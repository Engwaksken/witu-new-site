<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method: ' . $_SERVER['REQUEST_METHOD'], 405);
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    respond(false, 'Email address is required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.');
}

if (!isset($conn) || !$conn instanceof mysqli) {
    respond(false, 'Database connection not found.', 500);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $check = $conn->prepare("
        SELECT id, status
        FROM newsletter_subscribers
        WHERE email = ?
        LIMIT 1
    ");

    if (!$check) {
        respond(false, 'Prepare failed: ' . $conn->error, 500);
    }

    $check->bind_param('s', $email);
    $check->execute();

    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'active') {
            respond(true, 'You are already subscribed.');
        }

        $update = $conn->prepare("
            UPDATE newsletter_subscribers
            SET status = 'active',
                updated_at = NOW()
            WHERE id = ?
        ");

        if (!$update) {
            respond(false, 'Update prepare failed: ' . $conn->error, 500);
        }

        $id = (int)$existing['id'];
        $update->bind_param('i', $id);
        $update->execute();

        respond(true, 'Subscription reactivated successfully.');
    }

    $insert = $conn->prepare("
        INSERT INTO newsletter_subscribers
            (email, ip_address, status)
        VALUES
            (?, ?, 'active')
    ");

    if (!$insert) {
        respond(false, 'Insert prepare failed: ' . $conn->error, 500);
    }

    $insert->bind_param('ss', $email, $ip);
    $insert->execute();

    respond(true, 'Thank you for subscribing.');

} catch (Throwable $e) {
    respond(false, 'Server error: ' . $e->getMessage(), 500);
}