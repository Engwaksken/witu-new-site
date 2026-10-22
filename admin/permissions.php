<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

requirePermission($conn, 'manage_roles');

$page_title = 'Manage Permissions';

// Handle AJAX requests
if (isAjax() && isPost()) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_permission':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Permission name is required');
                }
                
                // Validate permission name format (lowercase with underscores)
                if (!preg_match('/^[a-z_]+$/', $name)) {
                    throw new Exception('Permission name must be lowercase with underscores only');
                }
                
                // Check if permission already exists
                $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Permission already exists');
                }
                
                $stmt = $conn->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission created successfully']);
                } else {
                    throw new Exception('Failed to create permission');
                }
                break;
                
            case 'edit_permission':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Permission name is required');
                }
                
                // Validate permission name format
                if (!preg_match('/^[a-z_]+$/', $name)) {
                    throw new Exception('Permission name must be lowercase with underscores only');
                }
                
                // Check if permission exists with different ID
                $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Permission name already exists');
                }
                
                $stmt = $conn->prepare("UPDATE permissions SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $description, $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
                } else {
                    throw new Exception('Failed to update permission');
                }
                break;
                
            case 'delete_permission':
                $id = intval($_POST['id'] ?? 0);
                
                // Check if permission is in use
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE permission_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot delete permission that is assigned to roles. Remove from all roles first.');
                }
                
                $stmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Permission deleted successfully']);
                } else {
                    throw new Exception('Failed to delete permission');
                }
                break;
                
            case 'update_role_permissions':
                $role_id = intval($_POST['role_id'] ?? 0);
                $permission_ids = $_POST['permissions'] ?? [];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Delete existing permissions for this role
                    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->bind_param("i", $role_id);
                    $stmt->execute();
                    
                    // Insert new permissions
                    if (!empty($permission_ids)) {
                        $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        foreach ($permission_ids as $permission_id) {
                            $permission_id = intval($permission_id);
                            $stmt->bind_param("ii", $role_id, $permission_id);
                            $stmt->execute();
                        }
                    }
                    
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Role permissions updated successfully']);
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;
                
            case 'add_role':
                $name = trim($_POST['role_name'] ?? '');
                $description = trim($_POST['role_description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Role name is required');
                }
                
                // Check if role already exists
                $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Role already exists');
                }
                
                $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Role created successfully']);
                } else {
                    throw new Exception('Failed to create role');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get all roles
$roles = [];
$result = $conn->query("SELECT * FROM roles ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Get all permissions
$permissions = [];
$result = $conn->query("SELECT * FROM permissions ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $permissions[] = $row;
}

// Get role permissions mapping
$role_permissions = [];
foreach ($roles as $role) {
    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $role_permissions[$role['id']] = [];
    while ($row = $result->fetch_assoc()) {
        $role_permissions[$role['id']][] = $row['permission_id'];
    }
}

// Calculate statistics
$total_assignments = 0;
foreach ($role_permissions as $perms) {
    $total_assignments += count($perms);
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
    gap: 12px;
}

.permission-card {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    border-left: 3px solid #f86505;
    transition: all 0.3s ease;
    position: relative;
}

.permission-card:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
    background: #eff6ff;
}

.permission-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 6px;
}

.permission-card h4 {
    margin: 0;
    color: #1f2937;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

.permission-card p {
    margin: 0;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.permission-actions {
    display: flex;
    gap: 4px;
}

.permission-meta {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
    font-size: 11px;
    color: #6b7280;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.role-card {
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 15px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.role-card:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
}

.role-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f3f4f6;
}

.role-name {
    display: flex;
    align-items: center;
    gap: 8px;
}

.role-name h4 {
    margin: 0;
    color: #1f2937;
    font-size: 16px;
}

.role-badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-system {
    background: #dbeafe;
    color: #1e40af;
}

.badge-custom {
    background: #fef3c7;
    color: #92400e;
}

.permission-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr));
    gap: 10px;
    margin-top: 15px;
}

.permission-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background: #f9fafb;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.permission-checkbox-label:hover {
    background: #e0e7ff;
}

.permission-checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    padding: 18px;
    border-radius: 10px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: #ffffff;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-large {
    max-width: 900px;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid #f3f4f6;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 20px;
}

.modal-close {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: #1f2937;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 2px solid #f3f4f6;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group small {
    display: block;
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
}

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s;
}

