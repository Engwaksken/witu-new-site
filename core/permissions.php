<?php
require_once __DIR__ . '/auth.php';

// Check if user has permission
function hasPermission($conn, $permission_name) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.name = ?
    ");
    $stmt->bind_param("is", $_SESSION['user_role'], $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

// Require specific permission
function requirePermission($conn, $permission_name) {
    requireLogin();
    
    if (!hasPermission($conn, $permission_name)) {
        die("Access denied. You don't have permission to access this resource.");
    }
}

// Get all permissions for a role
function getRolePermissions($conn, $role_id) {
    $stmt = $conn->prepare("
        SELECT p.* 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    return $permissions;
}

// Check if user is admin
function isAdmin($conn) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_role']);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    
    return strtolower($role['name']) === 'admin' || strtolower($role['name']) === 'administrator';
}
?>