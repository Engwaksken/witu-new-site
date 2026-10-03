<?php
if (!function_exists('renderAdminPageStats')) {
    $enhancements = __DIR__ . '/../includes/admin-enhancements.php';
    if (is_file($enhancements)) require_once $enhancements;
}
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
function adminNavActive(string $file): string { global $currentPage; return $currentPage === $file ? 'active' : ''; }
?>
<aside class="admin-sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?=adminNavActive('dashboard.php')?>">
            <span class="nav-icon"><i class="fas fa-chart-pie"></i></span><span class="nav-text">Dashboard</span>
        </a>

        <?php if (hasPermission($conn, 'manage_pages') || hasPermission($conn, 'manage_programmes') || hasPermission($conn, 'manage_focus_areas') || hasPermission($conn, 'manage_about_us') || hasPermission($conn, 'manage_opportunities') || hasPermission($conn, 'manage_jobs') || hasPermission($conn, 'manage_learning') || hasPermission($conn, 'manage_news_blog') || hasPermission($conn, 'manage_team') || hasPermission($conn, 'manage_reports') || hasPermission($conn, 'manage_newsletters')): ?>
        <div class="nav-dropdown <?=in_array($currentPage,['home.php','pages.php','about-us.php','programmes.php','programs.php','focus-areas.php','volunteer.php','partner.php','hire-talent.php','annual-reports.php','newsletters.php','opportunities.php','jobs.php','learning.php','news-blog.php','team.php'])?'open':''?>">
            <a href="#" class="nav-item nav-dropdown-toggle"><span class="nav-icon"><i class="fas fa-folder-open"></i></span><span class="nav-text">Manage Content</span><span class="dropdown-arrow"><i class="fas fa-chevron-down"></i></span></a>
            <div class="nav-dropdown-menu">
                <?php if (hasPermission($conn, 'manage_home')): ?><a href="home.php" class="nav-dropdown-item <?=adminNavActive('home.php')?>"><span class="nav-icon"><i class="fas fa-house"></i></span><span class="nav-text">Home</span></a><?php endif; ?>
                <!--<?php if (hasPermission($conn, 'manage_pages')): ?><a href="pages.php" class="nav-dropdown-item <?=adminNavActive('pages.php')?>"><span class="nav-icon"><i class="fas fa-file-lines"></i></span><span class="nav-text">Pages</span></a><?php endif; ?>-->
                <?php if (hasPermission($conn, 'manage_about_us')): ?><a href="about-us.php" class="nav-dropdown-item <?=adminNavActive('about-us.php')?>"><span class="nav-icon"><i class="fas fa-circle-info"></i></span><span class="nav-text">About Us</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_programmes')): ?><a href="programmes.php" class="nav-dropdown-item <?=adminNavActive('programmes.php')?>"><span class="nav-icon"><i class="fas fa-rocket"></i></span><span class="nav-text">Programmes</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_focus_areas')): ?><a href="focus-areas.php" class="nav-dropdown-item <?=adminNavActive('focus-areas.php')?>"><span class="nav-icon"><i class="fas fa-bullseye"></i></span><span class="nav-text">Focus Areas</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_volunteer')): ?><a href="volunteer.php" class="nav-dropdown-item <?=adminNavActive('volunteer.php')?>"><span class="nav-icon"><i class="fas fa-hand-holding-heart"></i></span><span class="nav-text">Volunteer</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_partner')): ?><a href="partner.php" class="nav-dropdown-item <?=adminNavActive('partner.php')?>"><span class="nav-icon"><i class="fas fa-people-arrows"></i></span><span class="nav-text">Partner</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_hire_talent')): ?><a href="hire-talent.php" class="nav-dropdown-item <?=adminNavActive('hire-talent.php')?>"><span class="nav-icon"><i class="fas fa-user-tie"></i></span><span class="nav-text">Hire Talent</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_reports')): ?><a href="annual-reports.php" class="nav-dropdown-item <?=adminNavActive('annual-reports.php')?>"><span class="nav-icon"><i class="fas fa-chart-column"></i></span><span class="nav-text">Annual Reports</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_newsletters')): ?><a href="newsletters.php" class="nav-dropdown-item <?=adminNavActive('newsletters.php')?>"><span class="nav-icon"><i class="fas fa-envelope-open-text"></i></span><span class="nav-text">Newsletters</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_opportunities')): ?><a href="opportunities.php" class="nav-dropdown-item <?=adminNavActive('opportunities.php')?>"><span class="nav-icon"><i class="fas fa-lightbulb"></i></span><span class="nav-text">Opportunities</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_jobs')): ?><a href="jobs.php" class="nav-dropdown-item <?=adminNavActive('jobs.php')?>"><span class="nav-icon"><i class="fas fa-briefcase"></i></span><span class="nav-text">Jobs</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_learning')): ?><a href="learning.php" class="nav-dropdown-item <?=adminNavActive('learning.php')?>"><span class="nav-icon"><i class="fas fa-graduation-cap"></i></span><span class="nav-text">Learning</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_news_blog')): ?><a href="news-blog.php" class="nav-dropdown-item <?=adminNavActive('news-blog.php')?>"><span class="nav-icon"><i class="fas fa-newspaper"></i></span><span class="nav-text">News &amp; Blog</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_team')): ?><a href="team.php" class="nav-dropdown-item <?=adminNavActive('team.php')?>"><span class="nav-icon"><i class="fas fa-user-group"></i></span><span class="nav-text">Team</span></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, 'manage_home') || hasPermission($conn, 'manage_sliders') || hasPermission($conn, 'manage_participant_journey') || hasPermission($conn, 'manage_testimonials') || hasPermission($conn, 'manage_partners')): ?>
        <div class="nav-dropdown <?=in_array($currentPage,['sliders.php','hero-images.php','participant-journey.php','testimonials.php','partners.php','our-impact.php'])?'open':''?>">
            <a href="#" class="nav-item nav-dropdown-toggle"><span class="nav-icon"><i class="fas fa-layer-group"></i></span><span class="nav-text">Manage Sections</span><span class="dropdown-arrow"><i class="fas fa-chevron-down"></i></span></a>
            <div class="nav-dropdown-menu">
                <?php if (hasPermission($conn, 'manage_sliders')): ?><a href="sliders.php" class="nav-dropdown-item <?=adminNavActive('sliders.php')?>"><span class="nav-icon"><i class="fas fa-images"></i></span><span class="nav-text">Hero Sliders</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_hero_images')): ?><a href="hero-images.php" class="nav-dropdown-item <?=adminNavActive('hero-images.php')?>"><span class="nav-icon"><i class="fas fa-image"></i></span><span class="nav-text">Hero Images</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_participant_journey')): ?><a href="participant-journey.php" class="nav-dropdown-item <?=adminNavActive('participant-journey.php')?>"><span class="nav-icon"><i class="fas fa-route"></i></span><span class="nav-text">Participant Journey</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_testimonials')): ?><a href="testimonials.php" class="nav-dropdown-item <?=adminNavActive('testimonials.php')?>"><span class="nav-icon"><i class="fas fa-comment-dots"></i></span><span class="nav-text">Testimonials</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_partners')): ?><a href="partners.php" class="nav-dropdown-item <?=adminNavActive('partners.php')?>"><span class="nav-icon"><i class="fas fa-handshake"></i></span><span class="nav-text">Partners</span></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, 'manage_users') || hasPermission($conn, 'manage_roles') || hasPermission($conn, 'manage_settings')): ?>
        <div class="nav-dropdown <?=in_array($currentPage,['users.php','roles.php','permissions.php','settings.php','activity-logs.php'])?'open':''?>">
            <a href="#" class="nav-item nav-dropdown-toggle"><span class="nav-icon"><i class="fas fa-gears"></i></span><span class="nav-text">Site Management</span><span class="dropdown-arrow"><i class="fas fa-chevron-down"></i></span></a>
            <div class="nav-dropdown-menu">
                <?php if (hasPermission($conn, 'manage_users')): ?><a href="users.php" class="nav-dropdown-item <?=adminNavActive('users.php')?>"><span class="nav-icon"><i class="fas fa-users"></i></span><span class="nav-text">Users</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_roles')): ?><a href="roles.php" class="nav-dropdown-item <?=adminNavActive('roles.php')?>"><span class="nav-icon"><i class="fas fa-user-shield"></i></span><span class="nav-text">Roles</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_roles')): ?><a href="permissions.php" class="nav-dropdown-item <?=adminNavActive('permissions.php')?>"><span class="nav-icon"><i class="fas fa-shield-halved"></i></span><span class="nav-text">Permissions</span></a><?php endif; ?>
                <?php if (hasPermission($conn, 'manage_settings')): ?><a href="settings.php" class="nav-dropdown-item <?=adminNavActive('settings.php')?>"><span class="nav-icon"><i class="fas fa-sliders"></i></span><span class="nav-text">Settings</span></a><?php endif; ?>
                <a href="activity-logs.php" class="nav-dropdown-item <?=adminNavActive('activity-logs.php')?>"><span class="nav-icon"><i class="fas fa-clock-rotate-left"></i></span><span class="nav-text">Activity Logs</span></a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
</aside>
<main class="admin-content">
<?php if (function_exists('renderAdminPageStats') && basename($_SERVER['PHP_SELF'] ?? '') !== 'newsletters.php') renderAdminPageStats($conn); ?>
