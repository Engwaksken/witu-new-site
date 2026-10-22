<?php
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!function_exists('siteLogoUrl')) {
    require_once __DIR__ . '/../../core/helpers.php';
}
$current_user = getCurrentUser($conn);
$admin_site_name = getSetting($conn, 'site_name', '') ?: SITE_NAME;
$admin_favicon   = getSetting($conn, 'site_favicon', '');
$admin_favicon   = $admin_favicon === '' ? '../assets/img/favicon.png'
    : (preg_match('#^(https?:)?//|^/#i', $admin_favicon) ? $admin_favicon : '../' . $admin_favicon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($admin_site_name); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars($admin_favicon); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../<?php echo htmlspecialchars(versionedAsset('assets/css/admin.css')); ?>">
    <script>
    // Apply the saved desktop sidebar state before first paint so it doesn't flash.
    try {
        if (window.innerWidth > 1024 && localStorage.getItem('adminSidebarCollapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {}
    </script>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="sidebar-toggle" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="dashboard.php" class="admin-brand" aria-label="<?php echo htmlspecialchars($admin_site_name); ?> dashboard">
                    <img src="<?php echo htmlspecialchars(siteLogoUrl($conn, '../')); ?>"
                         alt="<?php echo htmlspecialchars($admin_site_name); ?>">
                </a>
            </div>
            <div class="header-right">
                <a href="../index.php" target="_blank" rel="noopener" class="btn btn-sm header-view-site" title="View site">
                    <i class="fas fa-arrow-up-right-from-square"></i><span>View Site</span>
                </a>
                <span class="user-name">Welcome, <?php echo htmlspecialchars($current_user['name'] ?? ''); ?></span>
                <a href="logout.php" class="btn btn-sm" title="Logout">
                    <i class="fas fa-right-from-bracket"></i><span class="btn-label">Logout</span>
                </a>
            </div>
        </header>
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
