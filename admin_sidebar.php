<?php
/* Admin shared sidebar HTML – include after opening <body> */
$current_page = basename($_SERVER['PHP_SELF']);

function sidebar_initials($name) {
    $parts = explode(" ", trim($name));
    if (count($parts) >= 2) return strtoupper(substr($parts[0],0,1).substr($parts[1],0,1));
    return strtoupper(substr($name,0,2));
}

function nav_active($page, $current) {
    return $page === $current ? ' active' : '';
}
?>
<div class="bg-layer"></div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile hamburger -->
<button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<div class="app-layout">

<!-- ═══ SIDEBAR ════════════════════════════════════════════ -->
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

        <a href="admin_dashboard.php" class="nav-link<?php echo nav_active('admin_dashboard.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="admin_reports.php" class="nav-link<?php echo nav_active('admin_reports.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"/>
            </svg>
            Reports
        </a>

        <a href="admin_trust_management.php" class="nav-link<?php echo nav_active('admin_trust_management.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Trust Management
        </a>

        <div class="nav-section-label">Routes</div>

        <a href="route_status.php" class="nav-link<?php echo nav_active('route_status.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Route Status
        </a>

        <a href="manage_routes.php" class="nav-link<?php echo nav_active('manage_routes.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Manage Routes
        </a>

        <div class="nav-section-label">Analytics</div>

        <a href="heatmap.php" class="nav-link<?php echo nav_active('heatmap.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Crowdsourcing Heatmap
        </a>

        <div class="nav-section-label">Users</div>

        <a href="user_management.php" class="nav-link<?php echo nav_active('user_management.php', $current_page); ?>">
            <svg class="nav-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            User Management
        </a>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="user-info-card">
            <div class="user-ava"><?php echo sidebar_initials($_SESSION['user_name'] ?? 'AD'); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                <div class="user-role-label"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></div>
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
<!-- ═══ END SIDEBAR ════════════════════════════════════════ -->
