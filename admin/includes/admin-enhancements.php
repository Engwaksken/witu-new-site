<?php
/**
 * Shared admin enhancements: activity logging + reusable page statistics.
 * Safe to include from any authenticated admin page.
 */

if (!function_exists('adminTableExists')) {
    function adminTableExists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('adminColumnExists')) {
    function adminColumnExists(mysqli $conn, string $table, string $column): bool
    {
        if (!adminTableExists($conn, $table)) return false;
        $safe = $conn->real_escape_string($column);
        $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('adminEnsureActivityLogTable')) {
    function adminEnsureActivityLogTable(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            user_name VARCHAR(150) NULL,
            action VARCHAR(80) NOT NULL DEFAULT 'view',
            module VARCHAR(120) NULL,
            description VARCHAR(500) NULL,
            method VARCHAR(10) NULL,
            request_uri VARCHAR(500) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_user (user_id),
            INDEX idx_activity_action (action),
            INDEX idx_activity_module (module),
            INDEX idx_activity_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('adminClientIp')) {
    function adminClientIp(): string
    {
        $candidates = [$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', $_SERVER['REMOTE_ADDR'] ?? ''];
        foreach ($candidates as $candidate) {
            if ($candidate === '') continue;
            $ip = trim(explode(',', $candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return '0.0.0.0';
    }
}

if (!function_exists('adminLogActivity')) {
    function adminLogActivity(mysqli $conn, string $action, string $module = '', string $description = ''): void
    {
        adminEnsureActivityLogTable($conn);
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $userName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? null;
        if ($userId && !$userName && adminTableExists($conn, 'users')) {
            $st = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $userId);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $userName = $row['name'] ?? null;
                $st->close();
            }
        }
        $method = substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 10);
        $uri = substr($_SERVER['REQUEST_URI'] ?? '', 0, 500);
        $ip = adminClientIp();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $action = substr($action, 0, 80);
        $module = substr($module, 0, 120);
        $description = substr($description, 0, 500);

        $st = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, action, module, description, method, request_uri, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($st) {
            $st->bind_param('issssssss', $userId, $userName, $action, $module, $description, $method, $uri, $ip, $ua);
            $st->execute();
            $st->close();
        }
    }
}

if (!function_exists('adminTrackActivity')) {
    function adminTrackActivity(mysqli $conn): void
    {
        static $tracked = false;
        if ($tracked) return;
        $tracked = true;

        $module = basename($_SERVER['PHP_SELF'] ?? 'admin', '.php');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $action = 'view';
        $description = 'Viewed ' . str_replace(['-', '_'], ' ', $module);

        if ($method === 'POST') {
            $postedAction = trim((string)($_POST['action'] ?? 'update'));
            $action = preg_replace('/[^a-z0-9_-]/i', '', $postedAction) ?: 'update';
            $description = ucfirst(str_replace(['-', '_'], ' ', $action)) . ' action in ' . str_replace(['-', '_'], ' ', $module);
        }
        adminLogActivity($conn, $action, $module, $description);
    }
}

if (!function_exists('adminScalar')) {
    function adminScalar(mysqli $conn, string $sql, int $default = 0): int
    {
        $r = $conn->query($sql);
        if (!$r) return $default;
        $row = $r->fetch_row();
        return isset($row[0]) ? (int)$row[0] : $default;
    }
}

if (!function_exists('adminStatsForCurrentPage')) {
    function adminStatsForCurrentPage(mysqli $conn): array
    {
        $page = basename($_SERVER['PHP_SELF'] ?? '');
        $map = [
            'programmes.php' => ['programmes', 'Programmes', 'fa-rocket'],
            'programs.php' => ['programs', 'Programs', 'fa-graduation-cap'],
            'focus-areas.php' => ['focus_areas', 'Focus Areas', 'fa-bullseye'],
            'jobs.php' => ['jobs', 'Jobs', 'fa-briefcase'],
            'learning.php' => ['learning', 'Learning Items', 'fa-graduation-cap'],
            'news-blog.php' => ['news_blog', 'Articles', 'fa-newspaper'],
            'team.php' => ['team', 'Team Members', 'fa-users'],
            'partners.php' => ['partners', 'Partners', 'fa-handshake'],
            'testimonials.php' => ['testimonials', 'Testimonials', 'fa-comment-dots'],
            'annual-reports.php' => ['annual_reports', 'Annual Reports', 'fa-chart-column'],
            'opportunities.php' => ['opportunities', 'Opportunities', 'fa-lightbulb'],
            'users.php' => ['users', 'Users', 'fa-user-group'],
            'roles.php' => ['roles', 'Roles', 'fa-user-shield'],
            'sliders.php' => ['sliders', 'Sliders', 'fa-images'],
            'hero-images.php' => ['hero_images', 'Hero Images', 'fa-image'],
            'volunteer.php' => ['volunteer_submissions', 'Volunteer Requests', 'fa-hand-holding-heart'],
            'hire-talent.php' => ['hire_talent_submissions', 'Talent Requests', 'fa-user-tie'],
            'partner.php' => ['partner_submissions', 'Partner Requests', 'fa-people-arrows'],
            'newsletters.php' => ['newsletters', 'Newsletters', 'fa-envelope-open-text'],
        ];
        if (!isset($map[$page])) return [];
        [$table, $label, $icon] = $map[$page];
        if (!adminTableExists($conn, $table)) return [];

        $total = adminScalar($conn, "SELECT COUNT(*) FROM `{$table}`");
        $cards = [[
            'label' => 'Total ' . $label,
            'value' => $total,
            'icon' => $icon,
            'class' => 'stat-card--blue'
        ]];

        if (adminColumnExists($conn, $table, 'status')) {
            $publishedTerms = ["published", "active", "open", "approved", "completed"];
            $escaped = array_map(fn($v) => "'".$conn->real_escape_string($v)."'", $publishedTerms);
            $active = adminScalar($conn, "SELECT COUNT(*) FROM `{$table}` WHERE LOWER(status) IN (".implode(',', $escaped).")");
            $cards[] = ['label' => 'Active / Published', 'value' => $active, 'icon' => 'fa-circle-check', 'class' => 'stat-card--green'];
            $draft = adminScalar($conn, "SELECT COUNT(*) FROM `{$table}` WHERE LOWER(status) IN ('draft','pending','new','inactive')");
            $cards[] = ['label' => 'Draft / Pending', 'value' => $draft, 'icon' => 'fa-clock', 'class' => 'stat-card--amber'];
        } else {
            $cards[] = ['label' => 'Records Available', 'value' => $total, 'icon' => 'fa-database', 'class' => 'stat-card--green'];
        }

        if (adminColumnExists($conn, $table, 'created_at')) {
            $month = adminScalar($conn, "SELECT COUNT(*) FROM `{$table}` WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')");
            $cards[] = ['label' => 'Added This Month', 'value' => $month, 'icon' => 'fa-calendar-days', 'class' => 'stat-card--purple'];
        }
        return array_slice($cards, 0, 4);
    }
}

if (!function_exists('renderAdminPageStats')) {
    function renderAdminPageStats(mysqli $conn): void
    {
        $cards = adminStatsForCurrentPage($conn);
        if (!$cards) return;
        echo '<div class="stats-grid page-stats-grid">';
        foreach ($cards as $card) {
            echo '<div class="stat-card '.htmlspecialchars($card['class']).'">';
            echo '<div class="stat-icon"><i class="fas '.htmlspecialchars($card['icon']).'"></i></div>';
            echo '<div class="stat-info"><div class="stat-value">'.number_format((int)$card['value']).'</div><div class="stat-label">'.htmlspecialchars($card['label']).'</div></div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
