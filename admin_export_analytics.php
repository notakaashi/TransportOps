<?php
/**
 * Export Analytics PDF  –  TransportOps Admin
 * Palette: Navy #22335C | Gold #FBC061 | Slate #5B7B99
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "lib/fpdf/fpdf.php";
require_once "privacy_helper.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin_login.php");
    exit();
}
if ($_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit();
}
checkAdminActive();

/* ── date range ── */
$date_from =
    isset($_GET["date_from"]) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["date_from"])
        ? $_GET["date_from"]
        : date("Y-m-d", strtotime("-29 days"));
$date_to =
    isset($_GET["date_to"]) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET["date_to"])
        ? $_GET["date_to"]
        : date("Y-m-d");
if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}
$range_label =
    date("M j, Y", strtotime($date_from)) .
    "  -  " .
    date("M j, Y", strtotime($date_to));
$range_chart_label = "Reports Over Time";

/* ── helpers ── */
function fmtHour(int $h): string
{
    return date("g A", mktime($h, 0, 0)) .
        " - " .
        date("g A", mktime(($h + 1) % 24, 0, 0));
}
function clip(string $s, int $n): string
{
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . "." : $s;
}

/* ── fetch data ── */
$total_reports = $active_delays = $total_users = $total_routes = 0;
$recent_reports = $delay_trends = $hourly_trends = $reports_over_time = [];
$top_delay_hour = null;

try {
    $pdo = getDBConnection();

    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?",
    );
    $s->execute([$date_from, $date_to]);
    $total_reports = (int) $s->fetchColumn();

    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?
         AND delay_reason IS NOT NULL AND delay_reason != ""',
    );
    $s->execute([$date_from, $date_to]);
    $active_delays = (int) $s->fetchColumn();

    $total_users = (int) $pdo
        ->query("SELECT COUNT(*) FROM users")
        ->fetchColumn();
    try {
        $total_routes = (int) $pdo
            ->query("SELECT COUNT(*) FROM route_definitions")
            ->fetchColumn();
    } catch (Exception $e) {
        $total_routes = 0;
    }

    $start_dt = new DateTime($date_from);
    $end_dt = new DateTime($date_to);
    $diff_days = (int) $start_dt->diff($end_dt)->days + 1;

    if ($diff_days <= 31) {
        $range_chart_label = "Reports Per Day";
        for ($d = clone $start_dt; $d <= $end_dt; $d->modify("+1 day")) {
            $dayStr = $d->format("Y-m-d");
            $s = $pdo->prepare(
                "SELECT COUNT(*) FROM reports WHERE DATE(timestamp) = ?",
            );
            $s->execute([$dayStr]);
            $reports_over_time[] = [
                "date" => $d->format("M j"),
                "count" => (int) $s->fetchColumn(),
            ];
        }
        // Limit to last 30 days to prevent chart overcrowding
        if (count($reports_over_time) > 30) {
            $reports_over_time = array_slice($reports_over_time, -30);
        }
    } elseif ($diff_days <= 180) {
        $range_chart_label = "Reports Per Week";
        $s = $pdo->prepare(
            "SELECT YEARWEEK(timestamp,1) AS wk, MIN(DATE(timestamp)) AS wk_start, COUNT(*) AS cnt
             FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?
             GROUP BY YEARWEEK(timestamp,1) ORDER BY wk",
        );
        $s->execute([$date_from, $date_to]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $wr) {
            $reports_over_time[] = [
                "date" => date("M j", strtotime($wr["wk_start"])),
                "count" => (int) $wr["cnt"],
            ];
        }
        // Limit to last 26 weeks to prevent chart overcrowding
        if (count($reports_over_time) > 26) {
            $reports_over_time = array_slice($reports_over_time, -26);
        }
    } else {
        $range_chart_label = "Reports Per Month";
        $s = $pdo->prepare(
            "SELECT DATE_FORMAT(timestamp,'%Y-%m') AS mo, COUNT(*) AS cnt
             FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(timestamp,'%Y-%m') ORDER BY mo",
        );
        $s->execute([$date_from, $date_to]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $mr) {
            $reports_over_time[] = [
                "date" => date("M Y", strtotime($mr["mo"] . "-01")),
                "count" => (int) $mr["cnt"],
            ];
        }
        // Limit to last 24 months to prevent chart overcrowding
        if (count($reports_over_time) > 24) {
            $reports_over_time = array_slice($reports_over_time, -24);
        }
    }

    $s = $pdo->prepare(
        'SELECT r.crowd_level, r.delay_reason, r.timestamp,
                u.name AS user_name, u.role AS user_role,
                rd.name AS route_name
         FROM reports r
         LEFT JOIN users u  ON r.user_id  = u.id
         LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
         WHERE DATE(r.timestamp) BETWEEN ? AND ?
         ORDER BY r.timestamp DESC LIMIT 12',
    );
    $s->execute([$date_from, $date_to]);
    $recent_reports = $s->fetchAll(PDO::FETCH_ASSOC);

    $s = $pdo->prepare(
        'SELECT COALESCE(NULLIF(delay_reason,""),"Unspecified") AS delay_reason, COUNT(*) AS count
         FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?
         GROUP BY delay_reason ORDER BY count DESC LIMIT 7',
    );
    $s->execute([$date_from, $date_to]);
    $delay_trends = $s->fetchAll(PDO::FETCH_ASSOC);

    $s = $pdo->prepare(
        'SELECT HOUR(timestamp) AS hour,
                COUNT(*) AS total_reports,
                SUM(CASE WHEN delay_reason IS NOT NULL AND delay_reason != "" THEN 1 ELSE 0 END) AS total_delays
         FROM reports WHERE DATE(timestamp) BETWEEN ? AND ?
         GROUP BY HOUR(timestamp) ORDER BY hour',
    );
    $s->execute([$date_from, $date_to]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    $maxDel = 0;
    foreach ($rows as $r) {
        $d = (int) ($r["total_delays"] ?? 0);
        if ($d > $maxDel) {
            $maxDel = $d;
            $top_delay_hour = (int) $r["hour"];
        }
    }
    // Limit hourly trends to prevent excessive pages
    $hourly_trends = array_slice($rows, 0, 24); // Max 24 hours
} catch (PDOException $e) {
    die("DB error: " . htmlspecialchars($e->getMessage()));
}

