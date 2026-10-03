<?php

/*
|--------------------------------------------------------------------------
| Safe upload defaults
|--------------------------------------------------------------------------
| These are fallbacks only. If config.php/auth.php already defines them,
| the existing project values are kept.
*/
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
}

if (!defined('ALLOWED_IMAGES')) {
    define('ALLOWED_IMAGES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
}

if (!defined('ALLOWED_DOCS')) {
    define('ALLOWED_DOCS', [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
        'ppt', 'pptx', 'txt', 'rtf', 'zip'
    ]);
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
}


function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date($format, strtotime($date));
}


function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

function isDatePast($date) {
    return strtotime($date) < time();
}


function isDateFuture($date) {
    return strtotime($date) > time();
}


function truncate($text, $length = 100, $suffix = '...') {
    $text = strip_tags($text);
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . $suffix;
    }
    return $text;
}


function truncateWords($text, $words = 50, $suffix = '...') {
    $text = strip_tags($text);
    $word_array = explode(' ', $text);
    
    if (count($word_array) > $words) {
        $text = implode(' ', array_slice($word_array, 0, $words)) . $suffix;
    }
    
    return $text;
}

function generateSlug($text) {
    // Convert to lowercase
    $slug = strtolower($text);
    
    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);
    
    // Remove special characters (keep alphanumeric and hyphens)
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    
    // Replace multiple hyphens with single hyphen
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim hyphens from edges
    $slug = trim($slug, '-');
    
    // Return 'n-a' if empty
    if (empty($slug)) {
        return 'n-a';
    }
    
    return $slug;
}


function sanitizeHTML($html) {
    $allowed_tags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><blockquote><code><pre>';
    return strip_tags($html, $allowed_tags);
}


function nl2br_safe($text) {
    return nl2br(htmlspecialchars($text));
}


function htmlToText($html, $length = null) {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    if ($length) {
        $text = truncate($text, $length);
    }
    
    return $text;
}


function uploadFile($file, $destination_folder, ?array $allowed_extensions = null) {
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload parameters.'];
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];

        return [
            'success' => false,
            'message' => $error_messages[(int)$file['error']] ?? 'Unknown file upload error.'
        ];
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'The uploaded temporary file is invalid.'];
    }

    $size = isset($file['size']) ? (int)$file['size'] : 0;
    if ($size <= 0) {
        return ['success' => false, 'message' => 'The uploaded file is empty.'];
    }

    $max_file_size = defined('MAX_FILE_SIZE') ? (int)MAX_FILE_SIZE : (10 * 1024 * 1024);
    if ($size > $max_file_size) {
        $max_mb = round($max_file_size / 1048576, 2);
        return ['success' => false, 'message' => "File too large. Maximum size: {$max_mb} MB."];
    }

    $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

    if ($allowed_extensions === null) {
        $images = defined('ALLOWED_IMAGES') && is_array(ALLOWED_IMAGES)
            ? ALLOWED_IMAGES
            : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        $docs = defined('ALLOWED_DOCS') && is_array(ALLOWED_DOCS)
            ? ALLOWED_DOCS
            : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'rtf', 'zip'];

        $allowed_extensions = array_merge($images, $docs);
    }

    $allowed_extensions = array_values(array_unique(array_map(
        static fn($value) => strtolower(ltrim((string)$value, '.')),
        $allowed_extensions
    )));

    if ($ext === '' || !in_array($ext, $allowed_extensions, true)) {
        return [
            'success' => false,
            'message' => 'Invalid file type' . ($ext !== '' ? ': ' . $ext : '.')
        ];
    }

    $original_name = (string)pathinfo((string)($file['name'] ?? 'file'), PATHINFO_FILENAME);
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $original_name);
    $safe_name = trim((string)$safe_name, '-_');
    if ($safe_name === '') {
        $safe_name = 'file';
    }

    try {
        $random = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $random = uniqid('', true);
        $random = preg_replace('/[^a-zA-Z0-9]/', '', $random);
    }

    $new_filename = $safe_name . '_' . date('Ymd_His') . '_' . $random . '.' . $ext;

    $base_upload_path = defined('UPLOAD_PATH')
        ? rtrim((string)UPLOAD_PATH, '/\\')
        : rtrim(dirname(__DIR__) . '/uploads', '/\\');

    $destination_folder = trim((string)$destination_folder, '/\\');
    $destination_path = $base_upload_path . DIRECTORY_SEPARATOR . $destination_folder;

    if (!is_dir($destination_path) && !mkdir($destination_path, 0755, true) && !is_dir($destination_path)) {
        return ['success' => false, 'message' => 'Failed to create the upload directory.'];
    }

    if (!is_writable($destination_path)) {
        return ['success' => false, 'message' => 'The upload directory is not writable.'];
    }

    $destination = $destination_path . DIRECTORY_SEPARATOR . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Failed to save the uploaded file.'];
    }

    return [
        'success'   => true,
        'filename'  => $new_filename,
        'path'      => $destination,
        'size'      => $size,
        'extension' => $ext,
    ];
}

