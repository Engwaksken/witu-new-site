<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type']) || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$type = $input['type'];
$id = (int)$input['id'];

// Validate type
$allowedTypes = ['annual-report', 'publication', 'resource'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid download type']);
    exit;
}

// Map type to table
$tableMap = [
    'annual-report' => 'annual_reports',
    'publication' => 'publications',
    'resource' => 'resources'
];

$table = $tableMap[$type];

try {
    // Update download count
    $stmt = $conn->prepare("UPDATE {$table} SET download_count = download_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Get updated count
        $stmt = $conn->prepare("SELECT download_count FROM {$table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Log download (optional - create downloads_log table if you want detailed tracking)
        $logStmt = $conn->prepare("INSERT INTO downloads_log (item_type, item_id, download_date, ip_address, user_agent) VALUES (?, ?, NOW(), ?, ?)");
        if ($logStmt) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $logStmt->bind_param("siss", $type, $id, $ipAddress, $userAgent);
            $logStmt->execute();
            $logStmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'download_count' => $row['download_count'],
            'message' => 'Download tracked successfully'
        ]);
    } else {
        throw new Exception('Failed to update download count');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error tracking download: ' . $e->getMessage()
    ]);
}

$conn->close();
?>