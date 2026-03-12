<?php
/**
 * Export Analytics PDF  –  TransportOps Admin
 * Palette: Navy #22335C | Gold #FBC061 | Slate #5B7B99
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "lib/fpdf/fpdf.php";

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

    /* reports & delays filtered to the selected range */
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

    /* users and routes are system-wide totals */
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

    /* ── reports-over-time: dynamic grouping based on range length ── */
    $start_dt = new DateTime($date_from);
    $end_dt = new DateTime($date_to);
    $diff_days = (int) $start_dt->diff($end_dt)->days + 1;

    if ($diff_days <= 31) {
        /* daily */
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
    } elseif ($diff_days <= 180) {
        /* weekly */
        $range_chart_label = "Reports Per Week";
        $s = $pdo->prepare(
            "SELECT YEARWEEK(timestamp, 1) AS wk,
                    MIN(DATE(timestamp)) AS wk_start,
                    COUNT(*) AS cnt
             FROM reports
             WHERE DATE(timestamp) BETWEEN ? AND ?
             GROUP BY YEARWEEK(timestamp, 1)
             ORDER BY wk",
        );
        $s->execute([$date_from, $date_to]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $wr) {
            $reports_over_time[] = [
                "date" => date("M j", strtotime($wr["wk_start"])),
                "count" => (int) $wr["cnt"],
            ];
        }
    } else {
        /* monthly */
        $range_chart_label = "Reports Per Month";
        $s = $pdo->prepare(
            "SELECT DATE_FORMAT(timestamp, '%Y-%m') AS mo,
                    COUNT(*) AS cnt
             FROM reports
             WHERE DATE(timestamp) BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(timestamp, '%Y-%m')
             ORDER BY mo",
        );
        $s->execute([$date_from, $date_to]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $mr) {
            $reports_over_time[] = [
                "date" => date("M Y", strtotime($mr["mo"] . "-01")),
                "count" => (int) $mr["cnt"],
            ];
        }
    }

    /* recent reports in range */
    $s = $pdo->prepare(
        'SELECT r.crowd_level, r.delay_reason, r.timestamp,
                u.name AS user_name, u.role AS user_role,
                rd.name AS route_name
         FROM reports r
         LEFT JOIN users u  ON r.user_id = u.id
         LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
         WHERE DATE(r.timestamp) BETWEEN ? AND ?
         ORDER BY r.timestamp DESC LIMIT 12',
    );
    $s->execute([$date_from, $date_to]);
    $recent_reports = $s->fetchAll(PDO::FETCH_ASSOC);

    /* delay trends in range */
    $s = $pdo->prepare(
        'SELECT COALESCE(NULLIF(delay_reason,""),"Unspecified") AS delay_reason,
                COUNT(*) AS count
         FROM reports
         WHERE DATE(timestamp) BETWEEN ? AND ?
         GROUP BY delay_reason
         ORDER BY count DESC LIMIT 7',
    );
    $s->execute([$date_from, $date_to]);
    $delay_trends = $s->fetchAll(PDO::FETCH_ASSOC);

    /* hourly trends in range */
    $s = $pdo->prepare(
        'SELECT HOUR(timestamp) AS hour,
                COUNT(*) AS total_reports,
                SUM(CASE WHEN delay_reason IS NOT NULL AND delay_reason != "" THEN 1 ELSE 0 END) AS total_delays
         FROM reports
         WHERE DATE(timestamp) BETWEEN ? AND ?
         GROUP BY HOUR(timestamp)
         ORDER BY hour',
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
    $hourly_trends = $rows;
} catch (PDOException $e) {
    die("DB error: " . htmlspecialchars($e->getMessage()));
}

/* ══════════════════════════════════════════════════
   FPDF subclass
   ══════════════════════════════════════════════════ */
class AnalyticsPDF extends FPDF
{
    /* palette */
    const NAVY = [33, 51, 92];
    const GOLD = [251, 192, 97];
    const SLATE = [91, 123, 153];
    const WHITE = [255, 255, 255];
    const DARK = [30, 41, 59];
    const MUTED = [100, 116, 139];
    const LIGHT = [237, 240, 247];
    const GREEN = [22, 163, 74];
    const RED = [220, 38, 38];
    const PURPLE = [124, 58, 237];
    const BLUE = [37, 99, 235];
    const AMBER = [202, 138, 4];