function deleteFile($filename, $folder) {
    if (empty($filename)) {
        return false;
    }
    
    $path = UPLOAD_PATH . $folder . '/' . $filename;
    
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    
    return false;
}

function formatFileSize($bytes, $decimals = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
}


function isImage($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedImages = defined('ALLOWED_IMAGES') && is_array(ALLOWED_IMAGES)
        ? ALLOWED_IMAGES
        : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    return in_array($ext, $allowedImages, true);
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}


function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}


function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        
        return ['type' => $type, 'message' => $message];
    }
    
    return null;
}

function hasFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['flash_message']);
}


function paginate($conn, $table, $where = '1=1', $page = 1, $per_page = ITEMS_PER_PAGE) {
    $page = max(1, intval($page));
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM $table WHERE $where";
    $count_result = $conn->query($count_query);
    $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    
    $total_pages = ceil($total / $per_page);
    
    return [
        'offset' => $offset,
        'limit' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages,
        'prev_page' => max(1, $page - 1),
        'next_page' => min($total_pages, $page + 1)
    ];
}


function renderPagination($pagination, $url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $url . '?page=' . $pagination['prev_page'] . '" class="page-link">« Previous</a>';
    }
    
    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<a href="' . $url . '?page=' . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<a href="' . $url . '?page=' . $pagination['next_page'] . '" class="page-link">Next »</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}


function getSetting($conn, $key, $default = '') {
    static $settings_cache = [];
    
    // Check cache first
    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $settings_cache[$key] = $row['setting_value'];
        return $row['setting_value'];
    }
    
    return $default;
}


function setSetting($conn, $key, $value) {
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
        return $stmt->execute();
    }
}


function getAllSettings($conn, $group = null) {
    $settings = [];
    
    if ($group) {
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_group = ?");
        $stmt->bind_param("s", $group);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT setting_key, setting_value FROM site_settings");
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings;
}

function isFeatureEnabled($conn, $feature) {
    $value = getSetting($conn, 'enable_' . $feature, '0');
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}


function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 * 
 * @param string $url URL to validate
 * @return bool True if valid
 */
function isValidURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}


function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

function sanitizePhone($phone) {
    return preg_replace('/[^0-9+\-() ]/', '', $phone);
}


function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}


function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}


function redirect($url, $status_code = 302) {
    header("Location: $url", true, $status_code);
    exit();
}

/**
 * Get current URL
 * 
 * @return string Current URL
 */