/* ══════════════════════════════════════════════════
   FPDF subclass  –  clean, simplified design
   ══════════════════════════════════════════════════ */
class AnalyticsPDF extends FPDF
{
    /* ── palette ── */
    const NAVY = [33, 51, 92];
    const GOLD = [251, 192, 97];
    const SLATE = [91, 123, 153];
    const WHITE = [255, 255, 255];
    const DARK = [30, 41, 59];
    const MUTED = [100, 116, 139];
    const LIGHT = [241, 244, 248];
    const GREEN = [22, 163, 74];
    const RED = [220, 38, 38];
    const PURPLE = [124, 58, 237];
    const BLUE = [37, 99, 235];
    const AMBER = [202, 138, 4];

    public string $reportDate = "";
    public string $adminName = "";

    /* ── page header ── */
    public function Header(): void
    {
        // Navy bar
        $this->SetFillColor(...self::NAVY);
        $this->Rect(0, 0, 210, 20, "F");
        // Gold accent rule
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, 20, 210, 2, "F");

        // Brand name
        $this->SetFont("Helvetica", "B", 11);
        $this->SetTextColor(...self::WHITE);
        $this->SetXY(10, 4);
        $this->Cell(80, 6, "TransportOps", 0, 0, "L");

        // Sub-label
        $this->SetFont("Helvetica", "", 6);
        $this->SetTextColor(160, 180, 220);
        $this->SetXY(10, 11);
        $this->Cell(80, 4, "ADMIN ANALYTICS EXPORT", 0, 0, "L");

        // Right: report date
        $this->SetFont("Helvetica", "", 6.5);
        $this->SetTextColor(180, 200, 230);
        $this->SetXY(100, 8);
        $this->Cell(100, 5, $this->reportDate, 0, 0, "R");

