<?php
/**
 * Admin Analytics Export (PDF)
 * Streams a PDF download with key dashboard analytics.
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin_login.php");
    exit();
}
if (($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: login.php");
    exit();
}
checkAdminActive();

require_once __DIR__ . "/lib/fpdf/fpdf.php";

function safeText($value): string
{
    if ($value === null) {
        return "";
    }
    $s = (string) $value;
    // FPDF expects ISO-8859-1 by default; replace unsupported bytes gracefully.
    $out = @iconv("UTF-8", "ISO-8859-1//TRANSLIT//IGNORE", $s);
    return $out !== false ? $out : preg_replace("/[^\x20-\x7E]/", "", $s);
}

function fmtInt($n): string
{
    return number_format((int) $n);
}

function fmtPct($n): string
{
    return number_format((float) $n, 1) . "%";
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM reports");
    $total_reports = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS c FROM reports WHERE delay_reason IS NOT NULL AND delay_reason != ''",
    );
    $active_delays = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
    $total_users = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $total_routes = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM route_definitions");
        $total_routes = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);
    } catch (PDOException $e) {
        $total_routes = 0;
    }

    $today = date("Y-m-d");
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM reports WHERE DATE(timestamp) = ?");
    $stmt->execute([$today]);
    $today_reports = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM reports WHERE DATE(timestamp) = ? AND delay_reason IS NOT NULL AND delay_reason != ''",
    );
    $stmt->execute([$today]);
    $today_delays = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $today_users = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);

    $today_routes = 0;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM route_definitions WHERE DATE(created_at) = ?",
        );
        $stmt->execute([$today]);
        $today_routes = (int) ($stmt->fetch(PDO::FETCH_ASSOC)["c"] ?? 0);
    } catch (PDOException $e) {
        $today_routes = 0;
    }

    $stmt = $pdo->query("
        SELECT
            CASE
                WHEN delay_reason IS NULL OR delay_reason = '' THEN 'Unspecified'
                ELSE delay_reason
            END AS delay_reason,
            COUNT(*) AS c
        FROM reports
        GROUP BY delay_reason
        ORDER BY c DESC
        LIMIT 8
    ");
    $delay_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            HOUR(`timestamp`) AS hour,
            COUNT(*) AS total_reports,
            SUM(CASE WHEN delay_reason IS NOT NULL AND delay_reason != '' THEN 1 ELSE 0 END) AS total_delays
        FROM reports
        WHERE `timestamp` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(`timestamp`)
        ORDER BY hour ASC
    ");
    $hourly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp,
               u.name as user_name,
               rd.name AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        ORDER BY r.timestamp DESC
        LIMIT 10
    ");
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Failed to generate analytics export.";
    exit();
}

$delay_rate = ($active_delays / max($total_reports, 1)) * 100;
$rpu = $total_reports / max($total_users, 1);
$rpr = $total_reports / max($total_routes, 1);

class AnalyticsPDF extends FPDF
{
    public string $title = "Analytics Export";

    // Color palette (dashboard)
    public $colorPrimary = [34, 51, 92];      // #22335C
    public $colorAccent = [251, 192, 97];     // #FBC061
    public $colorSuccess = [22, 163, 74];     // #16a34a
    public $colorDanger = [220, 38, 38];      // #dc2626
    public $colorInfo = [6, 182, 212];        // #06b6d4
    public $colorWarning = [245, 158, 11];    // #f59e0b
    public $colorSecondary = [91, 123, 153];  // #5B7B99
    public $colorGray = [241, 245, 249];      // #f1f5f9
    public $colorBorder = [226, 232, 240];    // #e2e8f0

    function Header()
    {
        // Colored header bar
        $this->SetFillColor(...$this->colorPrimary);
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');
        $this->SetFont("Helvetica", "B", 15);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(6);
        $this->Cell(0, 8, safeText("Transport Ops — " . $this->title), 0, 1, "L");
        $this->SetFont("Helvetica", "", 9);
        $this->SetTextColor(251, 192, 97); // Accent
        $this->Cell(0, 5, safeText("Generated: " . date("M d, Y H:i")), 0, 1, "L");
        $this->Ln(2);
        $this->SetY(20);
    }

    function Footer()
    {
        $this->SetY(-14);
        $this->SetFont("Helvetica", "", 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, safeText("Page " . $this->PageNo()), 0, 0, "R");
    }

    function SectionTitle($label, $color = null)
    {
        $this->Ln(4);
        if ($color) {
            $this->SetTextColor(...$color);
        } else {
            $this->SetTextColor(...$this->colorPrimary);
        }
        $this->SetFont("Helvetica", "B", 13);
        $this->Cell(0, 9, safeText($label), 0, 1, "L");
        $this->SetTextColor(51, 65, 85);
        $this->SetFont("Helvetica", "", 10);
    }

    function ColoredBox($label, $value, $color, $w = 45, $h = 18)
    {
        $this->SetFillColor(...$color);
        $this->SetTextColor(255,255,255);
        $this->SetFont("Helvetica", "B", 11);
        $this->Cell($w, $h, safeText($label), 0, 2, "C", true);
        $this->SetFont("Helvetica", "", 13);
        $this->Cell($w, 0, safeText($value), 0, 0, "C", false);
        $this->SetTextColor(51, 65, 85);
        $this->SetFont("Helvetica", "", 10);
    }

    // Simple horizontal bar chart (for trends)
    function BarChart($labels, $values, $barColor, $maxWidth = 90, $height = 6, $labelWidth = 38)
    {
        $max = max($values) ?: 1;
        $this->SetFont("Helvetica", "", 9);
        for ($i = 0; $i < count($labels); $i++) {
            $this->SetTextColor(51, 65, 85);
            $this->Cell($labelWidth, $height, safeText($labels[$i]), 0, 0, "L");
            $barW = ($values[$i] / $max) * $maxWidth;
            $this->SetFillColor(...$barColor);
            $this->Cell($barW, $height, '', 0, 0, '', true);
            $this->Cell(8, $height, safeText($values[$i]), 0, 1, "L");
        }
        $this->Ln(2);
    }
}

$pdf = new AnalyticsPDF("P", "mm", "A4");
$pdf->SetTitle("Transport Ops — Analytics Export", true);
$pdf->SetAuthor("Transport Ops", true);
$pdf->SetCreator("Transport Ops Admin Panel", true);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 16);

// Key metrics
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(0, 7, safeText("Key Metrics"), 0, 1, "L");
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetTextColor(51, 65, 85);

$metrics = [
    ["Total reports", fmtInt($total_reports), "Today", fmtInt($today_reports)],
    ["Active delays", fmtInt($active_delays), "Today", fmtInt($today_delays)],
    ["Total users", fmtInt($total_users), "Today", fmtInt($today_users)],
    ["Active routes", fmtInt($total_routes), "Today", fmtInt($today_routes)],
];

$w1 = 40;
$w2 = 35;
$w3 = 25;
$w4 = 25;
$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(226, 232, 240);
foreach ($metrics as $row) {
    $pdf->Cell($w1, 8, safeText($row[0]), 1, 0, "L", true);
    $pdf->Cell($w2, 8, safeText($row[1]), 1, 0, "L");
    $pdf->Cell($w3, 8, safeText($row[2]), 1, 0, "L", true);
    $pdf->Cell($w4, 8, safeText($row[3]), 1, 1, "L");
}
$pdf->Ln(6);

// Summary ratios
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(0, 7, safeText("Summary"), 0, 1, "L");
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetTextColor(51, 65, 85);
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetFillColor(241, 245, 249);

$summary = [
    ["Delay rate", fmtPct($delay_rate)],
    ["Reports per user", number_format($rpu, 1)],
    ["Reports per route", number_format($rpr, 1)],
];
foreach ($summary as $row) {
    $pdf->Cell(50, 8, safeText($row[0]), 1, 0, "L", true);
    $pdf->Cell(35, 8, safeText($row[1]), 1, 1, "L");
}
$pdf->Ln(6);

// Delay trends
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(0, 7, safeText("Top Delay Reasons (All Time)"), 0, 1, "L");
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetTextColor(51, 65, 85);

if (empty($delay_trends)) {
    $pdf->Cell(0, 6, safeText("No delay trend data available."), 0, 1, "L");
} else {
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(140, 8, safeText("Reason"), 1, 0, "L", true);
    $pdf->Cell(30, 8, safeText("Count"), 1, 1, "R", true);
    foreach ($delay_trends as $row) {
        $reason = (string) ($row["delay_reason"] ?? "Unspecified");
        $count = (int) ($row["c"] ?? 0);
        $pdf->Cell(140, 8, safeText($reason), 1, 0, "L");
        $pdf->Cell(30, 8, safeText(fmtInt($count)), 1, 1, "R");
    }
}
$pdf->Ln(6);

// Hourly trends (7 days)
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(30, 41, 59);

$pdf = new AnalyticsPDF("P", "mm", "A4");
$pdf->SetTitle("Transport Ops — Analytics Export", true);
$pdf->SetAuthor("Transport Ops", true);
$pdf->SetCreator("Transport Ops Admin Panel", true);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 16);

// Key Metrics Section
$pdf->SectionTitle("Key Metrics", $pdf->colorAccent);
$pdf->Ln(2);
$pdf->ColoredBox("Total Reports", fmtInt($total_reports), $pdf->colorPrimary);
$pdf->Cell(5);
$pdf->ColoredBox("Active Delays", fmtInt($active_delays), $pdf->colorDanger);
$pdf->Cell(5);
$pdf->ColoredBox("Total Users", fmtInt($total_users), $pdf->colorSuccess);
$pdf->Cell(5);
$pdf->ColoredBox("Active Routes", fmtInt($total_routes), $pdf->colorInfo);
$pdf->Ln(20);

// Today Metrics
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetTextColor(91, 123, 153);
$pdf->Cell(0, 6, safeText("Today: Reports: ") . fmtInt($today_reports) . safeText("   Delays: ") . fmtInt($today_delays) . safeText("   Users: ") . fmtInt($today_users) . safeText("   Routes: ") . fmtInt($today_routes), 0, 1, "L");
$pdf->Ln(2);

// Summary Section
$pdf->SectionTitle("Summary Ratios", $pdf->colorSecondary);
$summary = [
    ["Delay rate", fmtPct($delay_rate)],
    ["Reports per user", number_format($rpu, 1)],
    ["Reports per route", number_format($rpr, 1)],
];
$pdf->SetFillColor(...$pdf->colorGray);
$pdf->SetDrawColor(...$pdf->colorBorder);
foreach ($summary as $row) {
    $pdf->Cell(50, 8, safeText($row[0]), 1, 0, "L", true);
    $pdf->Cell(35, 8, safeText($row[1]), 1, 1, "L");
}
$pdf->Ln(6);

// Delay Trends Section
$pdf->SectionTitle("Top Delay Reasons (All Time)", $pdf->colorDanger);
if (empty($delay_trends)) {
    $pdf->Cell(0, 6, safeText("No delay trend data available."), 0, 1, "L");
} else {
    // Bar chart for delay reasons
    $labels = [];
    $values = [];
    foreach ($delay_trends as $row) {
        $labels[] = (string) ($row["delay_reason"] ?? "Unspecified");
        $values[] = (int) ($row["c"] ?? 0);
    }
    $pdf->BarChart($labels, $values, $pdf->colorDanger, 70, 7, 50);
}
$pdf->Ln(2);

// Hourly Trends Section
$pdf->SectionTitle("Hourly Trend (Last 7 Days)", $pdf->colorInfo);
if (empty($hourly_trends)) {
    $pdf->Cell(0, 6, safeText("Not enough reports yet to calculate hourly trends."), 0, 1, "L");
} else {
    // Bar chart for hourly reports
    $labels = [];
    $values = [];
    $delays = [];
    foreach ($hourly_trends as $row) {
        $h = (int) ($row["hour"] ?? 0);
        $label = date("g A", strtotime(sprintf("%02d:00", $h))) .
            "–" .
            date("g A", strtotime(sprintf("%02d:00", ($h + 1) % 24)));
        $labels[] = $label;
        $values[] = (int) ($row["total_reports"] ?? 0);
        $delays[] = (int) ($row["total_delays"] ?? 0);
    }
    $pdf->SetFont("Helvetica", "", 9);
    $pdf->Cell(0, 6, safeText("Reports by Hour:"), 0, 1, "L");
    $pdf->BarChart($labels, $values, $pdf->colorPrimary, 60, 5, 32);
    $pdf->Cell(0, 6, safeText("Delays by Hour:"), 0, 1, "L");
    $pdf->BarChart($labels, $delays, $pdf->colorDanger, 60, 5, 32);
}
$pdf->Ln(2);

// Recent Reports Section
$pdf->SectionTitle("Recent Reports (Latest 10)", $pdf->colorAccent);
$pdf->SetFont("Helvetica", "", 9);
if (empty($recent_reports)) {
    $pdf->Cell(0, 6, safeText("No reports found."), 0, 1, "L");
} else {
    $pdf->SetFillColor(...$pdf->colorGray);
    $pdf->Cell(30, 8, safeText("Time"), 1, 0, "L", true);
    $pdf->Cell(35, 8, safeText("User"), 1, 0, "L", true);
    $pdf->Cell(55, 8, safeText("Route"), 1, 0, "L", true);
    $pdf->Cell(20, 8, safeText("Crowd"), 1, 0, "L", true);
    $pdf->Cell(30, 8, safeText("Delay"), 1, 1, "L", true);

    foreach ($recent_reports as $r) {
        $time = "";
        if (!empty($r["timestamp"])) {
            $time = date("M d H:i", strtotime((string) $r["timestamp"]));
        }
        $user = (string) ($r["user_name"] ?? "N/A");
        $maskedUser = $user !== "N/A" ? maskUserName($user) : $user;
        $route = (string) ($r["route_name"] ?? "N/A");
        $crowd = (string) ($r["crowd_level"] ?? "");
        $delay = (string) ($r["delay_reason"] ?? "");
        $delayShort = $delay !== "" ? substr($delay, 0, 32) : "—";
        if ($delay !== "" && strlen($delay) > 32) {
            $delayShort .= "…";
        }

        $pdf->Cell(30, 8, safeText($time), 1, 0, "L");
        $pdf->Cell(35, 8, safeText(substr($maskedUser, 0, 18)), 1, 0, "L");
        $pdf->Cell(55, 8, safeText(substr($route, 0, 28)), 1, 0, "L");
        $pdf->Cell(20, 8, safeText($crowd), 1, 0, "L");
        $pdf->Cell(30, 8, safeText($delayShort), 1, 1, "L");
    }
}

$filename = "analytics_export_" . date("Ymd_His") . ".pdf";
$pdf->Output("D", $filename);
exit();