@keyframes slideIn {
    from {
        transform: translateX(-20px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 2px solid #10b981;
}

.alert-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 2px solid #ef4444;
}

.alert i {
    font-size: 20px;
}

.btn-icon {
    padding: 6px 10px;
    font-size: 12px;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.data-table thead {
    background: #f9fafb;
}

.data-table th {
    padding: 14px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 14px;
    border-top: 1px solid #f3f4f6;
}

.data-table tbody tr:hover {
    background: #f9fafb;
}

/* Permission Matrix Grid */
.permission-matrix-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
    gap: 15px;
}

.matrix-role-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    transition: all 0.2s;
}

.matrix-role-card:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    border-color: #f86505;
}

.matrix-role-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}

.matrix-role-header h4 {
    margin: 0;
    font-size: 15px;
    color: #1f2937;
}

.matrix-permissions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
    min-height: 60px;
    align-items: flex-start;
}

.matrix-permission-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    background: #e5e7eb;
    color: #6b7280;
    transition: all 0.2s;
}

.matrix-permission-badge.active {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.matrix-permission-badge.active i {
    color: #10b981;
}

.matrix-permission-badge:hover {
    transform: translateY(-1px);
}

.matrix-role-footer {
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}

@keyframes countUp {
    from { 
        opacity: 0; 
        transform: scale(0.5);
    }
    to { 
        opacity: 1; 
        transform: scale(1);
    }
}

.stat-value {
    animation: countUp 0.5s ease-out;
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(20px);
        opacity: 0;
    }
}
</style>

<div class="content-header">
    <h2><i class="fas fa-shield-alt" style="color: #f86505;"></i> Permissions Management</h2>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="openAddPermissionModal()">
            <i class="fas fa-plus"></i> Add Permission
        </button>
        <button class="btn" onclick="openAddRoleModal()">
            <i class="fas fa-user-plus"></i> Add Role
        </button>
    </div>
</div>