    public string $reportDate = "";
    public string $adminName = "";

    /* ── filled circle ── */
    public function FilledCircle(float $cx, float $cy, float $r): void
    {
        $k = $this->k;
        $hp = $this->h;
        $c = (4 / 3) * (sqrt(2) - 1) * $r;
        $this->_out(sprintf("%.2F %.2F m", ($cx + $r) * $k, ($hp - $cy) * $k));
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($cx + $r) * $k,
                ($hp - ($cy - $c)) * $k,
                ($cx + $c) * $k,
                ($hp - ($cy - $r)) * $k,
                $cx * $k,
                ($hp - ($cy - $r)) * $k,
            ),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($cx - $c) * $k,
                ($hp - ($cy - $r)) * $k,
                ($cx - $r) * $k,
                ($hp - ($cy - $c)) * $k,
                ($cx - $r) * $k,
                ($hp - $cy) * $k,
            ),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($cx - $r) * $k,
                ($hp - ($cy + $c)) * $k,
                ($cx - $c) * $k,
                ($hp - ($cy + $r)) * $k,
                $cx * $k,
                ($hp - ($cy + $r)) * $k,
            ),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c f",
                ($cx + $c) * $k,
                ($hp - ($cy + $r)) * $k,
                ($cx + $r) * $k,
                ($hp - ($cy + $c)) * $k,
                ($cx + $r) * $k,
                ($hp - $cy) * $k,
            ),
        );
    }

    /* ── rounded rectangle ── */
    public function RoundedRect(
        float $x,
        float $y,
        float $w,
        float $h,
        float $r,
        string $style = "",
    ): void {
        $op = match ($style) {
            "F" => "f",
            "FD", "DF" => "B",
            default => "S",
        };
        $k = $this->k;
        $hp = $this->h;
        $c = (4 / 3) * (sqrt(2) - 1);
        $this->_out(sprintf("%.2F %.2F m", ($x + $r) * $k, ($hp - $y) * $k));
        $this->_out(
            sprintf("%.2F %.2F l", ($x + $w - $r) * $k, ($hp - $y) * $k),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($x + $w - $r + $c * $r) * $k,
                ($hp - $y) * $k,
                ($x + $w) * $k,
                ($hp - ($y + $r - $c * $r)) * $k,
                ($x + $w) * $k,
                ($hp - ($y + $r)) * $k,
            ),
        );
        $this->_out(
            sprintf("%.2F %.2F l", ($x + $w) * $k, ($hp - ($y + $h - $r)) * $k),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($x + $w) * $k,
                ($hp - ($y + $h - $r + $c * $r)) * $k,
                ($x + $w - $r + $c * $r) * $k,
                ($hp - ($y + $h)) * $k,
                ($x + $w - $r) * $k,
                ($hp - ($y + $h)) * $k,
            ),
        );
        $this->_out(
            sprintf("%.2F %.2F l", ($x + $r) * $k, ($hp - ($y + $h)) * $k),
        );
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c",
                ($x + $r - $c * $r) * $k,
                ($hp - ($y + $h)) * $k,
                $x * $k,
                ($hp - ($y + $h - $r + $c * $r)) * $k,
                $x * $k,
                ($hp - ($y + $h - $r)) * $k,
            ),
        );
        $this->_out(sprintf("%.2F %.2F l", $x * $k, ($hp - ($y + $r)) * $k));
        $this->_out(
            sprintf(
                "%.2F %.2F %.2F %.2F %.2F %.2F c %s",
                $x * $k,
                ($hp - ($y + $r - $c * $r)) * $k,
                ($x + $r - $c * $r) * $k,
                ($hp - $y) * $k,
                ($x + $r) * $k,
                ($hp - $y) * $k,
                $op,
            ),
        );
    }

    /* ── page header ── */
    public function Header(): void
    {
        /* navy banner */
        $this->SetFillColor(...self::NAVY);
        $this->Rect(0, 0, 210, 22, "F");
        /* gold rule */
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, 22, 210, 2.5, "F");

        /* icon box */
        $this->SetFillColor(...self::GOLD);
        $this->RoundedRect(8, 3, 16, 16, 2.5, "F");
        /* bus body */
        $this->SetFillColor(...self::NAVY);
        $this->Rect(10, 6, 11, 6.5, "F");
        /* windows */
        $this->SetFillColor(220, 230, 245);
        $this->Rect(10.8, 6.8, 3.5, 3.2, "F");
        $this->Rect(15.2, 6.8, 3.8, 3.2, "F");
        /* wheels */
        $this->SetFillColor(...self::NAVY);
        $this->FilledCircle(12.2, 12.9, 1.4);
        $this->FilledCircle(18.9, 12.9, 1.4);
        $this->SetFillColor(...self::GOLD);
        $this->FilledCircle(12.2, 12.9, 0.6);
        $this->FilledCircle(18.9, 12.9, 0.6);

        /* brand name */
        $this->SetFont("Helvetica", "B", 11.5);
        $this->SetTextColor(...self::WHITE);
        $this->SetXY(27, 4);
        $this->Cell(80, 7, "TransportOps", 0, 0, "L");
        $this->SetFont("Helvetica", "", 6.5);
        $this->SetTextColor(150, 170, 210);
        $this->SetXY(27, 11);
        $this->Cell(80, 5, "ADMIN ANALYTICS SYSTEM", 0, 0, "L");

        /* right: report label */
        $this->SetFont("Helvetica", "B", 9);
        $this->SetTextColor(...self::GOLD);
        $this->SetXY(110, 4.5);
        $this->Cell(90, 6, "Analytics Export Report", 0, 0, "R");
        $this->SetFont("Helvetica", "", 7);
        $this->SetTextColor(150, 170, 210);
        $this->SetXY(110, 11);
        $this->Cell(90, 5, $this->reportDate, 0, 0, "R");
    }

    /* ── page footer ── */
    public function Footer(): void
    {
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, $this->GetPageHeight() - 10, 210, 0.5, "F");
        $this->SetY(-9);
        $this->SetFont("Helvetica", "", 7);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(
            0,
            0,
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
        $x = $this->GetX();
        $y = $this->GetY();
        /* accent bar */
        $this->SetFillColor(...$accent);
        $this->Rect($x, $y, 4, 9, "F");
        /* background */
        $this->SetFillColor(237, 240, 247);
        $this->Rect($x + 4, $y, 186, 9, "F");
        /* title */
        $this->SetFont("Helvetica", "B", 9.5);
        $this->SetTextColor(...self::NAVY);
        $this->SetXY($x + 9, $y + 2);
        $this->Cell(0, 5, $title, 0, 1);
        $this->Ln(3);
    }

    /* ── KPI card  (left accent bar design – no stripe rendering issues) ── */
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
        /* card bg */
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(218, 225, 238);
        $this->RoundedRect($x, $y, $w, $h, 2.5, "FD");
        /* left accent bar */
        $this->SetFillColor(...$accent);
        $this->Rect($x, $y, 4, $h, "F");
        /* round the left corners by overdrawing with card color on leftmost 2px */
        $this->SetFillColor(255, 255, 255);
        $this->Rect($x, $y, 2, $h, "F");
        $this->SetFillColor(...$accent);
        $this->RoundedRect($x, $y, 4, $h, 2, "F");

        /* value */
        $this->SetFont("Helvetica", "B", 16);
        $this->SetTextColor(...self::DARK);
        $this->SetXY($x + 4, $y + 4);
        $this->Cell($w - 4, 9, $val, 0, 0, "C");
        /* label */
        $this->SetFont("Helvetica", "", 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->SetXY($x + 4, $y + 14);
        $this->Cell($w - 4, 5, $label, 0, 0, "C");
        /* sub note */
        $this->SetFont("Helvetica", "B", 6.5);
        $this->SetTextColor(...$accent);
        $this->SetXY($x + 4, $y + 19.5);
        $this->Cell($w - 4, 4, $sub, 0, 0, "C");
    }

    /* ── stat pill ── */
    public function StatPill(
        float $x,
        float $y,
        float $w,
        float $h,
        string $val,
        string $label,
    ): void {
        $this->SetFillColor(246, 248, 252);
        $this->SetDrawColor(215, 222, 235);
        $this->RoundedRect($x, $y, $w, $h, 2.5, "FD");
        $this->SetFont("Helvetica", "B", 13);
        $this->SetTextColor(...self::NAVY);
        $this->SetXY($x, $y + 2);
        $this->Cell($w, 7, $val, 0, 0, "C");
        $this->SetFont("Helvetica", "", 7);
        $this->SetTextColor(...self::MUTED);
        $this->SetXY($x, $y + 9.5);
        $this->Cell($w, 4, $label, 0, 0, "C");
    }

    /* ── horizontal bar ── */
    public function HBar(
        float $x,
        float $y,
        float $maxW,
        float $pct,
        array $color,
    ): void {
        /* track */
        $this->SetFillColor(225, 230, 242);
        $this->RoundedRect($x, $y, $maxW, 3.5, 1, "F");
        /* fill */
        if ($pct > 0) {
            $this->SetFillColor(...$color);
            $this->RoundedRect(
                $x,
                $y,
                max(2.5, ($maxW * $pct) / 100),
                3.5,
                1,
                "F",
            );
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

        /* chart area */
        $this->SetFillColor(247, 249, 253);
        $this->SetDrawColor(220, 228, 240);
        $this->RoundedRect($x, $y, $w, $h, 2, "FD");

        $maxV = max(1, max(array_column($data, "count")));
        $padL = 5;
        $padR = 4;
        $padT = 7;
        $padB = 9;
        $areaW = $w - $padL - $padR;
        $areaH = $h - $padT - $padB;
        $slot = $areaW / $n;
        $bW = max(3, $slot * 0.65);

        /* light grid lines */
        $this->SetDrawColor(220, 228, 240);
        $this->SetLineWidth(0.15);
        for ($g = 1; $g <= 3; $g++) {
            $gy = $y + $padT + $areaH - ($areaH * $g) / 4;
            $this->Line($x + $padL, $gy, $x + $padL + $areaW, $gy);
        }
        $this->SetLineWidth(0.2);

        for ($i = 0; $i < $n; $i++) {
            $v = (int) $data[$i]["count"];
            $bH = $v > 0 ? max(2, ($v / $maxV) * $areaH) : 0;
            $bx = $x + $padL + $i * $slot + ($slot - $bW) / 2;
            $by = $y + $padT + $areaH - $bH;

            if ($bH > 0) {
                /* bar */
                $this->SetFillColor(...$color);
                $this->RoundedRect($bx, $by, $bW, $bH, min(1.2, $bW / 4), "F");
                /* subtle top highlight */
                $hi = [
                    min(255, $color[0] + 45),
                    min(255, $color[1] + 45),
                    min(255, $color[2] + 55),
                ];
                $this->SetFillColor(...$hi);
                $hiH = min(2.5, $bH * 0.35);
                $this->RoundedRect($bx, $by, $bW, $hiH, min(1.2, $bW / 4), "F");
                /* value above bar */
                $this->SetFont("Helvetica", "B", 5.5);
                $this->SetTextColor(...self::NAVY);
                $this->SetXY($bx - 1, max($y + 1, $by - 5));
                $this->Cell($bW + 2, 4.5, (string) $v, 0, 0, "C");
            }

            /* date label */
            $this->SetFont("Helvetica", "", 5);
            $this->SetTextColor(...self::MUTED);
            $this->SetXY($bx - 2, $y + $padT + $areaH + 1.5);
            $this->Cell($bW + 4, 4, $data[$i]["date"], 0, 0, "C");
        }

        /* baseline */
        $this->SetDrawColor(...$color);
        $this->SetLineWidth(0.4);
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
        $this->Rect($x0, $y0, $tw, 8, "F");
        $this->SetFont("Helvetica", "B", 7.5);
        $this->SetTextColor(...self::WHITE);
        foreach ($cols as $c) {
            $this->Cell($c["w"], 8, $c["label"], 0, 0, $c["align"] ?? "L");
        }
        $this->Ln();
    }

    /* ── table row ── */
    public function TRow(array $cells, int $idx): void
    {
        $x0 = $this->GetX();
        $y0 = $this->GetY();
        $tw = array_sum(array_column($cells, "w"));
        $rowH = 7.5;
        /* alternating bg */
        if ($idx % 2 === 0) {
            $this->SetFillColor(246, 248, 252);
            $this->Rect($x0, $y0, $tw, $rowH, "F");
        }
        /* bottom divider */
        $this->SetDrawColor(220, 228, 240);
        $this->Line($x0, $y0 + $rowH, $x0 + $tw, $y0 + $rowH);
        /* cells */
        foreach ($cells as $c) {
            $this->SetFont("Helvetica", $c["style"] ?? "", 7.5);
            $col = $c["color"] ?? self::DARK;
            $this->SetTextColor($col[0], $col[1], $col[2]);
            $this->Cell($c["w"], $rowH, $c["v"], 0, 0, $c["align"] ?? "L");
        }
        $this->Ln();
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
$pdf->adminName = $_SESSION["user_name"] ?? "Admin";
$pdf->AliasNbPages();
$pdf->SetMargins(10, 28, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

/* usable area constants */
$pW = 190; /* page usable width  (210 - 10 - 10) */
$sX = 10; /* left margin */

/* ─────────────────────────────────────────────────
   HERO STRIP
   ───────────────────────────────────────────────── */
$hy = $pdf->GetY();
$pdf->SetFillColor(237, 240, 247);
$pdf->Rect($sX, $hy, $pW, 24, "F");
$pdf->SetFillColor(33, 51, 92);
$pdf->Rect($sX, $hy, 4.5, 24, "F");
$pdf->SetFillColor(251, 192, 97);
$pdf->Rect($sX + 4.5, $hy + 22.5, $pW - 4.5, 1.2, "F");

$pdf->SetFont("Helvetica", "B", 13);
$pdf->SetTextColor(33, 51, 92);
$pdf->SetXY($sX + 9, $hy + 2.5);
$pdf->Cell(132, 7, "Dashboard Analytics Report", 0, 0, "L");

$pdf->SetFont("Helvetica", "", 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY($sX + 9, $hy + 10);
$pdf->Cell(
    132,
    5,
    "Exported by " . $pdf->adminName . "   |   " . date("D, M j, Y   H:i"),
    0,
    0,
    "L",
);

$pdf->SetFont("Helvetica", "B", 7.5);
$pdf->SetTextColor(33, 51, 92);
$pdf->SetXY($sX + 9, $hy + 16);
$pdf->Cell(17, 4.5, "Period:", 0, 0, "L");
$pdf->SetFont("Helvetica", "", 7.5);
$pdf->SetTextColor(91, 123, 153);
$pdf->SetXY($sX + 26, $hy + 16);
$pdf->Cell(115, 4.5, $range_label, 0, 0, "L");

/* date badge – right side */
$pdf->SetFillColor(33, 51, 92);
$pdf->RoundedRect(151, $hy + 3, 48, 17, 2.5, "F");
$pdf->SetFillColor(251, 192, 97);
$pdf->Rect(151, $hy + 13, 48, 0.8, "F");
$pdf->SetFont("Helvetica", "B", 7);
$pdf->SetTextColor(251, 192, 97);
$pdf->SetXY(151, $hy + 5);
$pdf->Cell(48, 5, strtoupper(date("D, M j Y")), 0, 0, "C");
$pdf->SetFont("Helvetica", "", 6);
$pdf->SetTextColor(150, 170, 210);
$pdf->SetXY(151, $hy + 14.5);
$pdf->Cell(48, 4, "EXPORT DATE", 0, 0, "C");

$pdf->SetY($hy + 30);

/* ─────────────────────────────────────────────────
   SECTION 1 – KPI CARDS
   4 cards × 44mm + 3 gaps × 2.67mm = 182mm  (fits in 190mm)
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("System Overview", [33, 51, 92]);

$cW = 44;
$cH = 28;
$cGap = 2.67;
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
        "total registered",
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
$pdf->SetY($cY + $cH + 5);

/* derived stats pills
 3 × 60mm + 2 × 5mm gap = 190mm  ✓ */
$rpu = round($total_reports / max($total_users, 1), 1);
$rpr = round($total_reports / max($total_routes, 1), 1);
$sy = $pdf->GetY();
$pW3 = 60;
$pGap = 5;
$pH = 14;
$pdf->StatPill($sX, $sy, $pW3, $pH, $dRate . "%", "Delay Rate");
$pdf->StatPill(
    $sX + $pW3 + $pGap,
    $sy,
    $pW3,
    $pH,
    (string) $rpu,
    "Reports / User",
);
$pdf->StatPill(
    $sX + ($pW3 + $pGap) * 2,
    $sy,
    $pW3,
    $pH,
    (string) $rpr,
    "Reports / Route",
);
$pdf->SetY($sy + $pH + 6);

/* ─────────────────────────────────────────────────
   SECTION 2 – REPORTS OVER TIME  (bar chart)
   chart width = $pW = 190mm
   ───────────────────────────────────────────────── */
$pdf->SectionHeading(
    "Reports Over Time  (" . $range_chart_label . "  |  " . $range_label . ")",
    [37, 99, 235],
);
$pdf->BarChart($sX, $pdf->GetY(), $pW, 50, $reports_over_time, [37, 99, 235]);
$pdf->SetY($pdf->GetY() + 56);

/* ─────────────────────────────────────────────────
   SECTION 3 – DELAY TRENDS
   Columns: Reason(70) + Count(15) + Share(20) + Bar(85) = 190mm ✓
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("Delay Trends Breakdown", [220, 38, 38]);

if (empty($delay_trends)) {
    $pdf->SetFont("Helvetica", "", 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "No delay data available.", 0, 1);
} else {
    $dLW = 70;
    $dCW = 15;
    $dSW = 20;
    $dBW = 85; /* total = 190 */
    $maxCnt = max(1, max(array_column($delay_trends, "count")));

    $pdf->THead([
        ["label" => "Delay Reason", "w" => $dLW, "align" => "L"],
        ["label" => "Count", "w" => $dCW, "align" => "C"],
        ["label" => "Share", "w" => $dSW, "align" => "C"],
        ["label" => "Visual", "w" => $dBW, "align" => "L"],
    ]);

    foreach ($delay_trends as $idx => $row) {
        $reason = clip($row["delay_reason"] ?? "Unspecified", 32);
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

        /* reason */
        $pdf->SetFont("Helvetica", "", 7.5);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetXY($rx, $ry + 0.5);
        $pdf->Cell($dLW, $rowH, $reason, 0, 0, "L");

        /* count */
        $pdf->SetFont("Helvetica", "B", 7.5);
        $pdf->SetTextColor($barColor[0], $barColor[1], $barColor[2]);
        $pdf->SetXY($rx + $dLW, $ry + 0.5);
        $pdf->Cell($dCW, $rowH, (string) $cnt, 0, 0, "C");

        /* share */
        $pdf->SetFont("Helvetica", "", 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY($rx + $dLW + $dCW, $ry + 0.5);
        $pdf->Cell($dSW, $rowH, $sharePct . "%", 0, 0, "C");

        /* bar */
        $barPct = round(($cnt / $maxCnt) * 100);
        $pdf->HBar(
            $rx + $dLW + $dCW + $dSW + 2,
            $ry + 2.3,
            $dBW - 4,
            $barPct,
            $barColor,
        );

        $pdf->SetY($ry + $rowH);
    }
}
$pdf->Ln(5);

/* ─────────────────────────────────────────────────
   SECTION 4 – PEAK HOUR ANALYSIS
   Columns: Hour(35) + Reps(13) + RepBar(47) + Dels(13) + DelBar(47) + Hot(35) = 190mm ✓
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("Peak Hour Analysis  (" . $range_label . ")", [
    124,
    58,
    237,
]);

if (empty($hourly_trends)) {
    $pdf->SetFont("Helvetica", "", 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "Not enough data to calculate hourly trends.", 0, 1);
} else {
    $hHour = 35;
    $hRN = 13;
    $hRB = 47;
    $hDN = 13;
    $hDB = 47;
    $hHot = 35;
    /* verify: 35+13+47+13+47+35 = 190 */
    $maxRep = max(1, max(array_column($hourly_trends, "total_reports")));
    $maxDel = max(1, max(array_column($hourly_trends, "total_delays")));

    $pdf->THead([
        ["label" => "Hour Range", "w" => $hHour, "align" => "L"],
        ["label" => "Reports", "w" => $hRN, "align" => "C"],
        ["label" => "Volume", "w" => $hRB, "align" => "L"],
        ["label" => "Delays", "w" => $hDN, "align" => "C"],
        ["label" => "Delay Bar", "w" => $hDB, "align" => "L"],
        ["label" => "Status", "w" => $hHot, "align" => "C"],
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
        $rx = $pdf->GetX();
        $ry = $pdf->GetY();
        $tw = $hHour + $hRN + $hRB + $hDN + $hDB + $hHot;

        /* row bg */
        if ($isHot) {
            $pdf->SetFillColor(255, 243, 243);
            $pdf->Rect($rx, $ry, $tw, $rowH, "F");
        } elseif ($idx % 2 === 0) {
            $pdf->SetFillColor(246, 248, 252);
            $pdf->Rect($rx, $ry, $tw, $rowH, "F");
        }
        $pdf->SetDrawColor(220, 228, 240);
        $pdf->Line($rx, $ry + $rowH, $rx + $tw, $ry + $rowH);

        /* hour label */
        $pdf->SetFont("Helvetica", $isHot ? "B" : "", 7.5);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetXY($rx, $ry + 0.5);
        $pdf->Cell($hHour, $rowH, fmtHour($hour), 0, 0, "L");

        /* report count */
        $pdf->SetFont("Helvetica", "B", 7.5);
        $pdf->SetTextColor(37, 99, 235);
        $pdf->SetXY($rx + $hHour, $ry + 0.5);
        $pdf->Cell($hRN, $rowH, (string) $rep, 0, 0, "C");

        /* report bar */
        $pdf->HBar($rx + $hHour + $hRN + 1, $ry + 2.3, $hRB - 2, $repPct, [
            37,
            99,
            235,
        ]);

        /* delay count */
        $pdf->SetFont("Helvetica", "B", 7.5);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetXY($rx + $hHour + $hRN + $hRB, $ry + 0.5);
        $pdf->Cell($hDN, $rowH, (string) $del, 0, 0, "C");

        /* delay bar */
        $pdf->HBar(
            $rx + $hHour + $hRN + $hRB + $hDN + 1,
            $ry + 2.3,
            $hDB - 2,
            $delPct,
            [220, 38, 38],
        );

        /* hotspot badge */
        $bx = $rx + $hHour + $hRN + $hRB + $hDN + $hDB;
        if ($isHot) {
            $pdf->SetFillColor(220, 38, 38);
            $pdf->RoundedRect($bx + 4, $ry + 1.8, 27, 5, 1.5, "F");
            $pdf->SetFont("Helvetica", "B", 6);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($bx + 4, $ry + 3);
            $pdf->Cell(27, 3.5, "HOTSPOT", 0, 0, "C");
        } else {
            $pdf->SetFont("Helvetica", "", 7.5);
            $pdf->SetTextColor(200, 210, 225);
            $pdf->SetXY($bx, $ry + 0.5);
            $pdf->Cell($hHot, $rowH, "-", 0, 0, "C");
        }

        $pdf->SetY($ry + $rowH);
    }
}
$pdf->Ln(5);

/* ─────────────────────────────────────────────────
   SECTION 5 – RECENT REPORTS
   Columns: DateTime(28) + User(40) + Role(20) + Route(38) + Crowd(22) + Delay(42) = 190mm ✓
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("Recent Reports  (Last 12 in Period)", [91, 123, 153]);

if (empty($recent_reports)) {
    $pdf->SetFont("Helvetica", "", 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 8, "No reports found.", 0, 1);
} else {
    $pdf->THead([
        ["label" => "Date & Time", "w" => 28, "align" => "L"],
        ["label" => "User", "w" => 40, "align" => "L"],
        ["label" => "Role", "w" => 20, "align" => "L"],
        ["label" => "Route", "w" => 38, "align" => "L"],
        ["label" => "Crowd", "w" => 22, "align" => "C"],
        ["label" => "Delay Reason", "w" => 42, "align" => "L"],
    ]);

    foreach ($recent_reports as $idx => $r) {
        $ts = strtotime($r["timestamp"] ?? "now");
        $dt = date("M d", $ts) . " " . date("H:i", $ts);
        $user = clip($r["user_name"] ?? "N/A", 20);
        $role = $r["user_role"] ?? "";
        $route = clip($r["route_name"] ?? "N/A", 20);
        $crowd = $r["crowd_level"] ?? "-";
        $delay = clip($r["delay_reason"] ?? "-", 24);

        $pdf->TRow(
            [
                ["v" => $dt, "w" => 28, "style" => "", "color" => [30, 41, 59]],
                [
                    "v" => $user,
                    "w" => 40,
                    "style" => "B",
                    "color" => [30, 41, 59],
                ],
                [
                    "v" => $role,
                    "w" => 20,
                    "style" => "",
                    "color" => $pdf->roleColor($role),
                ],
                [
                    "v" => $route,
                    "w" => 38,
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
                    "w" => 42,
                    "style" => "",
                    "color" => [100, 116, 139],
                ],
            ],
            $idx,
        );
    }
}
$pdf->Ln(5);

/* ─────────────────────────────────────────────────
   SECTION 6 – USER BREAKDOWN
   ───────────────────────────────────────────────── */
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

$rY = $pdf->GetY();
$roleMap = [
    "Admin" => [220, 38, 38],
    "Driver" => [37, 99, 235],
    "Commuter" => [100, 116, 139],
];
$pillW = 58;
$pillH = 16;
$pillGap = 4;

foreach ($roleRows as $ri => $rr) {
    $rc = $roleMap[$rr["role"]] ?? [91, 123, 153];
    $px = $sX + $ri * ($pillW + $pillGap);

    /* pill */
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(215, 222, 235);
    $pdf->RoundedRect($px, $rY, $pillW, $pillH, 2.5, "FD");
    /* left accent */
    $pdf->SetFillColor($rc[0], $rc[1], $rc[2]);
    $pdf->RoundedRect($px, $rY, 4, $pillH, 2, "F");
    $pdf->Rect($px + 2, $rY, 2, $pillH, "F");

    /* count */
    $pdf->SetFont("Helvetica", "B", 12);
    $pdf->SetTextColor($rc[0], $rc[1], $rc[2]);
    $pdf->SetXY($px + 6, $rY + 2);
    $pdf->Cell($pillW - 6, 7, number_format((int) $rr["cnt"]), 0, 0, "C");

    /* role label */
    $pdf->SetFont("Helvetica", "", 7);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY($px + 6, $rY + 9.5);
    $pdf->Cell($pillW - 6, 4.5, $rr["role"] . "s", 0, 0, "C");
}

/* total label beside pills */
$afterX = $sX + count($roleRows) * ($pillW + $pillGap) + 2;
$pdf->SetFont("Helvetica", "B", 8);
$pdf->SetTextColor(33, 51, 92);
$pdf->SetXY($afterX, $rY + 5.5);
$pdf->Cell(
    50,
    5,
    "Total: " . number_format($total_users) . " users",
    0,
    0,
    "L",
);

$pdf->SetY($rY + $pillH + 6);

/* ─────────────────────────────────────────────────
   SECTION 7 – SUMMARY & NOTES
   ───────────────────────────────────────────────── */
$pdf->SectionHeading("Summary & Notes", [33, 51, 92]);

$smY = $pdf->GetY();
$pdf->SetFillColor(237, 240, 247);
$pdf->SetDrawColor(215, 222, 235);
$pdf->RoundedRect($sX, $smY, $pW, 30, 3, "FD");
/* navy left bar */
$pdf->SetFillColor(33, 51, 92);
$pdf->Rect($sX, $smY, 4, 30, "F");

$notes = [
    "Delay rate: " .
    $dRate .
    "%  (" .
    number_format($active_delays) .
    " of " .
    number_format($total_reports) .
    " reports have a delay reason).",
    "Peak delay hour: " .
    ($top_delay_hour !== null
        ? fmtHour($top_delay_hour)
        : "Insufficient data") .
    ".",
    "Avg " . $rpu . " report(s) per user  |  " . $rpr . " report(s) per route.",
    "This report was generated automatically by TransportOps Admin Analytics.",
];

$pdf->SetXY($sX + 8, $smY + 3);
foreach ($notes as $note) {
    /* gold bullet */
    $pdf->SetFillColor(251, 192, 97);
    $pdf->FilledCircle($sX + 10.5, $pdf->GetY() + 3, 1.3);
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
