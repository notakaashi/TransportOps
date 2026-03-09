<?php
/**
 * Admin Dashboard
 * Displays fleet overview, statistics, and management tools
 * Restricted to Admin role only
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin_login.php");
    exit();
}
if ($_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit();
}

checkAdminActive();

$total_reports = 0;
$active_delays = 0;
$total_users = 0;
$total_routes = 0;
$recent_reports = [];
$users_data = [];
$delay_trends = [];
$peak_hours = [];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_reports = isset($result["count"]) ? (int) $result["count"] : 0;

    $stmt = $pdo->query(
        "SELECT COUNT(*) as count FROM reports WHERE delay_reason IS NOT NULL AND delay_reason != ''",
    );
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_delays = isset($result["count"]) ? (int) $result["count"] : 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = isset($result["count"]) ? (int) $result["count"] : 0;

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM route_definitions");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_routes = isset($result["count"]) ? (int) $result["count"] : 0;
    } catch (PDOException $e) {
        $total_routes = 0;
    }

    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               u.name as user_name, u.role as user_role,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        ORDER BY r.timestamp DESC
        LIMIT 10
    ");
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC",
    );
    $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch real reports over time data for the last 7 days
    $reports_over_time = [];
    for ($i = 7; $i >= 0; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM reports
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)["count"];
        $reports_over_time[] = $count;
    }

    // Calculate week-over-week changes
    $current_week_reports = array_sum(array_slice($reports_over_time, 0, 7));
    $previous_week_reports = 0;
    for ($i = 14; $i >= 8; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM reports
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $previous_week_reports += $stmt->fetch(PDO::FETCH_ASSOC)["count"];
    }

    $reports_change =
        $previous_week_reports > 0
            ? round(
                (($current_week_reports - $previous_week_reports) /
                    $previous_week_reports) *
                    100,
                1,
            )
            : 0;

    // Calculate delay change
    $current_week_delays = 0;
    for ($i = 7; $i >= 0; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM reports
            WHERE DATE(timestamp) = ? AND delay_reason IS NOT NULL AND delay_reason != ''
        ");
        $stmt->execute([$date]);
        $current_week_delays += $stmt->fetch(PDO::FETCH_ASSOC)["count"];
    }

    $previous_week_delays = 0;
    for ($i = 14; $i >= 8; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM reports
            WHERE DATE(timestamp) = ? AND delay_reason IS NOT NULL AND delay_reason != ''
        ");
        $stmt->execute([$date]);
        $previous_week_delays += $stmt->fetch(PDO::FETCH_ASSOC)["count"];
    }

    $delays_change =
        $previous_week_delays > 0
            ? round(
                (($current_week_delays - $previous_week_delays) /
                    $previous_week_delays) *
                    100,
                1,
            )
            : 0;

    // Calculate user change
    $current_week_users = 0;
    for ($i = 7; $i >= 0; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM users
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $current_week_users += $stmt->fetch(PDO::FETCH_ASSOC)["count"];
    }

    $previous_week_users = 0;
    for ($i = 14; $i >= 8; $i--) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM users
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $previous_week_users += $stmt->fetch(PDO::FETCH_ASSOC)["count"];
    }

    $users_change =
        $previous_week_users > 0
            ? round(
                (($current_week_users - $previous_week_users) /
                    $previous_week_users) *
                    100,
                1,
            )
            : 0;

    $stmt = $pdo->query("
        SELECT delay_reason, COUNT(*) as count
        FROM reports
        WHERE delay_reason IS NOT NULL AND delay_reason != ''
          AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY delay_reason
        ORDER BY count DESC
        LIMIT 5
    ");
    $delay_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT HOUR(timestamp) as hour,
               SUM(CASE WHEN crowd_level = 'Heavy' THEN 1 ELSE 0 END) as heavy_count,
               COUNT(*) as total_reports
        FROM reports
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(timestamp)
        ORDER BY hour
    ");
    $peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

function getStatusBadge($status)
{
    switch ($status) {
        case "Light":
            return "badge-light";
        case "Moderate":
            return "badge-moderate";
        case "Heavy":
            return "badge-heavy";
        default:
            return "badge-unknown";
    }
}
function getAvatarClass($role)
{
    switch ($role) {
        case "Admin":
            return "avatar-admin";
        case "Driver":
            return "avatar-driver";
        default:
            return "avatar-commuter";
    }
}
function getRoleBadge($role)
{
    switch ($role) {
        case "Admin":
            return "badge-role-admin";
        case "Driver":
            return "badge-role-driver";
        default:
            return "badge-role-commuter";
    }
}
function getInitials($name)
{
    $parts = explode(" ", trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include "admin_layout_head.php"; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: #edf0f7;
            min-height: 100vh;
            color: #1e293b;
        }

        /* ── Animated Background ────────────────────────── */
        .bg-layer {
            position: fixed; inset: 0; z-index: 0;
            background: linear-gradient(145deg, #e8edf8 0%, #f0f4ff 35%, #edf3f0 65%, #f5f0ea 100%);
            overflow: hidden; pointer-events: none;
        }
        .bg-layer::before {
            content: '';
            position: absolute;
            width: 800px; height: 800px; border-radius: 50%;
            background: radial-gradient(circle, rgba(34,51,92,0.08) 0%, transparent 70%);
            top: -300px; left: -200px;
            animation: bgFloat1 20s ease-in-out infinite alternate;
        }
        .bg-layer::after {
            content: '';
            position: absolute;
            width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(91,123,153,0.07) 0%, transparent 70%);
            bottom: -200px; right: -100px;
            animation: bgFloat2 25s ease-in-out infinite alternate;
        }
        @keyframes bgFloat1 { from{transform:translate(0,0) scale(1)} to{transform:translate(80px,50px) scale(1.08)} }
        @keyframes bgFloat2 { from{transform:translate(0,0) scale(1)} to{transform:translate(-60px,-40px) scale(1.06)} }

        /* ── App Layout ─────────────────────────────────── */
        .app-layout { position: relative; z-index: 1; display: flex; min-height: 100vh; }

        /* ── Sidebar ────────────────────────────────────── */
        .sidebar {
            width: 256px; flex-shrink: 0;
            background: linear-gradient(180deg, #0f1c36 0%, #19284a 45%, #1d3055 100%);
            position: fixed; top: 0; left: 0; bottom: 0;
            display: flex; flex-direction: column;
            z-index: 40;
            box-shadow: 4px 0 30px rgba(0,0,0,0.25);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
            overflow: hidden;
        }
        .sidebar::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 220px;
            background: radial-gradient(ellipse at 50% 0%, rgba(251,192,97,0.09) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Brand */
        .sidebar-brand {
            padding: 1.5rem 1.375rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex; align-items: center; gap: 0.75rem;
            position: relative; z-index: 1; flex-shrink: 0;
        }
        .brand-icon {
            width: 2.5rem; height: 2.5rem;
            background: linear-gradient(135deg, #FBC061 0%, #e09820 100%);
            border-radius: 0.625rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(251,192,97,0.4);
        }
        .brand-name { font-size: 0.975rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; line-height: 1.1; }
        .brand-tag  { font-size: 0.62rem; color: rgba(255,255,255,0.38); font-weight: 500; letter-spacing: 0.07em; text-transform: uppercase; margin-top: 2px; }

        /* Nav */
        .sidebar-nav {
            flex: 1; padding: 0.875rem 0.875rem 0.5rem;
            overflow-y: auto; position: relative; z-index: 1;
        }
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 999px; }

        .nav-section-label {
            font-size: 0.6rem; font-weight: 700; letter-spacing: 0.13em;
            text-transform: uppercase; color: rgba(255,255,255,0.25);
            padding: 0.75rem 0.625rem 0.3rem;
        }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.6rem 0.75rem; border-radius: 0.6rem;
            font-size: 0.845rem; font-weight: 500;
            color: rgba(255,255,255,0.58);
            text-decoration: none;
            transition: all 0.18s ease;
            margin-bottom: 2px; border: 1px solid transparent;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.9);
            transform: translateX(3px);
        }
        .nav-link.active {
            background: linear-gradient(135deg, rgba(251,192,97,0.18) 0%, rgba(91,123,153,0.22) 100%);
            color: #fbd07c;
            border-color: rgba(251,192,97,0.22);
            box-shadow: 0 2px 10px rgba(251,192,97,0.1);
        }
        .nav-link.active .nav-ico { color: #FBC061; }
        .nav-ico { width: 1.1rem; height: 1.1rem; flex-shrink: 0; transition: color 0.18s; opacity: 0.85; }
        .nav-link:hover .nav-ico { opacity: 1; }

        /* Footer */
        .sidebar-footer {
            padding: 0.875rem; border-top: 1px solid rgba(255,255,255,0.07);
            position: relative; z-index: 1; flex-shrink: 0;
        }
        .user-info-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 0.75rem;
            padding: 0.65rem 0.75rem;
            display: flex; align-items: center; gap: 0.625rem;
            margin-bottom: 0.625rem;
        }
        .user-ava {
            width: 2.2rem; height: 2.2rem; border-radius: 50%;
            background: linear-gradient(135deg, #5B7B99 0%, #22335C 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 800; color: #fff;
            flex-shrink: 0; letter-spacing: 0.02em;
        }
        .user-name  { font-size: 0.8rem; font-weight: 600; color: #fff; line-height: 1.2; }
        .user-role-label { font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 1px; }
        .logout-link {
            display: flex; align-items: center; justify-content: center; gap: 0.45rem;
            width: 100%; padding: 0.55rem;
            border-radius: 0.6rem;
            font-size: 0.8rem; font-weight: 600;
            color: rgba(255,110,110,0.82);
            background: rgba(220,38,38,0.08);
            border: 1px solid rgba(220,38,38,0.16);
            text-decoration: none;
            transition: all 0.18s ease;
        }
        .logout-link:hover { background: rgba(220,38,38,0.16); color: #ff7070; border-color: rgba(220,38,38,0.32); }

        /* Mobile */
        .hamburger-btn {
            display: none;
            position: fixed; top: 1rem; left: 1rem; z-index: 50;
            background: #19284a; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0.5rem; padding: 0.5rem; cursor: pointer; color: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            align-items: center; justify-content: center;
        }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(5,15,35,0.55); z-index: 35; backdrop-filter: blur(3px);
        }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .hamburger-btn { display: flex; }
            .main-area { margin-left: 0 !important; }
        }

        /* ── Main Area ──────────────────────────────────── */
        .main-area { margin-left: 256px; flex: 1; min-width: 0; padding: 2rem 2rem 3rem; }

        /* ── Page Header ────────────────────────────────── */
        .page-header {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            flex-wrap: wrap; gap: 1rem;
        }
        .page-title {
            font-size: 1.7rem; font-weight: 800;
            background: linear-gradient(135deg, #1a2a4c 0%, #4a6a99 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            letter-spacing: -0.025em; line-height: 1.15;
        }
        .page-subtitle { font-size: 0.845rem; color: #7a8aaa; margin-top: 0.2rem; font-weight: 400; }
        .date-chip {
            display: flex; align-items: center; gap: 0.4rem;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(34,51,92,0.11);
            border-radius: 999px; padding: 0.4rem 1rem;
            font-size: 0.78rem; font-weight: 500; color: #334155;
            box-shadow: 0 2px 8px rgba(34,51,92,0.07); white-space: nowrap;
        }

        /* ── Stat Cards ─────────────────────────────────── */
        .stats-row {
            display: grid; grid-template-columns: repeat(4,1fr);
            gap: 1.125rem; margin-bottom: 1.625rem;
        }
        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 600px)  { .stats-row { grid-template-columns: 1fr; } }

        .stat-card {
            border-radius: 1.125rem; padding: 1.375rem 1.5rem;
            color: #fff; position: relative; overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            cursor: default;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .sc-deco1 {
            position: absolute; top: -28px; right: -18px;
            width: 110px; height: 110px; border-radius: 50%;
            background: rgba(255,255,255,0.10); pointer-events: none;
        }
        .sc-deco2 {
            position: absolute; bottom: -35px; right: 25px;
            width: 75px; height: 75px; border-radius: 50%;
            background: rgba(255,255,255,0.06); pointer-events: none;
        }
        .sc-green  { background: linear-gradient(135deg,#14532d 0%,#16a34a 100%); box-shadow: 0 8px 28px rgba(22,163,74,0.32); }
        .sc-red    { background: linear-gradient(135deg,#7f1d1d 0%,#dc2626 100%); box-shadow: 0 8px 28px rgba(220,38,38,0.32); }
        .sc-purple { background: linear-gradient(135deg,#3b0764 0%,#7c3aed 100%); box-shadow: 0 8px 28px rgba(124,58,237,0.32); }
        .sc-navy   { background: linear-gradient(135deg,#0f1f3d 0%,#2563eb 100%); box-shadow: 0 8px 28px rgba(37,99,235,0.28); }
        .sc-top    { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.875rem; }
        .sc-icon   {
            width: 2.625rem; height: 2.625rem;
            background: rgba(255,255,255,0.16); border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sc-label { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: rgba(255,255,255,0.65); margin-bottom: 0.3rem; }
        .sc-value { font-size: 2.1rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
        .sc-sub   { font-size: 0.72rem; color: rgba(255,255,255,0.55); margin-top: 0.35rem; }

        /* ── Content Cards ──────────────────────────────── */
        .card {
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.96);
            border-radius: 1.125rem;
            box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
            overflow: hidden; margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1rem 1.375rem;
            border-bottom: 1px solid rgba(34,51,92,0.07);
            background: linear-gradient(135deg, rgba(34,51,92,0.03) 0%, rgba(255,255,255,0.5) 100%);
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.75rem; flex-wrap: wrap;
        }
        .card-title-wrap { display: flex; align-items: center; gap: 0.6rem; }
        .card-icon {
            width: 1.75rem; height: 1.75rem; border-radius: 0.45rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ci-blue   { background: #dbeafe; color: #1d4ed8; }
        .ci-red    { background: #fee2e2; color: #b91c1c; }
        .ci-green  { background: #dcfce7; color: #166534; }
        .ci-orange { background: #ffedd5; color: #c2410c; }
        .ci-purple { background: #f3e8ff; color: #7e22ce; }
        .card-title { font-size: 0.925rem; font-weight: 700; color: #1e293b; }
        .card-body  { padding: 1.25rem 1.375rem; }
        .chart-wrap { position: relative; height: 300px; width: 100%; }

        /* ── Two-col grid ───────────────────────────────── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

        /* ── Tables ─────────────────────────────────────── */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead tr {
            background: linear-gradient(90deg, rgba(34,51,92,0.04) 0%, rgba(91,123,153,0.025) 100%);
        }
        .data-table thead th {
            padding: 0.7rem 1.25rem; text-align: left;
            font-size: 0.67rem; font-weight: 700; letter-spacing: 0.09em;
            text-transform: uppercase; color: #64748b; white-space: nowrap;
        }
        .data-table tbody tr { border-top: 1px solid rgba(34,51,92,0.055); transition: background 0.13s ease; }
        .data-table tbody tr:first-child { border-top: none; }
        .data-table tbody tr:hover { background: rgba(34,51,92,0.033); }
        .data-table td { padding: 0.825rem 1.25rem; vertical-align: middle; font-size: 0.855rem; color: #334155; }
        .empty-row td { padding: 2.5rem; text-align: center; color: #94a3b8; font-size: 0.855rem; }

        /* ── Badges ─────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 0.22rem 0.65rem; border-radius: 999px;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.025em;
            white-space: nowrap; border: 1px solid transparent;
        }
        .badge-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .badge-light    { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .badge-light    .badge-dot { background: #22c55e; }
        .badge-moderate { background: #fef9c3; color: #92400e; border-color: #fde68a; }
        .badge-moderate .badge-dot { background: #f59e0b; }
        .badge-heavy    { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .badge-heavy    .badge-dot { background: #ef4444; }
        .badge-unknown  { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .badge-role-admin    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .badge-role-driver   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .badge-role-commuter { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        .badge-new { background: #dc2626; color: #fff; border-color: #dc2626; animation: pulseBadge 1.8s ease-out infinite; }
        @keyframes pulseBadge {
            0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.45); }
            50%      { box-shadow: 0 0 0 7px rgba(220,38,38,0); }
        }

        /* ── Avatars ────────────────────────────────────── */
        .avatar {
            width: 2rem; height: 2rem; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; font-weight: 800; color: #fff;
            flex-shrink: 0; letter-spacing: 0.02em;
        }
        .avatar-admin    { background: linear-gradient(135deg,#ef4444,#b91c1c); }
        .avatar-driver   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
        .avatar-commuter { background: linear-gradient(135deg,#64748b,#334155); }

        /* ── Progress Bars ──────────────────────────────── */
        .bar-track { width: 100%; height: 7px; background: rgba(34,51,92,0.08); border-radius: 999px; overflow: hidden; }
        .bar-fill  { height: 100%; border-radius: 999px; transition: width 0.9s cubic-bezier(.4,0,.2,1); }
        .bar-red   { background: linear-gradient(90deg,#f87171,#dc2626); }
        .bar-orange{ background: linear-gradient(90deg,#fb923c,#ea580c); }

        /* ── Buttons ────────────────────────────────────── */
        .btn-primary {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.45rem 1.1rem;
            background: linear-gradient(135deg, #22335C 0%, #2e4a80 100%);
            color: #fff; border-radius: 0.5rem;
            font-size: 0.78rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
            transition: all 0.18s ease;
            box-shadow: 0 2px 10px rgba(34,51,92,0.25);
        }
        .btn-primary:hover { box-shadow: 0 4px 16px rgba(34,51,92,0.38); transform: translateY(-1px); }
        .btn-link {
            display: inline-flex; align-items: center; gap: 0.35rem;
            font-size: 0.78rem; font-weight: 600; color: #5B7B99;
            text-decoration: none; transition: color 0.15s;
        }
        .btn-link:hover { color: #22335C; }

        /* ── Modal ──────────────────────────────────────── */
        .modal-wrap {
            position: fixed; inset: 0;
            background: rgba(8,18,42,0.58); backdrop-filter: blur(4px);
            z-index: 60; display: none;
            align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-wrap.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 1.125rem;
            box-shadow: 0 28px 72px rgba(0,0,0,0.26);
            width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto;
            animation: modalPop 0.22s cubic-bezier(.4,0,.2,1);
        }
        @keyframes modalPop {
            from { opacity:0; transform: scale(0.95) translateY(10px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .modal-head {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-head-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .modal-close-btn {
            width: 1.875rem; height: 1.875rem; border-radius: 50%;
            background: #f1f5f9; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: #64748b; transition: all 0.15s ease;
        }
        .modal-close-btn:hover { background: #e2e8f0; color: #1e293b; }
        .modal-body { padding: 1.25rem 1.5rem; }
        .modal-field {
            display: flex; gap: 0.5rem;
            padding: 0.6rem 0; border-bottom: 1px solid #f8fafc;
            font-size: 0.875rem;
        }
        .modal-field:last-child { border-bottom: none; }
        .modal-field-label { font-weight: 600; color: #475569; min-width: 115px; flex-shrink: 0; }
        .modal-field-val { color: #1e293b; }
        .modal-foot {
            padding: 1rem 1.5rem 1.25rem;
            border-top: 1px solid #f1f5f9;
            display: flex; gap: 0.75rem;
        }

        /* ── Scrollbar ──────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(34,51,92,0.18); border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(34,51,92,0.32); }

        /* ── Report row clickable ───────────────────────── */
        .report-row { cursor: pointer; }

        /* ── Notif count label ──────────────────────────── */
        #report-notification-count { font-size: 0.78rem; color: #64748b; font-weight: 500; }
    </style>
</head>
<body>
<div class="bg-layer"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile hamburger with Transport Ops branding -->
<button class="mobile-brand-btn" id="hamburgerBtn" aria-label="Open menu">
    <div class="mobile-brand-icon">
        <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
        </svg>
    </div>
    <div class="mobile-brand-text">
        <div class="mobile-brand-name">Transport Ops</div>
        <div class="mobile-brand-tag">Admin Panel</div>
    </div>
</button>

<!-- Mobile Dropdown Menu -->
<div class="mobile-dropdown" id="mobileDropdown">
    <div class="mobile-dropdown-header">
        <div class="dropdown-brand">
            <div class="brand-icon">
                <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">Transport Ops</div>
                <div class="brand-tag">Admin Panel</div>
            </div>
        </div>
        <button class="dropdown-close" id="dropdownClose" aria-label="Close menu">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <nav class="mobile-nav">
        <a href="admin_dashboard.php" class="mobile-nav-link active">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="admin_reports.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"/>
            </svg>
            Reports
        </a>

        <a href="admin_trust_management.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Trust Management
        </a>

        <a href="route_status.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Route Status
        </a>

        <a href="manage_routes.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Manage Routes
        </a>

        <a href="heatmap.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Crowdsourcing Heatmap
        </a>

        <a href="user_management.php" class="mobile-nav-link">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            User Management
        </a>
    </nav>

    <div class="mobile-dropdown-footer">
        <div class="mobile-user-info">
            <div class="user-ava"><?php echo getInitials(
                $_SESSION["user_name"],
            ); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars(
                    $_SESSION["user_name"],
                ); ?></div>
                <div class="user-role-label"><?php echo htmlspecialchars(
                    $_SESSION["role"],
                ); ?></div>
            </div>
        </div>
        <a href="logout.php" class="mobile-logout-link">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Sign Out
        </a>
    </div>
</div>

<div class="app-layout">

    <!-- ═══ SIDEBAR ════════════════════════════════════════ -->
    <aside class="sidebar" id="appSidebar">

        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="brand-icon">
                <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">Transport Ops</div>
                <div class="brand-tag">Admin Panel</div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>

            <a href="admin_dashboard.php" class="nav-link active">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <a href="admin_reports.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"/>
                </svg>
                Reports
            </a>

            <a href="admin_trust_management.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Trust Management
            </a>

            <div class="nav-section-label">Routes</div>

            <a href="route_status.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                Route Status
            </a>

            <a href="manage_routes.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Manage Routes
            </a>

            <div class="nav-section-label">Analytics</div>

            <a href="heatmap.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Crowdsourcing Heatmap
            </a>

            <div class="nav-section-label">Users</div>

            <a href="user_management.php" class="nav-link">
                <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                User Management
            </a>
        </nav>

        <!-- Footer -->
        <div class="sidebar-footer">
            <div class="user-info-card">
                <div class="user-ava"><?php echo getInitials(
                    $_SESSION["user_name"],
                ); ?></div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars(
                        $_SESSION["user_name"],
                    ); ?></div>
                    <div class="user-role-label"><?php echo htmlspecialchars(
                        $_SESSION["role"],
                    ); ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-link">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ═══ MAIN CONTENT ════════════════════════════════════ -->
    <main class="main-area">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars(
                    $_SESSION["user_name"],
                ); ?> — here's what's happening today.</p>
            </div>
            <div class="date-chip">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="liveDateChip"><?php echo date("M d, Y"); ?></span>
            </div>
        </div>

        <!-- ── Analytics Charts ─────────────────────────── -->
        <div class="two-col">
            <!-- Reports Over Time Chart -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrap">
                        <div class="card-icon ci-blue">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2h6m-4-4l4 4-4 4m0 0l-4 4-4m0 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <span class="card-title">Reports Analytics</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-wrap">
                        <canvas id="reportsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Delay Trends Chart -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrap">
                        <div class="card-icon ci-orange">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="card-title">Delay Trends (7 Days)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-wrap">
                        <canvas id="delaysChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Metrics Overview ─────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title-wrap">
                    <div class="card-icon ci-blue">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2h6m-4-4l4 4-4 4m0 0l-4 4-4m0 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="card-title">System Metrics Overview</span>
                </div>

            </div>
            <div class="card-body">
                <!-- Metrics Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                    <!-- Total Reports Metric -->
                    <div class="metric-card">
                        <div class="metric-icon metric-green">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?php echo number_format(
                                $total_reports,
                            ); ?></div>
                            <div class="metric-label">Total Reports</div>
                            <div class="metric-change <?php echo $reports_change >=
                            0
                                ? "positive"
                                : "negative"; ?>">
                            <?php
                            echo $reports_change >= 0 ? "+" : "";
                            echo $reports_change;
                            ?>% this week
                        </div>
                        </div>
                    </div>

                    <!-- Active Delays Metric -->
                    <div class="metric-card">
                        <div class="metric-icon metric-red">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?php echo number_format(
                                $active_delays,
                            ); ?></div>
                            <div class="metric-label">Active Delays</div>
                            <div class="metric-change <?php echo $delays_change >=
                            0
                                ? "negative"
                                : "positive"; ?>">
                            <?php
                            echo $delays_change >= 0 ? "+" : "";
                            echo $delays_change;
                            ?>% this week
                        </div>
                        </div>
                    </div>

                    <!-- Total Users Metric -->
                    <div class="metric-card">
                        <div class="metric-icon metric-purple">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?php echo number_format(
                                $total_users,
                            ); ?></div>
                            <div class="metric-label">Total Users</div>
                            <div class="metric-change <?php echo $users_change >=
                            0
                                ? "positive"
                                : "negative"; ?>">
                            <?php
                            echo $users_change >= 0 ? "+" : "";
                            echo $users_change;
                            ?>% this week
                        </div>
                        </div>
                    </div>

                    <!-- Active Routes Metric -->
                    <div class="metric-card">
                        <div class="metric-icon metric-blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?php echo number_format(
                                $total_routes,
                            ); ?></div>
                            <div class="metric-label">Active Routes</div>
                            <div class="metric-change neutral">0% this week</div>
                        </div>
                    </div>

                </div>

                <!-- Summary Stats -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-gray-800"><?php echo round(
                                ($active_delays / max($total_reports, 1)) * 100,
                                1,
                            ); ?>%</div>
                            <div class="text-sm text-gray-600">Delay Rate</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800"><?php echo round(
                                $total_reports / max($total_users, 1),
                                1,
                            ); ?></div>
                            <div class="text-sm text-gray-600">Reports per User</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800"><?php echo round(
                                $total_reports / max($total_routes, 1),
                                1,
                            ); ?></div>
                            <div class="text-sm text-gray-600">Reports per Route</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent Reports Table ────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title-wrap">
                    <div class="card-icon ci-blue">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="card-title">Recent Reports</span>
                    <span id="report-notification-badge" class="badge badge-new" style="display:none;">New</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <span id="report-notification-count"></span>
                    <a href="admin_reports.php" class="btn-link">
                        View all
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Route</th>
                            <th>Crowd Level</th>
                            <th>Delay Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_reports)): ?>
                            <tr class="empty-row"><td colspan="5">No reports found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_reports as $report): ?>
                                <tr class="report-row"
                                    data-report="<?php echo htmlspecialchars(
                                        json_encode($report),
                                    ); ?>"
                                    title="Click to view details">
                                    <td>
                                        <div style="font-size:0.82rem;font-weight:600;color:#1e293b;">
                                            <?php echo date(
                                                "M d, Y",
                                                strtotime($report["timestamp"]),
                                            ); ?>
                                        </div>
                                        <div style="font-size:0.72rem;color:#94a3b8;margin-top:1px;">
                                            <?php echo date(
                                                "H:i",
                                                strtotime($report["timestamp"]),
                                            ); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.5rem;">
                                            <div class="avatar <?php echo getAvatarClass(
                                                $report["user_role"] ?? "",
                                            ); ?>">
                                                <?php echo getInitials(
                                                    $report["user_name"] ??
                                                        "N/A",
                                                ); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:#1e293b;font-size:0.835rem;"><?php echo htmlspecialchars(
                                                    $report["user_name"] ??
                                                        "N/A",
                                                ); ?></div>
                                                <div style="font-size:0.72rem;color:#94a3b8;"><?php echo htmlspecialchars(
                                                    $report["user_role"] ?? "",
                                                ); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;color:#334155;font-size:0.835rem;"><?php echo htmlspecialchars(
                                            $report["route_name"] ?? "N/A",
                                        ); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge(
                                            $report["crowd_level"],
                                        ); ?>">
                                            <span class="badge-dot"></span>
                                            <?php echo htmlspecialchars(
                                                $report["crowd_level"],
                                            ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($report["delay_reason"]): ?>
                                            <span style="color:#475569;font-size:0.835rem;">
                                                <?php
                                                echo htmlspecialchars(
                                                    substr(
                                                        $report["delay_reason"],
                                                        0,
                                                        52,
                                                    ),
                                                );
                                                if (
                                                    strlen(
                                                        $report["delay_reason"],
                                                    ) > 52
                                                ): ?>…<?php endif;
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1;font-size:0.825rem;font-style:italic;">None</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /card recent reports -->

        <!-- ── Analytics Row ──────────────────────────────── -->
        <div class="two-col">

            <!-- Delay Trend Analysis -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <div class="card-title-wrap">
                        <div class="card-icon ci-red">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                            </svg>
                        </div>
                        <span class="card-title">Delay Trends</span>
                    </div>
                    <span style="font-size:0.72rem;color:#94a3b8;font-weight:500;">Last 7 days</span>
                </div>
                <div class="card-body">
                    <?php if (empty($delay_trends)): ?>
                        <p style="color:#94a3b8;text-align:center;padding:1.5rem 0;font-size:0.855rem;">No delay data available.</p>
                    <?php else: ?>
                        <?php $maxDelay = max(
                            array_column($delay_trends, "count"),
                        ); ?>
                        <div style="display:flex;flex-direction:column;gap:0.875rem;">
                            <?php foreach ($delay_trends as $i => $trend): ?>
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                                        <span style="font-size:0.815rem;font-weight:600;color:#334155;"><?php echo htmlspecialchars(
                                            $trend["delay_reason"],
                                        ); ?></span>
                                        <span style="font-size:0.75rem;font-weight:700;color:#1e293b;background:#f1f5f9;padding:0.15rem 0.55rem;border-radius:999px;"><?php echo $trend[
                                            "count"
                                        ]; ?></span>
                                    </div>
                                    <div class="bar-track">
                                        <div class="bar-fill bar-red" style="width:<?php echo min(
                                            100,
                                            ($trend["count"] / $maxDelay) * 100,
                                        ); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Peak Hour Analysis -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-header">
                    <div class="card-title-wrap">
                        <div class="card-icon ci-orange">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="card-title">Peak Hour Crowding</span>
                    </div>
                    <span style="font-size:0.72rem;color:#94a3b8;font-weight:500;">Last 7 days</span>
                </div>
                <div class="card-body">
                    <?php if (empty($peak_hours)): ?>
                        <p style="color:#94a3b8;text-align:center;padding:1.5rem 0;font-size:0.855rem;">No peak hour data available.</p>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.875rem;">
                            <?php foreach ($peak_hours as $hour): ?>
                                <?php $pct =
                                    $hour["total_reports"] > 0
                                        ? ($hour["heavy_count"] /
                                                $hour["total_reports"]) *
                                            100
                                        : 0; ?>
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                                        <span style="font-size:0.815rem;font-weight:600;color:#334155;"><?php echo date(
                                            "g A",
                                            mktime($hour["hour"], 0, 0),
                                        ); ?></span>
                                        <span style="font-size:0.75rem;color:#64748b;"><?php echo $hour[
                                            "heavy_count"
                                        ]; ?> / <?php echo $hour[
     "total_reports"
 ]; ?> heavy</span>
                                    </div>
                                    <div class="bar-track">
                                        <div class="bar-fill bar-orange" style="width:<?php echo round(
                                            $pct,
                                        ); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /two-col -->

        <div style="margin-bottom:1.5rem;"></div>

        <!-- ── User Management Table ───────────────────────── -->
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-title-wrap">
                    <div class="card-icon ci-purple">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="card-title">User Management</span>
                </div>
                <a href="user_management.php" class="btn-primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Manage Users
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users_data)): ?>
                            <tr class="empty-row"><td colspan="4">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users_data as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.55rem;">
                                            <div class="avatar <?php echo getAvatarClass(
                                                $user["role"],
                                            ); ?>">
                                                <?php echo getInitials(
                                                    $user["name"],
                                                ); ?>
                                            </div>
                                            <span style="font-weight:600;color:#1e293b;font-size:0.855rem;"><?php echo htmlspecialchars(
                                                $user["name"],
                                            ); ?></span>
                                        </div>
                                    </td>
                                    <td style="color:#64748b;"><?php echo htmlspecialchars(
                                        $user["email"],
                                    ); ?></td>
                                    <td>
                                        <span class="badge <?php echo getRoleBadge(
                                            $user["role"],
                                        ); ?>">
                                            <?php echo htmlspecialchars(
                                                $user["role"],
                                            ); ?>
                                        </span>
                                    </td>
                                    <td style="color:#64748b;font-size:0.82rem;">
                                        <?php echo date(
                                            "M d, Y",
                                            strtotime($user["created_at"]),
                                        ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /card users -->

    </main>
</div><!-- /app-layout -->

<!-- ═══ REPORT DETAIL MODAL ═══════════════════════════════ -->
<div class="modal-wrap" id="reportModal" aria-modal="true" role="dialog">
    <div class="modal-box">
        <div class="modal-head">
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <div class="card-icon ci-blue">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <span class="modal-head-title">Report Details</span>
            </div>
            <button class="modal-close-btn" id="reportModalClose" aria-label="Close">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="reportModalBody"></div>
        <div class="modal-foot">
            <a id="reportModalViewOnMap" href="#" class="btn-primary">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                View on Map
            </a>
            <button type="button" id="reportModalCloseBtn" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 1.1rem;background:#f1f5f9;color:#475569;border-radius:0.5rem;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;transition:background 0.15s;">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // ── Chart Data Preparation ─────────────────────── */
    const delayTrendsData = <?php echo json_encode(
        array_map(function ($item) {
            return [
                "x" => htmlspecialchars($item["delay_reason"] ?? "Unknown"),
                "y" => (int) ($item["count"] ?? 0),
            ];
        }, $delay_trends),
    ); ?>;

    const peakHoursData = <?php echo json_encode(
        array_map(function ($item) {
            return [
                "x" => (int) $item["hour"] . ":00",
                "y" => (int) ($item["total_reports"] ?? 0),
            ];
        }, $peak_hours),
    ); ?>;

    // ── Chart Configuration ─────────────────────────── */
    const chartColors = {
        primary: '#22335C',
        secondary: '#5B7B99',
        accent: '#FBC061',
        success: '#16a34a',
        danger: '#dc2626',
        warning: '#f59e0b',
        info: '#06b6d4',
        grid: 'rgba(34, 51, 92, 0.1)'
    };

    // ── Reports Over Time Chart ─────────────────────────── */
    const reportsCtx = document.getElementById('reportsChart');
    if (reportsCtx) {
        const reportsData = <?php echo json_encode(
            $reports_over_time ?? [],
        ); ?>;
        console.log('Reports data:', reportsData); // Debug log

        new Chart(reportsCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['7 Days Ago', '6 Days Ago', '5 Days Ago', '4 Days Ago', '3 Days Ago', '2 Days Ago', 'Yesterday', 'Today'],
                datasets: [{
                    label: 'Reports Per Day',
                    data: reportsData,
                    borderColor: chartColors.primary,
                    backgroundColor: 'rgba(34, 51, 92, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#64748b',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: chartColors.grid
                    },
                    ticks: {
                        color: '#64748b'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b'
                    }
                }
            }
        }
    });
    } else {
        console.error('Reports chart canvas not found');
    }

    // ── Delay Trends Chart ─────────────────────────────── */
    const delaysCtx = document.getElementById('delaysChart');
    if (delaysCtx) {
        console.log('Delay trends data:', delayTrendsData); // Debug log

        new Chart(delaysCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: delayTrendsData.map(item => item.x),
                datasets: [{
                    label: 'Delay Reasons',
                    data: delayTrendsData.map(item => item.y),
                backgroundColor: [
                    chartColors.danger,
                    chartColors.warning,
                    chartColors.info,
                    chartColors.secondary,
                    chartColors.accent
                ],
                borderColor: [
                    chartColors.danger,
                    chartColors.warning,
                    chartColors.info,
                    chartColors.secondary,
                    chartColors.accent
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#64748b',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: chartColors.grid
                    },
                    ticks: {
                        color: '#64748b'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        maxRotation: 45
                    }
                }
            }
        }
        });
    } else {
        console.error('Delays chart canvas not found');
    }



    /* ── Live date ────────────────────────────────── */──────── */
    (function () {
        const el = document.getElementById('liveDateChip');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    })();

    /* ── Mobile sidebar toggle ────────────────────────────── */
    (function () {
        const btn     = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!btn || !sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        btn.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);
    })();

    /* ── New-report notification polling ─────────────────── */
    (function () {
        let lastReportTimestamp = <?php
        $latest = !empty($recent_reports)
            ? $recent_reports[0]["timestamp"]
            : null;
        echo $latest ? json_encode($latest) : "null";
        ?>;
        let notificationAudio;

        function initAudio() {
            try { notificationAudio = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg'); }
            catch (e) { notificationAudio = null; }
        }
        function playNotificationSound() {
            if (!notificationAudio) return;
            notificationAudio.currentTime = 0;
            notificationAudio.play().catch(() => {});
        }
        async function checkNewReports() {
            try {
                const params   = lastReportTimestamp ? '?since=' + encodeURIComponent(lastReportTimestamp) : '';
                const response = await fetch('admin_notifications.php' + params, { credentials: 'same-origin' });
                if (!response.ok) return;
                const data     = await response.json();
                const newCount = data.new_count || 0;
                const latest   = data.latest_timestamp || null;
                const badge    = document.getElementById('report-notification-badge');
                const countLbl = document.getElementById('report-notification-count');
                if (newCount > 0) {
                    if (badge)    badge.style.display = '';
                    if (countLbl) countLbl.textContent = newCount + ' new report' + (newCount > 1 ? 's' : '');
                    playNotificationSound();
                }
                if (latest) lastReportTimestamp = latest;
            } catch (e) { console.error('Notification check failed', e); }
        }
        initAudio();
        setInterval(checkNewReports, 15000);
    })();

    /* ── Report detail modal ──────────────────────────────── */
    (function () {
        const modal      = document.getElementById('reportModal');
        const body       = document.getElementById('reportModalBody');
        const mapLink    = document.getElementById('reportModalViewOnMap');
        const closeBtn   = document.getElementById('reportModalClose');
        const closeBtn2  = document.getElementById('reportModalCloseBtn');

        function crowdClass(level) {
            if (level === 'Heavy')    return 'badge badge-heavy';
            if (level === 'Moderate') return 'badge badge-moderate';
            if (level === 'Light')    return 'badge badge-light';
            return 'badge badge-unknown';
        }

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function openModal(report) {
            const r    = report;
            const time = r.timestamp ? new Date(r.timestamp).toLocaleString() : 'N/A';
            const rows = [
                { label: 'Time',        val: time },
                { label: 'Reported by', val: escapeHtml(r.user_name || 'N/A') + (r.user_role ? ' <span style="color:#94a3b8;font-size:0.78rem;">(' + escapeHtml(r.user_role) + ')</span>' : '') },
                { label: 'Route',       val: escapeHtml(r.route_name || 'N/A') },
                { label: 'Crowd Level', val: '<span class="' + crowdClass(r.crowd_level) + '"><span class="badge-dot"></span>' + escapeHtml(r.crowd_level || '') + '</span>' },
                r.delay_reason ? { label: 'Delay Reason', val: escapeHtml(r.delay_reason) } : null,
                (r.latitude && r.longitude) ? { label: 'Location', val: '<span style="color:#94a3b8;">' + parseFloat(r.latitude).toFixed(5) + ', ' + parseFloat(r.longitude).toFixed(5) + '</span>' } : null,
            ].filter(Boolean);

            body.innerHTML = rows.map(row =>
                '<div class="modal-field">' +
                '<span class="modal-field-label">' + row.label + '</span>' +
                '<span class="modal-field-val">'   + row.val   + '</span>' +
                '</div>'
            ).join('');

            mapLink.href = (r.latitude && r.longitude) ? 'admin_reports.php?focus=' + r.id : 'admin_reports.php';
            mapLink.style.visibility = (r.latitude && r.longitude) ? 'visible' : 'hidden';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.report-row').forEach(function (row) {
            row.addEventListener('click', function () {
                try {
                    const d = this.getAttribute('data-report');
                    if (d) openModal(JSON.parse(d));
                } catch (e) { console.error(e); }
            });
        });

        closeBtn.addEventListener('click',  closeModal);
        closeBtn2.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    })();
}); // Close DOM ready event
</script>

<?php include "admin_sidebar_js.php"; ?>
</body>
</html>
