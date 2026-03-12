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

    function Header()
    {
        $this->SetFont("Helvetica", "B", 14);
        $this->SetTextColor(34, 51, 92);
        $this->Cell(0, 8, safeText("Transport Ops — " . $this->title), 0, 1, "L");
        $this->SetFont("Helvetica", "", 9);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, safeText("Generated: " . date("M d, Y H:i")), 0, 1, "L");
        $this->Ln(2);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-14);
        $this->SetFont("Helvetica", "", 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, safeText("Page " . $this->PageNo()), 0, 0, "R");
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
$pdf->Cell(0, 7, safeText("Hourly Trend (Last 7 Days)"), 0, 1, "L");
$pdf->SetFont("Helvetica", "", 10);
$pdf->SetTextColor(51, 65, 85);

if (empty($hourly_trends)) {
    $pdf->Cell(0, 6, safeText("Not enough reports yet to calculate hourly trends."), 0, 1, "L");
} else {
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(30, 8, safeText("Hour"), 1, 0, "L", true);
    $pdf->Cell(40, 8, safeText("Reports"), 1, 0, "R", true);
    $pdf->Cell(40, 8, safeText("Delays"), 1, 1, "R", true);
    foreach ($hourly_trends as $row) {
        $h = (int) ($row["hour"] ?? 0);
        $label = date("g A", strtotime(sprintf("%02d:00", $h))) .
            "–" .
            date("g A", strtotime(sprintf("%02d:00", ($h + 1) % 24)));
        $pdf->Cell(30, 8, safeText($label), 1, 0, "L");
        $pdf->Cell(40, 8, safeText(fmtInt($row["total_reports"] ?? 0)), 1, 0, "R");
        $pdf->Cell(40, 8, safeText(fmtInt($row["total_delays"] ?? 0)), 1, 1, "R");
    }
}
$pdf->Ln(6);

// Recent reports
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(0, 7, safeText("Recent Reports (Latest 10)"), 0, 1, "L");
$pdf->SetFont("Helvetica", "", 9);
$pdf->SetTextColor(51, 65, 85);

if (empty($recent_reports)) {
    $pdf->Cell(0, 6, safeText("No reports found."), 0, 1, "L");
} else {
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(30, 8, safeText("Time"), 1, 0, "L", true);
    $pdf->Cell(35, 8, safeText("User"), 1, 0, "L", true);
    $pdf->Cell(55, 8, safeText("Route"), 1, 0, "L", true);
    $pdf->Cell(20, 8, safeText("Crowd"), 1, 0, "L", true);
    $pdf->Cell(30, 8, safeText("Delay"), 1, 1, "L", true);

    // Mask user names for privacy (e.g., J*** R** N)
    function maskUserName($name) {
        $parts = preg_split('/\s+/', trim($name));
        $masked = [];
        foreach ($parts as $i => $part) {
            if ($i === 0 && strlen($part) > 0) {
                // First name: first letter + ***
                $masked[] = strtoupper(substr($part, 0, 1)) . (strlen($part) > 1 ? str_repeat('*', max(2, strlen($part) - 1)) : '');
            } elseif ($i === count($parts) - 1 && strlen($part) > 0) {
                // Last name: first letter only
                $masked[] = strtoupper(substr($part, 0, 1));
            } elseif (strlen($part) > 0) {
                // Middle names: first letter + **
                $masked[] = strtoupper(substr($part, 0, 1)) . (strlen($part) > 1 ? str_repeat('*', 2) : '');
            }
        }
        return implode(' ', $masked);
    }

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