        // Reset cursor to content start so nothing drawn in Header()
        // causes the first content block to overlap the page header.
        $this->SetXY($this->lMargin, $this->tMargin);
    }

    /* ── page footer ── */
    public function Footer(): void
    {
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, $this->GetPageHeight() - 10, 210, 0.5, "F");
        $this->SetY(-8);
        $this->SetFont("Helvetica", "", 6.5);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(
            0,
            5,
            "TransportOps Admin  |  " .
                $this->reportDate .
                "  |  Page " .
                $this->PageNo() .
                " / {nb}",
            0,
            0,
            "C",
        );
    }

    /* ── section heading ── */
    public function SectionHeading(string $title, array $accent): void
    {
        $this->Ln(3);
        $y = $this->GetY();
        // Left accent bar
        $this->SetFillColor(...$accent);
        $this->Rect(10, $y, 3, 8, "F");
        // Light background
        $this->SetFillColor(245, 247, 252);
        $this->Rect(13, $y, 187, 8, "F");
        // Title
        $this->SetFont("Helvetica", "B", 9);
        $this->SetTextColor(...self::NAVY);
        $this->SetXY(18, $y + 1.5);
        $this->Cell(182, 5, $title, 0, 1);
        $this->Ln(3);
    }

    /* ── KPI card  (flat – top accent strip, no rounded-corner hacks) ── */
    public function KPICard(
        float $x,
        float $y,
        float $w,
        float $h,
        string $val,
        string $label,
        string $sub,
        array $accent,
    ): void {
        // White card with accent-colored border
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(...$accent);
        $this->SetLineWidth(0.5);
        $this->Rect($x, $y, $w, $h, "FD");
        $this->SetLineWidth(0.2);
        // Thicker colored top strip
        $this->SetFillColor(...$accent);
        $this->Rect($x, $y, $w, 5, "F");
        // Light tinted bottom area for sub-note
        $tr = (int) ($accent[0] * 0.1 + 255 * 0.9);
        $tg = (int) ($accent[1] * 0.1 + 255 * 0.9);
        $tb = (int) ($accent[2] * 0.1 + 255 * 0.9);
        $this->SetFillColor($tr, $tg, $tb);
        $this->Rect($x, $y + $h - 7, $w, 7, "F");
        // Soft divider above sub-note
        $this->SetDrawColor(...$accent);
        $this->SetLineWidth(0.15);
        $this->Line($x + 4, $y + $h - 7, $x + $w - 4, $y + $h - 7);
        $this->SetLineWidth(0.2);
        // Value (accent colored, bold)
        $this->SetFont("Helvetica", "B", 15);
        $this->SetTextColor(...$accent);
        $this->SetXY($x, $y + 6);
        $this->Cell($w, 8, $val, 0, 0, "C");
        // Label (muted)
        $this->SetFont("Helvetica", "", 7);
        $this->SetTextColor(...self::MUTED);
        $this->SetXY($x, $y + 14.5);
        $this->Cell($w, 4.5, $label, 0, 0, "C");
        // Sub note (accent, in tinted area)
        $this->SetFont("Helvetica", "B", 6);
        $this->SetTextColor(...$accent);
        $this->SetXY($x, $y + $h - 5.5);
        $this->Cell($w, 4, $sub, 0, 0, "C");
    }

    /* ── stat pill ── */
    public function StatPill(
        float $x,
        float $y,
        float $w,
        float $h,
        string $val,
        string $label,
        array $accent = [33, 51, 92],
    ): void {
        // Very light tinted background (subtle wash)
        $tr = (int) ($accent[0] * 0.07 + 255 * 0.93);
        $tg = (int) ($accent[1] * 0.07 + 255 * 0.93);
        $tb = (int) ($accent[2] * 0.07 + 255 * 0.93);
        $this->SetFillColor($tr, $tg, $tb);
        $this->SetDrawColor(...$accent);
        $this->SetLineWidth(0.4);
        $this->Rect($x, $y, $w, $h, "FD");
        $this->SetLineWidth(0.2);
        // Left accent bar
        $this->SetFillColor(...$accent);
        $this->Rect($x, $y, 3, $h, "F");
        // Value (accent colored)
        $this->SetFont("Helvetica", "B", 12);
        $this->SetTextColor(...$accent);
        $this->SetXY($x, $y + 2);
        $this->Cell($w, 6, $val, 0, 0, "C");
        // Label
        $this->SetFont("Helvetica", "", 6.5);
        $this->SetTextColor(...self::MUTED);
        $this->SetXY($x, $y + 9);
        $this->Cell($w, 4, $label, 0, 0, "C");
    }

    /* ── horizontal bar (simple rects, no bezier) ── */
    public function HBar(
        float $x,
        float $y,
        float $maxW,
        float $pct,
        array $color,
    ): void {
        // Track
        $this->SetFillColor(225, 230, 242);
        $this->Rect($x, $y, $maxW, 3, "F");
        // Fill
        if ($pct > 0) {
            $this->SetFillColor(...$color);
            $this->Rect($x, $y, max(2, ($maxW * $pct) / 100), 3, "F");
        }
    }

    /* ── bar chart ── */
    public function BarChart(
        float $x,
        float $y,
        float $w,
        float $h,
        array $data,
        array $color,
    ): void {
        $n = count($data);
        if ($n === 0) {
            return;
        }

        // Chart background
        $this->SetFillColor(247, 249, 253);
        $this->SetDrawColor(220, 228, 240);
        $this->Rect($x, $y, $w, $h, "FD");

        $maxV = max(1, max(array_column($data, "count")));
        $padL = 5;
        $padR = 4;
        $padT = 7;
        $padB = 10;
        $areaW = $w - $padL - $padR;
        $areaH = $h - $padT - $padB;
        $slot = $areaW / $n;
        $bW = max(2.5, $slot * 0.62);

        // Horizontal grid lines
        $this->SetDrawColor(215, 222, 235);
        $this->SetLineWidth(0.12);
        for ($g = 1; $g <= 3; $g++) {
            $gy = $y + $padT + $areaH - ($areaH * $g) / 4;
            $this->Line($x + $padL, $gy, $x + $padL + $areaW, $gy);
        }
        $this->SetLineWidth(0.2);

        // Smart label interval to avoid crowding
        $labelEvery = 1;
        if ($n > 25) {
            $labelEvery = 7;
        } elseif ($n > 15) {
            $labelEvery = 5;
        } elseif ($n > 10) {
            $labelEvery = 3;
        } elseif ($n > 6) {
            $labelEvery = 2;
        }

        for ($i = 0; $i < $n; $i++) {
            $v = (int) $data[$i]["count"];
            $bH = $v > 0 ? max(1.5, ($v / $maxV) * $areaH) : 0;
            $bx = $x + $padL + $i * $slot + ($slot - $bW) / 2;
            $by = $y + $padT + $areaH - $bH;

            if ($bH > 0) {
                $this->SetFillColor(...$color);
                $this->Rect($bx, $by, $bW, $bH, "F");
                // Value above bar (only when tall enough)
                if ($bH > 5) {
                    $this->SetFont("Helvetica", "B", 5);
                    $this->SetTextColor(...self::NAVY);
                    $this->SetXY($bx - 2, max($y + 1.5, $by - 5));
                    $this->Cell($bW + 4, 4, (string) $v, 0, 0, "C");
                }
            }

            // Date label — skipped on crowded charts
            if ($i % $labelEvery === 0) {
                $this->SetFont("Helvetica", "", 5);
                $this->SetTextColor(...self::MUTED);
                $this->SetXY($bx - 2, $y + $padT + $areaH + 1.5);
                $this->Cell($bW + 4, 4, $data[$i]["date"], 0, 0, "C");
            }
        }

        // Baseline
        $this->SetDrawColor(...$color);
        $this->SetLineWidth(0.35);
        $this->Line(
            $x + $padL,
            $y + $padT + $areaH,
            $x + $padL + $areaW,
            $y + $padT + $areaH,
        );
        $this->SetLineWidth(0.2);
    }

    /* ── table header ── */
    public function THead(array $cols): void
    {
        $x0 = $this->GetX();
        $y0 = $this->GetY();
        $tw = array_sum(array_column($cols, "w"));
        $this->SetFillColor(...self::NAVY);
        $this->Rect($x0, $y0, $tw, 7.5, "F");
        $this->SetFont("Helvetica", "B", 7);
        $this->SetTextColor(...self::WHITE);
        $curX = $x0;
        foreach ($cols as $c) {
            $this->SetXY($curX, $y0);
            $this->Cell($c["w"], 7.5, $c["label"], 0, 0, $c["align"] ?? "L");
            $curX += $c["w"];
        }
        $this->SetXY($x0, $y0 + 7.5);
    }

    /* ── table row (explicit X tracking – no drift) ── */
    public function TRow(array $cells, int $idx): void
    {
        $x0 = $this->GetX();
        $y0 = $this->GetY();
        $tw = array_sum(array_column($cells, "w"));
        $rowH = 7;

        if ($idx % 2 === 0) {
            $this->SetFillColor(246, 248, 252);
            $this->Rect($x0, $y0, $tw, $rowH, "F");
        }
        $this->SetDrawColor(220, 228, 240);
        $this->Line($x0, $y0 + $rowH, $x0 + $tw, $y0 + $rowH);

        $curX = $x0;
        foreach ($cells as $c) {
            $col = $c["color"] ?? self::DARK;
            $this->SetFont("Helvetica", $c["style"] ?? "", 7);
            $this->SetTextColor($col[0], $col[1], $col[2]);
            $this->SetXY($curX, $y0 + 0.5);
            $this->Cell($c["w"], $rowH, $c["v"], 0, 0, $c["align"] ?? "L");
            $curX += $c["w"];
        }
        $this->SetXY($x0, $y0 + $rowH);
    }

    /* ── color helpers ── */
    public function crowdColor(string $l): array
    {
        return match (strtolower(trim($l))) {
            "light" => self::GREEN,
            "moderate" => self::AMBER,
            "heavy" => self::RED,
            default => self::MUTED,
        };
    }
    public function roleColor(string $r): array
    {
        return match (strtolower(trim($r))) {
            "admin" => self::RED,
            "driver" => self::BLUE,
            default => self::MUTED,
        };
    }
    public function delayPalette(int $i): array
    {
        $p = [
            self::RED,
            self::AMBER,
            self::BLUE,
            self::PURPLE,
            self::SLATE,
            self::GREEN,
        ];
        return $p[$i % count($p)];
    }
}

