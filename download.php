<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf; // (kept because you requested autoload dompdf, even though we stream the stored PDF)

/*
  Supports:
    - download.php?type=newsletter&id=123
    - download.php?type=annual-report&id=456

  Unique-by-IP tracking tables expected:
    - newsletter_downloads(newsletter_id, ip_address, user_agent) UNIQUE(newsletter_id, ip_address)
    - annual_report_downloads(report_id, ip_address, user_agent) UNIQUE(report_id, ip_address)
*/

$type = strtolower(trim($_GET['type'] ?? ''));
$id   = (int)($_GET['id'] ?? 0);

$allowed = ['newsletter', 'annual-report'];
if ($id <= 0 || !in_array($type, $allowed, true)) {
    http_response_code(400);
    exit('Invalid request.');
}

/* ---- Get IP + UA (basic) ---- */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if ($ip === '') $ip = '0.0.0.0';

// If behind proxy / Cloudflare, uncomment if you trust the header:
// $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $ip);
// if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

/* ---- Config by type ---- */
if ($type === 'newsletter') {
    $selectSql   = "SELECT id, title, pdf_file FROM newsletters WHERE id=? AND status='published' LIMIT 1";
    $uploadDir   = __DIR__ . '/uploads/newsletters/';
    $logSql      = "INSERT IGNORE INTO newsletter_downloads (newsletter_id, ip_address, user_agent) VALUES (?,?,?)";
    $updateSql   = "UPDATE newsletters SET download_count = download_count + 1 WHERE id=?";
    $idBindType  = "i";  // for SELECT/UPDATE
    $logBindType = "iss"; // (id, ip, ua)
    $filenameFallback = "newsletter";
} else { // annual-report
    $selectSql   = "SELECT id, title, pdf_file FROM annual_reports WHERE id=? AND status='published' LIMIT 1";
    $uploadDir   = __DIR__ . '/uploads/annual-reports/';
    $logSql      = "INSERT IGNORE INTO annual_report_downloads (report_id, ip_address, user_agent) VALUES (?,?,?)";
    $updateSql   = "UPDATE annual_reports SET download_count = download_count + 1 WHERE id=?";
    $idBindType  = "i";
    $logBindType = "iss";
    $filenameFallback = "annual-report";
}

/* ---- Fetch record ---- */
$stmt = $conn->prepare($selectSql);
if (!$stmt) {
    http_response_code(500);
    exit('Database error.');
}
$stmt->bind_param($idBindType, $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(404);
    exit('Item not found.');
}

/* ---- File path ---- */
$pdfFile = (string)($item['pdf_file'] ?? '');
$filePath = $uploadDir . $pdfFile;

if ($pdfFile === '' || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

/* ---- Unique-by-IP logging ---- */
$ins = $conn->prepare($logSql);
if ($ins) {
    $ins->bind_param($logBindType, $id, $ip, $userAgent);
    $ins->execute();

    // increment only if new unique ip for this item
    if ($ins->affected_rows === 1) {
        $up = $conn->prepare($updateSql);
        if ($up) {
            $up->bind_param($idBindType, $id);
            $up->execute();
            $up->close();
        }
    }
    $ins->close();
}

/* ---- Stream download ---- */
$title = trim((string)($item['title'] ?? ''));
$safeTitle = preg_replace('/[^a-zA-Z0-9\-_\. ]+/', '', ($title !== '' ? $title : $filenameFallback));
$downloadName = $safeTitle . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$downloadName.'"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
