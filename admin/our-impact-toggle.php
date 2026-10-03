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

$id = (int)($_POST['id'] ?? 0);
$is_active = (int)($_POST['is_active'] ?? 0);

if ($id <= 0) {
  echo json_encode(['success'=>false,'message'=>'Invalid id']); exit;
}

$st = $conn->prepare("UPDATE impact_stats SET is_active=? WHERE id=?");
$st->bind_param("ii", $is_active, $id);
$ok = $st->execute();

echo json_encode(['success'=>$ok ? true : false]);