/* ══════════════════════════════════════════════════
   BUILD PDF
   ══════════════════════════════════════════════════ */
$pdf = new AnalyticsPDF("P", "mm", "A4");
$pdf->reportDate =
    "Period: " . $range_label . "  |  Exported: " . date("M j, Y  H:i");
$pdf->adminName = censorUserName($_SESSION["user_name"] ?? "Admin");
$pdf->AliasNbPages();
$pdf->SetMargins(10, 26, 10);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$pW = 190; // usable page width
$sX = 10; // left margin

/* ─────────────────────────────────────────────────
   TITLE STRIP
   ───────────────────────────────────────────────── */
$hy = $pdf->GetY();

// Navy background
$pdf->SetFillColor(33, 51, 92);
$pdf->Rect($sX, $hy, $pW, 22, "F");
// Gold bottom rule
$pdf->SetFillColor(251, 192, 97);
$pdf->Rect($sX, $hy + 21, $pW, 1, "F");

// Report title
$pdf->SetFont("Helvetica", "B", 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY($sX + 6, $hy + 3.5);
$pdf->Cell($pW - 12, 7, "Dashboard Analytics Report", 0, 0, "L");

// Exported by
$pdf->SetFont("Helvetica", "", 7);
$pdf->SetTextColor(160, 180, 220);
$pdf->SetXY($sX + 6, $hy + 11);
$pdf->Cell(
    $pW - 12,
    5,
    "Exported by " . $pdf->adminName . "   |   " . date("D, M j, Y   H:i"),
    0,
    0,
    "L",
);

// Period
$pdf->SetFont("Helvetica", "B", 6.5);
$pdf->SetTextColor(251, 192, 97);
$pdf->SetXY($sX + 6, $hy + 17);
$pdf->Cell(14, 4, "Period:", 0, 0, "L");
$pdf->SetFont("Helvetica", "", 6.5);
$pdf->SetTextColor(180, 200, 230);
$pdf->SetXY($sX + 20, $hy + 17);
$pdf->Cell($pW - 26, 4, $range_label, 0, 0, "L");

$pdf->SetY($hy + 28);

/* ─────────────────────────────────────────────────
   SECTION 1 – KPI CARDS
   4 × 44 mm + 3 gaps = 190 mm
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("System Overview", [33, 51, 92]);

$cW = 44;
$cH = 26;
$cGap = (190 - 4 * $cW) / 3;
$cY = $pdf->GetY();

$dRate = round(($active_delays / max($total_reports, 1)) * 100, 1);
$kpis = [
    [
        number_format($total_reports),
        "Total Reports",
        "in selected period",
        [37, 99, 235],
    ],
    [
        number_format($active_delays),
        "Active Delays",
        $dRate . "% delay rate",
        [220, 38, 38],
    ],
    [
        number_format($total_users),
        "Registered Users",
        "system-wide total",
        [124, 58, 237],
    ],
    [
        number_format($total_routes),
        "Active Routes",
        "total defined",
        [91, 123, 153],
    ],
];
foreach ($kpis as $i => $k) {
    $pdf->KPICard(
        $sX + $i * ($cW + $cGap),
        $cY,
        $cW,
        $cH,
        $k[0],
        $k[1],
        $k[2],
        $k[3],
    );
}
$pdf->SetY($cY + $cH + 8);

/* Stat pills: 3 × 60 mm + 2 × 5 mm gap = 190 mm */
$rpu = round($total_reports / max($total_users, 1), 1);
$rpr = round($total_reports / max($total_routes, 1), 1);
$sy = $pdf->GetY();
$pW3 = 60;
$pGap = 5;
$pH = 13;
$pdf->StatPill($sX, $sy, $pW3, $pH, $dRate . "%", "Delay Rate", [220, 38, 38]);
$pdf->StatPill(
    $sX + $pW3 + $pGap,
    $sy,
    $pW3,
    $pH,
    (string) $rpu,
    "Reports / User",
    [37, 99, 235],
);
$pdf->StatPill(
    $sX + ($pW3 + $pGap) * 2,
    $sy,
    $pW3,
    $pH,
    (string) $rpr,
    "Reports / Route",
    [124, 58, 237],
);
$pdf->SetY($sy + $pH + 8);

/* ─────────────────────────────────────────────────
   SECTION 2 – REPORTS OVER TIME  (bar chart)
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}
$pdf->SectionHeading($range_chart_label . "  (" . $range_label . ")", [
    37,
    99,
    235,
]);
$pdf->BarChart($sX, $pdf->GetY(), $pW, 48, $reports_over_time, [37, 99, 235]);
$pdf->SetY($pdf->GetY() + 58);

/* ─────────────────────────────────────────────────
   SECTION 3 – DELAY TRENDS
   Reason(75) + Count(16) + Share(20) + Bar(79) = 190 mm
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
}
$pdf->SectionHeading("Delay Trends Breakdown", [220, 38, 38]);

if (empty($delay_trends)) {
    $pdf->SetFont("Helvetica", "", 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "No delay data available for this period.", 0, 1);
} else {
    $dLW = 75;
    $dCW = 16;
    $dSW = 20;
    $dBW = 79;
    $maxCnt = max(1, max(array_column($delay_trends, "count")));

    $pdf->THead([
        ["label" => "Delay Reason", "w" => $dLW, "align" => "L"],
        ["label" => "Count", "w" => $dCW, "align" => "C"],
        ["label" => "Share", "w" => $dSW, "align" => "C"],
        ["label" => "Visual", "w" => $dBW, "align" => "L"],
    ]);

    foreach ($delay_trends as $idx => $row) {
        $reason = clip($row["delay_reason"] ?? "Unspecified", 44);
        $cnt = (int) $row["count"];
        $sharePct = round(($cnt / max($total_reports, 1)) * 100, 1);
        $barColor = $pdf->delayPalette($idx);
        $rowH = 8;
        $rx = $pdf->GetX();
        $ry = $pdf->GetY();

        if ($idx % 2 === 0) {
            $pdf->SetFillColor(246, 248, 252);
            $pdf->Rect($rx, $ry, $dLW + $dCW + $dSW + $dBW, $rowH, "F");
        }
        $pdf->SetDrawColor(220, 228, 240);
        $pdf->Line(
            $rx,
            $ry + $rowH,
            $rx + $dLW + $dCW + $dSW + $dBW,
            $ry + $rowH,
        );

        $pdf->SetFont("Helvetica", "", 7);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetXY($rx, $ry + 0.5);
        $pdf->Cell($dLW, $rowH, $reason, 0, 0, "L");

        $pdf->SetFont("Helvetica", "B", 7);
        $pdf->SetTextColor($barColor[0], $barColor[1], $barColor[2]);
        $pdf->SetXY($rx + $dLW, $ry + 0.5);
        $pdf->Cell($dCW, $rowH, (string) $cnt, 0, 0, "C");

        $pdf->SetFont("Helvetica", "", 7);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY($rx + $dLW + $dCW, $ry + 0.5);
        $pdf->Cell($dSW, $rowH, $sharePct . "%", 0, 0, "C");

        $pdf->HBar(
            $rx + $dLW + $dCW + $dSW + 2,
            $ry + 2.5,
            $dBW - 4,
            round(($cnt / $maxCnt) * 100),
            $barColor,
        );
        $pdf->SetXY($rx, $ry + $rowH);
    }
}
$pdf->Ln(3);

/* ─────────────────────────────────────────────────
   SECTION 4 – PEAK HOUR ANALYSIS
   Hour(38) + Rep#(14) + RepBar(40) + Del#(14) + DelBar(40) + Status(44) = 190 mm
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 180) {
    $pdf->AddPage();
}
$pdf->SectionHeading("Peak Hour Analysis  (" . $range_label . ")", [
    124,
    58,
    237,
]);

if (empty($hourly_trends)) {
    $pdf->SetFont("Helvetica", "", 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "Not enough data to calculate hourly trends.", 0, 1);
} else {
    $hH = 38;
    $hRN = 14;
    $hRB = 40;
    $hDN = 14;
    $hDB = 40;
    $hSt = 44;
    $maxRep = max(1, max(array_column($hourly_trends, "total_reports")));
    $maxDel = max(1, max(array_column($hourly_trends, "total_delays")));

    $pdf->THead([
        ["label" => "Hour Range", "w" => $hH, "align" => "L"],
        ["label" => "Reports", "w" => $hRN, "align" => "C"],
        ["label" => "Volume", "w" => $hRB, "align" => "L"],
        ["label" => "Delays", "w" => $hDN, "align" => "C"],
        ["label" => "Delay Bar", "w" => $hDB, "align" => "L"],
        ["label" => "Status", "w" => $hSt, "align" => "C"],
    ]);

    foreach ($hourly_trends as $idx => $row) {
        $hour = (int) ($row["hour"] ?? 0);
        $rep = (int) ($row["total_reports"] ?? 0);
        $del = (int) ($row["total_delays"] ?? 0);
        $isHot =
            $top_delay_hour !== null && $hour === $top_delay_hour && $del > 0;
        $repPct = round(($rep / $maxRep) * 100);
        $delPct = round(($del / $maxDel) * 100);
        $rowH = 8;
        $tw = $hH + $hRN + $hRB + $hDN + $hDB + $hSt;
        $rx = $pdf->GetX();
        $ry = $pdf->GetY();

        if ($isHot) {
            $pdf->SetFillColor(255, 243, 243);
            $pdf->Rect($rx, $ry, $tw, $rowH, "F");
        } elseif ($idx % 2 === 0) {
            $pdf->SetFillColor(246, 248, 252);
            $pdf->Rect($rx, $ry, $tw, $rowH, "F");
        }
        $pdf->SetDrawColor(220, 228, 240);
        $pdf->Line($rx, $ry + $rowH, $rx + $tw, $ry + $rowH);

        // Hour label
        $pdf->SetFont("Helvetica", $isHot ? "B" : "", 7);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetXY($rx, $ry + 0.5);
        $pdf->Cell($hH, $rowH, fmtHour($hour), 0, 0, "L");

        // Report count
        $pdf->SetFont("Helvetica", "B", 7);
        $pdf->SetTextColor(37, 99, 235);
        $pdf->SetXY($rx + $hH, $ry + 0.5);
        $pdf->Cell($hRN, $rowH, (string) $rep, 0, 0, "C");

        // Report bar
        $pdf->HBar($rx + $hH + $hRN + 1, $ry + 2.5, $hRB - 2, $repPct, [
            37,
            99,
            235,
        ]);

        // Delay count
        $pdf->SetFont("Helvetica", "B", 7);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetXY($rx + $hH + $hRN + $hRB, $ry + 0.5);
        $pdf->Cell($hDN, $rowH, (string) $del, 0, 0, "C");

        // Delay bar
        $pdf->HBar(
            $rx + $hH + $hRN + $hRB + $hDN + 1,
            $ry + 2.5,
            $hDB - 2,
            $delPct,
            [220, 38, 38],
        );

        // Status badge
        $bx = $rx + $hH + $hRN + $hRB + $hDN + $hDB;
        if ($isHot) {
            $pdf->SetFillColor(220, 38, 38);
            $pdf->Rect($bx + 5, $ry + 2, 34, 5, "F");
            $pdf->SetFont("Helvetica", "B", 6);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($bx + 5, $ry + 3.2);
            $pdf->Cell(34, 3.5, "PEAK HOTSPOT", 0, 0, "C");
        } else {
            $pdf->SetFont("Helvetica", "", 7);
            $pdf->SetTextColor(200, 210, 225);
            $pdf->SetXY($bx, $ry + 0.5);
            $pdf->Cell($hSt, $rowH, "-", 0, 0, "C");
        }

        $pdf->SetXY($rx, $ry + $rowH);
    }
}
$pdf->Ln(3);

/* ─────────────────────────────────────────────────
   SECTION 5 – RECENT REPORTS
   Date(28) + User(38) + Role(22) + Route(40) + Crowd(22) + Delay(40) = 190 mm
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 160) {
    $pdf->AddPage();
}
$pdf->SectionHeading("Recent Reports  (Last 12 in Period)", [91, 123, 153]);

if (empty($recent_reports)) {
    $pdf->SetFont("Helvetica", "", 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "No reports found for this period.", 0, 1);
} else {
    $pdf->THead([
        ["label" => "Date & Time", "w" => 28, "align" => "L"],
        ["label" => "User", "w" => 38, "align" => "L"],
        ["label" => "Role", "w" => 22, "align" => "L"],
        ["label" => "Route", "w" => 40, "align" => "L"],
        ["label" => "Crowd", "w" => 22, "align" => "C"],
        ["label" => "Delay Reason", "w" => 40, "align" => "L"],
    ]);

    foreach ($recent_reports as $idx => $r) {
        $ts = strtotime($r["timestamp"] ?? "now");
        $dt = date("M d", $ts) . "  " . date("H:i", $ts);
        $user = clip(censorUserName($r["user_name"] ?? "N/A"), 22);
        $role = $r["user_role"] ?? "";
        $route = clip($r["route_name"] ?? "N/A", 24);
        $crowd = $r["crowd_level"] ?? "-";
        $delay = clip($r["delay_reason"] ?? "-", 23);

        $pdf->TRow(
            [
                ["v" => $dt, "w" => 28, "style" => "", "color" => [30, 41, 59]],
                [
                    "v" => $user,
                    "w" => 38,
                    "style" => "B",
                    "color" => [30, 41, 59],
                ],
                [
                    "v" => $role,
                    "w" => 22,
                    "style" => "",
                    "color" => $pdf->roleColor($role),
                ],
                [
                    "v" => $route,
                    "w" => 40,
                    "style" => "",
                    "color" => [30, 41, 59],
                ],
                [
                    "v" => $crowd,
                    "w" => 22,
                    "style" => "B",
                    "color" => $pdf->crowdColor($crowd),
                    "align" => "C",
                ],
                [
                    "v" => $delay,
                    "w" => 40,
                    "style" => "",
                    "color" => [100, 116, 139],
                ],
            ],
            $idx,
        );
    }
}
$pdf->Ln(3);

/* ─────────────────────────────────────────────────
   SECTION 6 – REGISTERED USERS OVERVIEW
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 140) {
    $pdf->AddPage();
}
$pdf->SectionHeading("Registered Users Overview", [251, 192, 97]);

try {
    $roleRows = $pdo
        ->query(
            "SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY cnt DESC",
        )
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roleRows = [];
}

$roleMap = [
    "Admin" => [220, 38, 38],
    "Driver" => [37, 99, 235],
    "Commuter" => [100, 116, 139],
];
$pillW = 58;
$pillH = 16;
$pillGap = 4;
$rY = $pdf->GetY();

foreach ($roleRows as $ri => $rr) {
    $rc = $roleMap[$rr["role"]] ?? [91, 123, 153];
    $px = $sX + $ri * ($pillW + $pillGap);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(215, 222, 235);
    $pdf->Rect($px, $rY, $pillW, $pillH, "FD");

    // Left accent strip
    $pdf->SetFillColor($rc[0], $rc[1], $rc[2]);
    $pdf->Rect($px, $rY, $pillW, 3, "F");

    $pdf->SetFont("Helvetica", "B", 12);
    $pdf->SetTextColor($rc[0], $rc[1], $rc[2]);
    $pdf->SetXY($px, $rY + 4.5);
    $pdf->Cell($pillW, 6, number_format((int) $rr["cnt"]), 0, 0, "C");

    $pdf->SetFont("Helvetica", "", 6.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY($px, $rY + 11);
    $pdf->Cell($pillW, 4, $rr["role"] . "s", 0, 0, "C");
}

// Total label
$afterX = $sX + count($roleRows) * ($pillW + $pillGap) + 2;
$pdf->SetFont("Helvetica", "B", 8);
$pdf->SetTextColor(33, 51, 92);
$pdf->SetXY($afterX, $rY + 6);
$pdf->Cell(
    50,
    5,
    "Total: " . number_format($total_users) . " users",
    0,
    0,
    "L",
);

$pdf->SetY($rY + $pillH + 5);

/* ─────────────────────────────────────────────────
   SECTION 7 – SUMMARY & NOTES
   ───────────────────────────────────────────────── */
// Check if we need a new page for this section
if ($pdf->GetY() > 120) {
    $pdf->AddPage();
}
$pdf->SectionHeading("Summary & Notes", [33, 51, 92]);

$smY = $pdf->GetY();
$pdf->SetFillColor(245, 247, 252);
$pdf->SetDrawColor(215, 222, 235);
$pdf->Rect($sX, $smY, $pW, 30, "FD");
// Navy left bar
$pdf->SetFillColor(33, 51, 92);
$pdf->Rect($sX, $smY, 3.5, 30, "F");

$notes = [
    "Delay rate: " .
    $dRate .
    "%  (" .
    number_format($active_delays) .
    " of " .
    number_format($total_reports) .
    " reports had a delay reason).",
    "Peak delay hour: " .
    ($top_delay_hour !== null
        ? fmtHour($top_delay_hour)
        : "Insufficient data") .
    ".",
    "Avg " . $rpu . " report(s) per user  |  " . $rpr . " report(s) per route.",
    "Generated automatically by TransportOps Admin Analytics.",
];

$pdf->SetXY($sX + 8, $smY + 3);
foreach ($notes as $note) {
    // Gold square bullet
    $pdf->SetFillColor(251, 192, 97);
    $pdf->Rect($sX + 9, $pdf->GetY() + 2, 2, 2, "F");
    $pdf->SetFont("Helvetica", "", 7.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetX($sX + 14);
    $pdf->Cell($pW - 18, 6, $note, 0, 1);
}

/* ─────────────────────────────────────────────────
   OUTPUT
   ───────────────────────────────────────────────── */
$filename = "TransportOps_Analytics_" . date("Y-m-d_Hi") . ".pdf";
$pdf->Output("D", $filename);
exit();
