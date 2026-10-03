<?php
declare(strict_types=1);

define('SITE_NAME', 'WITU');

$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

$scheme = $isHttps ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'www.witu.org';

define('SITE_URL', rtrim($scheme . $host, '/'));

define('PUBLIC_URL', SITE_URL);
define('ADMIN_URL', SITE_URL . '/admin');


define('ROOT_PATH', realpath(__DIR__ . '/..'));

define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('IMAGE_UPLOAD_PATH', UPLOAD_PATH . 'images/');
define('DOCUMENT_UPLOAD_PATH', UPLOAD_PATH . 'documents/');
define('TEMP_UPLOAD_PATH', UPLOAD_PATH . 'temp/');
define('LOG_PATH', ROOT_PATH . '/logs/');
define('CACHE_PATH', ROOT_PATH . '/cache/');


define('MAX_FILE_SIZE', 5 * 1024 * 1024);       // 5 MB
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);     // 10 MB
define('MAX_DOCUMENT_SIZE', 20 * 1024 * 1024);  // 20 MB

define('ALLOWED_IMAGES', [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'svg'
]);

define('ALLOWED_DOCUMENTS', [
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'ppt',
    'pptx',
    'csv',
    'txt',
    'zip'
]);

define('ALLOWED_VIDEO', [
    'mp4',
    'webm',
    'mov'
]);

define('ITEMS_PER_PAGE', 10);
define('ADMIN_ITEMS_PER_PAGE', 25);

define('SESSION_TIMEOUT', 3600); // 1 Hour

date_default_timezone_set('Africa/Kampala');

define('PASSWORD_COST', 12);
define('CSRF_TOKEN_LENGTH', 64);

define('CACHE_TIME', 3600);


define('APP_VERSION', '1.0.0');