<div id="alertContainer"></div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card" style="background: linear-gradient(135deg, #f86505 0%, #f86505a8 100%);">
        <div class="stat-value" style="color: white;"><?php echo count($roles); ?></div>
        <div class="stat-label" style="color: rgba(255,255,255,0.9);">Total Roles</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        <div class="stat-value" style="color: white;"><?php echo count($permissions); ?></div>
        <div class="stat-label" style="color: rgba(255,255,255,0.9);">Total Permissions</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
        <div class="stat-value" style="color: white;"><?php echo $total_assignments; ?></div>
        <div class="stat-label" style="color: rgba(255,255,255,0.9);">Total Assignments</div>
    </div>
</div>

<!-- Available Permissions -->
<div class="card">
    <h3 style="margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #e5e7eb;">
        <i class="fas fa-key" style="color: #10b981;"></i> Available Permissions
    </h3>
    
    <div class="permission-grid">
        <?php foreach ($permissions as $permission): ?>
        <div class="permission-card" data-permission-id="<?php echo $permission['id']; ?>">
            <div class="permission-card-header">
                <h4><?php echo htmlspecialchars($permission['name']); ?></h4>
                <div class="permission-actions">
                    <button class="btn btn-sm btn-primary btn-icon" 
                            onclick='editPermission(<?php echo json_encode($permission); ?>)'
                            title="Edit Permission">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-icon" 
                            onclick="deletePermission(<?php echo $permission['id']; ?>, '<?php echo addslashes($permission['name']); ?>')"
                            title="Delete Permission">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            <p><?php echo htmlspecialchars($permission['description']); ?></p>
            <div class="permission-meta">
                <i class="fas fa-users" style="color: #6b7280;"></i> Used by:
                <?php
                $used_by = [];
                foreach ($roles as $role) {
                    if (in_array($permission['id'], $role_permissions[$role['id']] ?? [])) {
                        $used_by[] = htmlspecialchars($role['name']);
                    }
                }
                echo !empty($used_by) ? implode(', ', $used_by) : '<span style="color: #9ca3af;">None</span>';
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Role Permissions Management -->
<div class="card" style="margin-top: 20px;">
    <h3 style="margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #e5e7eb;">
        <i class="fas fa-users-cog" style="color: #f86505;"></i> Role Permissions
    </h3>
    
    <?php foreach ($roles as $role): ?>
    <div class="role-card">
        <div class="role-card-header">
            <div class="role-name">
                <h4><?php echo htmlspecialchars($role['name']); ?></h4>
                <?php if (in_array($role['name'], ['Administrator', 'Editor', 'Author', 'Viewer'])): ?>
                    <span class="role-badge badge-system">System Role</span>
                <?php else: ?>
                    <span class="role-badge badge-custom">Custom Role</span>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" onclick="editRolePermissions(<?php echo $role['id']; ?>)">
                <i class="fas fa-edit"></i> Edit Permissions
            </button>
        </div>
        <p style="color: #6b7280; margin-bottom: 10px; font-size: 13px;"><?php echo htmlspecialchars($role['description']); ?></p>
        
        <div>
            <strong style="display: block; margin-bottom: 8px; color: #374151; font-size: 13px;">
                <i class="fas fa-check-circle" style="color: #10b981;"></i> Assigned Permissions:
            </strong>
            <?php
            $role_perms = [];
            foreach ($permissions as $perm) {
                if (in_array($perm['id'], $role_permissions[$role['id']] ?? [])) {
                    $role_perms[] = '<span class="badge badge-active" style="margin: 3px;">' . htmlspecialchars($perm['name']) . '</span>';
                }
            }
            echo !empty($role_perms) ? implode(' ', $role_perms) : '<span style="color: #9ca3af; font-style: italic;">No permissions assigned</span>';
            ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Permission Matrix -->
<div class="card" style="margin-top: 20px;">
    <h3 style="margin-bottom: 15px;">
        <i class="fas fa-table" style="color: #f59e0b;"></i> Permission Matrix
        <span style="font-size: 13px; color: #6b7280; font-weight: normal; margin-left: 10px;">Quick Overview</span>
    </h3>
    
    <div class="permission-matrix-grid">
        <?php foreach ($roles as $role): ?>
        <div class="matrix-role-card">
            <div class="matrix-role-header">
                <h4><?php echo htmlspecialchars($role['name']); ?></h4>
                <?php if (in_array($role['name'], ['Administrator', 'Editor', 'Author', 'Viewer'])): ?>
                    <span class="role-badge badge-system">System</span>
                <?php else: ?>
                    <span class="role-badge badge-custom">Custom</span>
                <?php endif; ?>
            </div>
            <div class="matrix-permissions">
                <?php 
                $assigned_count = 0;
                foreach ($permissions as $perm): 
                    if (in_array($perm['id'], $role_permissions[$role['id']] ?? [])):
                        $assigned_count++;
                ?>
                    <span class="matrix-permission-badge active" title="<?php echo htmlspecialchars($perm['description']); ?>">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($perm['name']); ?>
                    </span>
                <?php 
                    endif;
                endforeach; 
                ?>
                <?php if ($assigned_count === 0): ?>
                    <span style="color: #9ca3af; font-size: 12px; font-style: italic;">No permissions assigned</span>
                <?php endif; ?>
            </div>
            <div class="matrix-role-footer">
                <span style="font-size: 12px; color: #6b7280;">
                    <i class="fas fa-key"></i> <?php echo $assigned_count; ?> of <?php echo count($permissions); ?> permissions
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Permission Modal -->
<div id="permissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="permissionModalTitle">Add New Permission</h3>
            <button class="modal-close" onclick="closePermissionModal()">&times;</button>
        </div>
        <form id="permissionForm" onsubmit="handlePermissionSubmit(event)">
            <div class="modal-body">
                <input type="hidden" id="permissionId" name="id" value="">
                <input type="hidden" id="permissionAction" name="action" value="add_permission">
                
                <div class="form-group">
                    <label for="permissionName">Permission Name *</label>
                    <input type="text" 
                           id="permissionName" 
                           name="name" 
                           required 
                           placeholder="e.g., manage_users"
                           pattern="[a-z_]+"
                           title="Use lowercase letters and underscores only">
                    <small>Use lowercase with underscores (e.g., manage_content, view_reports)</small>
                </div>
                
                <div class="form-group">
                    <label for="permissionDescription">Description *</label>
                    <textarea id="permissionDescription" 
                              name="description" 
                              required 
                              placeholder="Describe what this permission allows..."></textarea>
                    <small>Be specific about what actions this permission grants</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closePermissionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Permission
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Role Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Role</h3>
            <button class="modal-close" onclick="closeRoleModal()">&times;</button>
        </div>
        <form id="roleForm" onsubmit="handleRoleSubmit(event)">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_role">
                
                <div class="form-group">
                    <label for="roleName">Role Name *</label>
                    <input type="text" 
                           id="roleName" 
                           name="role_name" 
                           required 
                           placeholder="e.g., Content Manager">
                </div>
                
                <div class="form-group">
                    <label for="roleDescription">Description *</label>
                    <textarea id="roleDescription" 
                              name="role_description" 
                              required 
                              placeholder="Describe this role's purpose and responsibilities..."></textarea>
                    <small>Note: You can assign permissions to the role after creation</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeRoleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Role
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Permissions Modal -->
<div id="rolePermissionsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-user-shield"></i> Edit Role Permissions</h3>
            <button class="modal-close" onclick="closeRolePermissionsModal()">&times;</button>
        </div>
        <form id="rolePermissionsForm" onsubmit="handleRolePermissionsSubmit(event)">
            <input type="hidden" name="action" value="update_role_permissions">
            <input type="hidden" name="role_id" id="edit_role_id">
            <div class="modal-body">
                <h4 id="edit_role_name" style="margin-bottom: 20px; color: #f86505; font-size: 18px;"></h4>
                
                <div class="permission-checkboxes">
                    <?php foreach ($permissions as $permission): ?>
                    <label class="permission-checkbox-label">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="<?php echo $permission['id']; ?>" 
                               class="permission-checkbox"
                               data-permission-id="<?php echo $permission['id']; ?>">
                        <span>
                            <strong style="display: block; font-size: 13px;"><?php echo htmlspecialchars($permission['name']); ?></strong>
                            <small style="color: #6b7280; font-size: 11px;"><?php echo htmlspecialchars(substr($permission['description'], 0, 60)); ?><?php echo strlen($permission['description']) > 60 ? '...' : ''; ?></small>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeRolePermissionsModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Permissions
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Role permissions data
const rolePermissions = <?php echo json_encode($role_permissions); ?>;
const roles = <?php echo json_encode($roles); ?>;

// Alert Functions
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    const container = document.getElementById('alertContainer');
    container.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s';
        setTimeout(() => alertDiv.remove(), 300);
    }, 4000);
}