function getCurrentURL() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Check if request is AJAX
 * 
 * @return bool True if AJAX request
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Check if request method is POST
 * 
 * @return bool True if POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request method is GET
 * 
 * @return bool True if GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIP() {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($keys as $key) {
        if (isset($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Debug dump and die
 * 
 * @param mixed $var Variable to dump
 * @param bool $die Whether to exit after dump
 */
function dd($var, $die = true) {
    echo '<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px;">';
    print_r($var);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}

/**
 * Convert array to CSV string
 * 
 * @param array $array Array to convert
 * @return string CSV string
 */
function arrayToCSV($array) {
    if (empty($array)) {
        return '';
    }
    
    $output = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($output, array_keys($array[0]));
    
    // Add data
    foreach ($array as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

/**
 * Generate random string
 * 
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Convert minutes to human-readable time
 * 
 * @param int $minutes Number of minutes
 * @return string Human-readable time
 */
function minutesToTime($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    $parts = [];
    
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    
    if ($mins > 0) {
        $parts[] = $mins . ' minute' . ($mins > 1 ? 's' : '');
    }
    
    return implode(' ', $parts);
}

/**
 * Case-insensitive array search
 * 
 * @param string $needle Value to search for
 * @param array $haystack Array to search in
 * @return bool True if found
 */
function inArrayCaseInsensitive($needle, $haystack) {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

/**
 * Strip HTML tags and truncate (alternative implementation)
 * 
 * @param string $html HTML content
 * @param int $length Maximum length
 * @return string Plain text excerpt
 */
function excerptFromHtml($html, $length = 160) {
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    $text = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($text, ' ');
    
    if ($lastSpace !== false) {
        $text = mb_substr($text, 0, $lastSpace);
    }
    
    return $text . '...';
}

/**
 * Get reading time estimate
 * 
 * @param string $content Article content
 * @param int $wordsPerMinute Average reading speed
 * @return int Estimated reading time in minutes
 */
function estimateReadingTime($content, $wordsPerMinute = 200) {
    $text = strip_tags($content);
    $wordCount = str_word_count($text);
    $minutes = ceil($wordCount / $wordsPerMinute);
    return max(1, $minutes); // Minimum 1 minute
}

/**
 * Parse tags from comma-separated string
 * 
 * @param string $tags Comma-separated tags
 * @return array Array of individual tags
 */
function parseTags($tags) {
    if (empty($tags)) {
        return [];
    }
    
    $tagArray = explode(',', $tags);
    $tagArray = array_map('trim', $tagArray);
    $tagArray = array_filter($tagArray); // Remove empty
    
    return $tagArray;
}

/**
 * Format tags for display
 * 
 * @param string $tags Comma-separated tags
 * @param string $baseUrl Base URL for tag links
 * @return string HTML formatted tags
 */
function formatTags($tags, $baseUrl = 'blog.php?tag=') {
    $tagArray = parseTags($tags);
    $html = '';
    
    foreach ($tagArray as $tag) {
        $slug = generateSlug($tag);
        $html .= '<a href="' . htmlspecialchars($baseUrl . $slug) . '" class="tag">' 
               . htmlspecialchars($tag) 
               . '</a> ';
    }
    
    return trim($html);
}

/**
 * Get related posts by category
 * 
 * @param mysqli $conn Database connection
 * @param int $postId Current post ID
 * @param string $category Post category
 * @param int $limit Number of posts to return
 * @return array Array of related posts
 */
function getRelatedPosts($conn, $postId, $category, $limit = 3) {
    $stmt = $conn->prepare("
        SELECT id, title, slug, excerpt, featured_image, published_at 
        FROM news_blog 
        WHERE category = ? 
        AND id != ? 
        AND status = 'published' 
        ORDER BY published_at DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("sii", $category, $postId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return $posts;
}

/**
 * Get popular posts
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Number of posts to return
 * @return array Array of popular posts
 */
function getPopularPosts($conn, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT id, title, slug, view_count, published_at 
        FROM news_blog 
        WHERE status = 'published' 
        ORDER BY view_count DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return $posts;
}

/**
 * Get recent posts
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Number of posts to return
 * @return array Array of recent posts
 */
function getRecentPosts($conn, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT id, title, slug, excerpt, featured_image, published_at 
        FROM news_blog 
        WHERE status = 'published' 
        ORDER BY published_at DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return $posts;
}


function incrementViewCount($conn, $postId) {
    $stmt = $conn->prepare("
        UPDATE news_blog 
        SET view_count = view_count + 1 
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $postId);
    return $stmt->execute();
}


function searchPosts($conn, $query, $limit = 10) {
    $searchTerm = '%' . $query . '%';
    
    $stmt = $conn->prepare("
        SELECT id, title, slug, excerpt, category, published_at 
        FROM news_blog 
        WHERE (title LIKE ? OR content LIKE ? OR tags LIKE ?) 
        AND status = 'published' 
        ORDER BY published_at DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return $posts;
}


function getAllCategories($conn) {
    $result = $conn->query("
        SELECT category, COUNT(*) as count 
        FROM news_blog 
        WHERE category IS NOT NULL 
        AND category != '' 
        AND status = 'published' 
        GROUP BY category 
        ORDER BY category
    ");
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}


function slugExists($conn, $slug, $excludeId = 0) {
    $stmt = $conn->prepare("
        SELECT id FROM news_blog 
        WHERE slug = ? AND id != ?
    ");
    
    $stmt->bind_param("si", $slug, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}


function generateUniqueSlug($conn, $baseSlug, $excludeId = 0) {
    $slug = generateSlug($baseSlug);
    $originalSlug = $slug;
    $counter = 1;
    
    while (slugExists($conn, $slug, $excludeId)) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}



function getFocusAreasMenu(mysqli $conn): array {
    $sql = "
        SELECT id, title, slug, icon, parent_id, display_order
        FROM focus_areas
        WHERE status = 'published' AND show_in_menu = 1
        ORDER BY display_order ASC, id ASC
    ";

    $result = $conn->query($sql);
    if (!$result) return [];

    $indexed = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize parent_id (some DBs store 0 instead of NULL)
        $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
        $row['children']  = [];
        $indexed[(int)$row['id']] = $row;
    }

    // Build hierarchy (no references needed)
    $menu = [];
    foreach ($indexed as $id => $area) {
        $pid = (int)$area['parent_id'];

        if ($pid === 0) {
            $menu[] = $area;
        } elseif (isset($indexed[$pid])) {
            $indexed[$pid]['children'][] = $area;
        }
    }

      $indexed2 = [];
    foreach ($indexed as $id => $area) {
        $area['children'] = [];
        $indexed2[$id] = $area;
    }
    foreach ($indexed2 as $id => $area) {
        $pid = (int)$area['parent_id'];
        if ($pid !== 0 && isset($indexed2[$pid])) {
            $indexed2[$pid]['children'][] = $area;
        }
    }
    $menu2 = [];
    foreach ($indexed2 as $id => $area) {
        if ((int)$area['parent_id'] === 0) $menu2[] = $area;
    }

    return $menu2;
}

function renderFocusAreasDropdown(mysqli $conn): string {
    $menu = getFocusAreasMenu($conn);
    if (empty($menu)) return '';

    $html  = "<li class=\"dropdown\">\n";
    $html .= "  <button type=\"button\" class=\"dropdown-toggle\" onclick=\"toggleDropdown(this)\">Focus Areas</button>\n";
    $html .= "  <div class=\"dropdown-menu\">\n";

    foreach ($menu as $parent) {
        $parentSlug = htmlspecialchars($parent['slug'] ?? '');
        $parentIcon = trim((string)($parent['icon'] ?? ''));
        $parentTitle= htmlspecialchars($parent['title'] ?? '');

        if (!empty($parent['children'])) {
            // parent as a normal link + children beneath it (simple, reliable)
            $html .= "    <a href=\"focus-area.php?slug={$parentSlug}\">"
                  . ($parentIcon ? "<i class=\"".htmlspecialchars($parentIcon)."\"></i> " : "")
                  . "{$parentTitle}</a>\n";

            foreach ($parent['children'] as $child) {
                $childSlug  = htmlspecialchars($child['slug'] ?? '');
                $childIcon  = trim((string)($child['icon'] ?? ''));
                $childTitle = htmlspecialchars($child['title'] ?? '');

                $html .= "    <a href=\"focus-area.php?slug={$childSlug}\" style=\"padding-left:22px;\">"
                      . ($childIcon ? "<i class=\"".htmlspecialchars($childIcon)."\"></i> " : "")
                      . "{$childTitle}</a>\n";
            }
        } else {
            $html .= "    <a href=\"focus-area.php?slug={$parentSlug}\">"
                  . ($parentIcon ? "<i class=\"".htmlspecialchars($parentIcon)."\"></i> " : "")
                  . "{$parentTitle}</a>\n";
        }
    }

    $html .= "  </div>\n";
    $html .= "</li>\n";

    return $html;
}


function safe_truncate(string $text, int $len = 100): string {
    $text = trim(strip_tags($text));
    if ($text === '') return '';
    if (function_exists('truncate')) return truncate($text, $len);
    return (mb_strlen($text) > $len) ? (mb_substr($text, 0, $len - 3) . '...') : $text;
}

function renderProgrammesMegaMenu(mysqli $conn): string {
    $sql = "
        SELECT id, title, slug, summary, icon, featured_image, display_order
        FROM programmes
        WHERE status = 'published' AND show_in_menu = 1
        ORDER BY display_order ASC, id ASC
    ";
    $result = $conn->query($sql);
    if (!$result) return '';

    $programmes = [];
    while ($row = $result->fetch_assoc()) $programmes[] = $row;
    if (empty($programmes)) return '';

    $html  = "<li class=\"mega-menu-item\">\n";
    $html .= "  <a href=\"programmes.php\" class=\"nav-link\">Programmes <i class=\"fas fa-chevron-down\" aria-hidden=\"true\"></i></a>\n";
    $html .= "  <div class=\"mega-menu-dropdown\">\n";
    $html .= "    <div class=\"mega-menu-container\">\n";
    $html .= "      <h3 class=\"mega-menu-title\">PROGRAMMES</h3>\n";
    $html .= "      <div class=\"mega-menu-grid\">\n";

    foreach ($programmes as $p) {
        $slug  = htmlspecialchars($p['slug'] ?? '');
        $title = htmlspecialchars($p['title'] ?? '');
        $icon  = trim((string)($p['icon'] ?? ''));
        $desc  = htmlspecialchars(safe_truncate((string)($p['summary'] ?? ''), 100));

        $imgFile = trim((string)($p['featured_image'] ?? ''));
        $imgUrl  = $imgFile ? ("uploads/programmes/" . rawurlencode($imgFile)) : '';

        $html .= "        <a href=\"programme.php?slug={$slug}\" class=\"mega-menu-card\">\n";
        $html .= "          <div class=\"mega-card-image\">\n";

        if ($imgUrl) {
            $html .= "            <img src=\"{$imgUrl}\" alt=\"{$title}\">\n";
        } else {
            $html .= "            <div class=\"mega-card-placeholder\">\n";
            if ($icon) $html .= "              <i class=\"".htmlspecialchars($icon)."\" aria-hidden=\"true\"></i>\n";
            $html .= "            </div>\n";
        }

        $html .= "          </div>\n";
        $html .= "          <div class=\"mega-card-content\">\n";
        $html .= "            <h4 class=\"mega-card-title\">{$title}</h4>\n";
        if ($desc !== '') $html .= "            <p class=\"mega-card-description\">{$desc}</p>\n";
        $html .= "            <span class=\"mega-card-link\">Learn More <i class=\"fas fa-arrow-right\" aria-hidden=\"true\"></i></span>\n";
        $html .= "          </div>\n";
        $html .= "        </a>\n";
    }

    $html .= "      </div>\n";
    $html .= "    </div>\n";
    $html .= "  </div>\n";
    $html .= "</li>\n";

    return $html;
}

function renderFocusAreasMegaMenu(mysqli $conn): string {
    $sql = "
        SELECT id, title, slug, description, icon, featured_image, parent_id, display_order
        FROM focus_areas
        WHERE status = 'published' AND show_in_menu = 1
        ORDER BY display_order ASC, id ASC
    ";
    $result = $conn->query($sql);
    if (!$result) return '';

    $areas = [];
    while ($row = $result->fetch_assoc()) $areas[] = $row;
    if (empty($areas)) return '';

    $areas = array_values(array_filter($areas, fn($a) => (int)($a['parent_id'] ?? 0) === 0));

    $html  = "<li class=\"mega-menu-item\">\n";
    $html .= "  <a href=\"focus-areas.php\" class=\"nav-link\">Focus Areas <i class=\"fas fa-chevron-down\" aria-hidden=\"true\"></i></a>\n";
    $html .= "  <div class=\"mega-menu-dropdown\">\n";
    $html .= "    <div class=\"mega-menu-container\">\n";
    $html .= "      <h3 class=\"mega-menu-title\">FOCUS AREAS</h3>\n";
    $html .= "      <div class=\"mega-menu-grid\">\n";

    foreach ($areas as $a) {
        $slug  = htmlspecialchars($a['slug'] ?? '');
        $title = htmlspecialchars($a['title'] ?? '');
        $icon  = trim((string)($a['icon'] ?? ''));
        $desc  = htmlspecialchars(safe_truncate((string)($a['description'] ?? ''), 100));

        // IMPORTANT: adjust path to your public root (no ../ inside header)
        $imgFile = trim((string)($a['featured_image'] ?? ''));
        $imgUrl  = $imgFile ? ("uploads/focus-areas/" . rawurlencode($imgFile)) : '';

        $html .= "        <a href=\"focus-area.php?slug={$slug}\" class=\"mega-menu-card\">\n";
        $html .= "          <div class=\"mega-card-image\">\n";

        if ($imgUrl) {
            $html .= "            <img src=\"{$imgUrl}\" alt=\"{$title}\">\n";
        } else {
            $html .= "            <div class=\"mega-card-placeholder\">\n";
            if ($icon) $html .= "              <i class=\"".htmlspecialchars($icon)."\" aria-hidden=\"true\"></i>\n";
            $html .= "            </div>\n";
        }

        $html .= "          </div>\n";
        $html .= "          <div class=\"mega-card-content\">\n";
        $html .= "            <h4 class=\"mega-card-title\">{$title}</h4>\n";
        if ($desc !== '') $html .= "            <p class=\"mega-card-description\">{$desc}</p>\n";
        $html .= "            <span class=\"mega-card-link\">Learn More <i class=\"fas fa-arrow-right\" aria-hidden=\"true\"></i></span>\n";
        $html .= "          </div>\n";
        $html .= "        </a>\n";
    }

    $html .= "      </div>\n";
    $html .= "    </div>\n";
    $html .= "  </div>\n";
    $html .= "</li>\n";

    return $html;
}
function getHeroImage(mysqli $conn, string $page_key): ?array
{
    $st = $conn->prepare(
        "SELECT image, alt_text, overlay_title, overlay_subtitle
         FROM   hero_images
         WHERE  page_key  = ?
           AND  is_active = 1
         LIMIT  1"
    );
    if (!$st) return null;

    $st->bind_param("s", $page_key);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row || empty($row['image'])) return null;

    return [
        'src'      => 'uploads/heroes/' . $row['image'],
        'alt'      => $row['alt_text']       ?? '',
        'title'    => $row['overlay_title']  ?? '',
        'subtitle' => $row['overlay_subtitle'] ?? '',
    ];
}


function renderLearningMegaMenu($conn) {
    $sql = "SELECT id, title, slug, description, featured_image, icon, level, category, price
            FROM learning 
            WHERE status = 'published' 
            ORDER BY display_order ASC, title ASC 
            LIMIT 8";
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) return '';
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    ob_start();
   }
?>