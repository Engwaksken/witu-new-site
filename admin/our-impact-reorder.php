<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

header('Content-Type: application/json; charset=utf-8');

$has_permission = false;
if (function_exists('hasPermission')) {
  if (hasPermission($conn, 'manage_impact')) $has_permission = true;
  elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else { $has_permission = true; }

if (!$has_permission) {
  echo json_encode(['success'=>false,'message'=>'Not authorized']); exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['items']) || !is_array($data['items'])) {
  echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit;
}

$conn->begin_transaction();
try {
  $st = $conn->prepare("UPDATE impact_stats SET sort_order=? WHERE id=?");
  foreach ($data['items'] as $item) {
    $id = (int)($item['id'] ?? 0);
    $sort = (int)($item['sort_order'] ?? 0);
    if ($id > 0) {
      $st->bind_param("ii", $sort, $id);
      $st->execute();
    }
  }
  $conn->commit();
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success'=>false,'message'=>'Failed to save order']);
}