// Permission Modal Functions
function openAddPermissionModal() {
    document.getElementById('permissionModalTitle').textContent = 'Add New Permission';
    document.getElementById('permissionId').value = '';
    document.getElementById('permissionAction').value = 'add_permission';
    document.getElementById('permissionName').value = '';
    document.getElementById('permissionDescription').value = '';
    document.getElementById('permissionModal').style.display = 'flex';
}

function editPermission(permission) {
    document.getElementById('permissionModalTitle').textContent = 'Edit Permission';
    document.getElementById('permissionId').value = permission.id;
    document.getElementById('permissionAction').value = 'edit_permission';
    document.getElementById('permissionName').value = permission.name;
    document.getElementById('permissionDescription').value = permission.description;
    document.getElementById('permissionModal').style.display = 'flex';
}

function closePermissionModal() {
    document.getElementById('permissionModal').style.display = 'none';
    document.getElementById('permissionForm').reset();
}

function handlePermissionSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closePermissionModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
}

function deletePermission(id, name) {
    if (!confirm(`Are you sure you want to delete the permission "${name}"?\n\nThis will remove it from all roles that have it assigned.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_permission');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Role Modal Functions
function openAddRoleModal() {
    document.getElementById('roleName').value = '';
    document.getElementById('roleDescription').value = '';
    document.getElementById('roleModal').style.display = 'flex';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    document.getElementById('roleForm').reset();
}

function handleRoleSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeRoleModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Role Permissions Modal Functions
function editRolePermissions(roleId) {
    document.getElementById('edit_role_id').value = roleId;
    
    const role = roles.find(r => r.id == roleId);
    document.getElementById('edit_role_name').innerHTML = `<i class="fas fa-user"></i> ${role.name}`;
    
    // Uncheck all checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    
    // Check boxes for role's permissions
    const permissions = rolePermissions[roleId] || [];
    permissions.forEach(permId => {
        const checkbox = document.querySelector(`input[data-permission-id="${permId}"]`);
        if (checkbox) checkbox.checked = true;
    });
    
    document.getElementById('rolePermissionsModal').style.display = 'flex';
}

function closeRolePermissionsModal() {
    document.getElementById('rolePermissionsModal').style.display = 'none';
}

function handleRolePermissionsSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeRolePermissionsModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
});
</script>

<?php include 'layout/footer.php'; ?>