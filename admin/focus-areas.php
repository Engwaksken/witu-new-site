<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

/* -------------------------------------------------------
   Permission check
------------------------------------------------------- */
$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_focus_areas')) $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage focus areas. Please contact your administrator to assign the "manage_focus_areas" or "manage_content" permission.</p>');
}

$page_title = 'Manage Focus Areas';

/* -------------------------------------------------------
   Helpers
------------------------------------------------------- */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Bind params dynamically.
 */
function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bind = [];
    $bind[] = $types;

    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

/* -------------------------------------------------------
   Handle POST actions
------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);

        $title            = trim(clean($conn, $_POST['title'] ?? ''));
        $slug             = trim(clean($conn, $_POST['slug'] ?? ''));
        $link             = trim(clean($conn, $_POST['link'] ?? ''));
        $parent_id_raw    = $_POST['parent_id'] ?? '';
        $parent_id        = ($parent_id_raw === '' ? null : (int)$parent_id_raw);
        $description      = trim(clean($conn, $_POST['description'] ?? ''));
        $content          = $_POST['content'] ?? ''; // keep HTML
        $outcome          = trim(clean($conn, $_POST['outcome'] ?? ''));
        $icon             = trim(clean($conn, $_POST['icon'] ?? ''));
        $display_order    = (int)($_POST['display_order'] ?? 0);
        $status           = trim(clean($conn, $_POST['status'] ?? 'published'));
        $show_in_menu     = isset($_POST['show_in_menu']) ? 1 : 0;
        $meta_title       = trim(clean($conn, $_POST['meta_title'] ?? ''));
        $meta_description = trim(clean($conn, $_POST['meta_description'] ?? ''));

        if ($title === '') {
            setFlashMessage('error', 'Title is required.');
            header('Location: focus-areas.php');
            exit;
        }

        if ($slug === '') {
            $slug = generateSlug($title);
        } else {
            $slug = generateSlug($slug);
        }

        if ($link !== '') {
            if (!preg_match('~^(https?://|/|[a-zA-Z0-9_\-]+\.php)~', $link)) {
                $link = 'https://' . $link;
            }
        }

        $featured_image = '';

        if (isset($_FILES['featured_image']) && (int)$_FILES['featured_image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['featured_image'], 'focus-areas');
            if (!empty($upload_result['success'])) {
                $featured_image = $upload_result['filename'];
            }
        }

        if ($id > 0) {
            if ($parent_id === $id) {
                setFlashMessage('error', 'A focus area cannot be its own parent.');
                header('Location: focus-areas.php');
                exit;
            }

            if ($featured_image !== '') {
                $oldStmt = $conn->prepare("SELECT featured_image FROM focus_areas WHERE id = ?");
                $oldStmt->bind_param("i", $id);
                $oldStmt->execute();
                $oldRow = $oldStmt->get_result()->fetch_assoc();
                $oldStmt->close();

                if ($oldRow && !empty($oldRow['featured_image'])) {
                    deleteFile($oldRow['featured_image'], 'focus-areas');
                }

                $sql = "UPDATE focus_areas
                        SET title = ?, slug = ?, link = ?, parent_id = ?, description = ?, content = ?, outcome = ?,
                            featured_image = ?, icon = ?, display_order = ?, status = ?, show_in_menu = ?,
                            meta_title = ?, meta_description = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssisssssisisii",
                    $title,
                    $slug,
                    $link,
                    $parent_id,
                    $description,
                    $content,
                    $outcome,
                    $featured_image,
                    $icon,
                    $display_order,
                    $status,
                    $show_in_menu,
                    $meta_title,
                    $meta_description,
                    $id
                );
            } else {
                $sql = "UPDATE focus_areas
                        SET title = ?, slug = ?, link = ?, parent_id = ?, description = ?, content = ?, outcome = ?,
                            icon = ?, display_order = ?, status = ?, show_in_menu = ?, meta_title = ?, meta_description = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssissssisisii",
                    $title,
                    $slug,
                    $link,
                    $parent_id,
                    $description,
                    $content,
                    $outcome,
                    $icon,
                    $display_order,
                    $status,
                    $show_in_menu,
                    $meta_title,
                    $meta_description,
                    $id
                );
            }

            if (!$stmt->execute()) {
                setFlashMessage('error', 'Failed to update focus area: ' . $stmt->error);
            } else {
                setFlashMessage('success', 'Focus area updated successfully.');
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO focus_areas
                (title, slug, link, parent_id, description, content, outcome, featured_image, icon,
                 display_order, status, show_in_menu, meta_title, meta_description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $created_by = (int)($_SESSION['user_id'] ?? 0);

            $stmt->bind_param(
                "sssisssssisisii",
                $title,
                $slug,
                $link,
                $parent_id,
                $description,
                $content,
                $outcome,
                $featured_image,
                $icon,
                $display_order,
                $status,
                $show_in_menu,
                $meta_title,
                $meta_description,
                $created_by
            );

            if (!$stmt->execute()) {
                setFlashMessage('error', 'Failed to create focus area: ' . $stmt->error);
            } else {
                setFlashMessage('success', 'Focus area created successfully.');
            }
            $stmt->close();
        }

        header('Location: focus-areas.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $checkStmt = $conn->prepare("SELECT COUNT(*) AS total FROM focus_areas WHERE parent_id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!empty($checkRes['total'])) {
            setFlashMessage('error', 'Cannot delete a focus area that has child focus areas.');
            header('Location: focus-areas.php');
            exit;
        }

        $imgStmt = $conn->prepare("SELECT featured_image FROM focus_areas WHERE id = ?");
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute();
        $imgRow = $imgStmt->get_result()->fetch_assoc();
        $imgStmt->close();

        if ($imgRow && !empty($imgRow['featured_image'])) {
            deleteFile($imgRow['featured_image'], 'focus-areas');
        }

        $delStmt = $conn->prepare("DELETE FROM focus_areas WHERE id = ?");
        $delStmt->bind_param("i", $id);

        if (!$delStmt->execute()) {
            setFlashMessage('error', 'Failed to delete focus area: ' . $delStmt->error);
        } else {
            setFlashMessage('success', 'Focus area deleted successfully.');
        }
        $delStmt->close();

        header('Location: focus-areas.php');
        exit;
    }
}

/* -------------------------------------------------------
   Filters + Search + Pagination
------------------------------------------------------- */
$search            = trim($_GET['search'] ?? '');
$filter_parent_id  = trim($_GET['parent_filter'] ?? '');
$page              = max(1, (int)($_GET['page'] ?? 1));
$per_page          = 10;
$offset            = ($page - 1) * $per_page;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = "(fa.title LIKE ? OR fa.slug LIKE ? OR fa.description LIKE ? OR fa.outcome LIKE ? OR parent.title LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'sssss';
}

if ($filter_parent_id !== '') {
    if ($filter_parent_id === 'top') {
        $where[] = "fa.parent_id IS NULL";
    } else {
        $where[] = "fa.parent_id = ?";
        $params[] = (int)$filter_parent_id;
        $types .= 'i';
    }
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

/* -------------------------------------------------------
   Count total rows
------------------------------------------------------- */
$countSql = "
    SELECT COUNT(*) AS total
    FROM focus_areas fa
    LEFT JOIN focus_areas parent ON parent.id = fa.parent_id
    $where_sql
";

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($params)) {
        $countParams = $params;
        bindDynamicParams($countStmt, $types, $countParams);
    }
    $countStmt->execute();
    $countRes = $countStmt->get_result()->fetch_assoc();
    $total_rows = (int)($countRes['total'] ?? 0);
    $countStmt->close();
} else {
    $total_rows = 0;
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

/* -------------------------------------------------------
   Fetch paginated rows
------------------------------------------------------- */
$listSql = "
    SELECT
        fa.*,
        parent.title AS parent_title,
        (
            SELECT COUNT(*)
            FROM focus_areas child
            WHERE child.parent_id = fa.id
        ) AS children_count
    FROM focus_areas fa
    LEFT JOIN focus_areas parent ON parent.id = fa.parent_id
    $where_sql
    ORDER BY
        CASE WHEN fa.parent_id IS NULL THEN 0 ELSE 1 END,
        fa.display_order ASC,
        fa.created_at DESC
    LIMIT ? OFFSET ?
";

$listStmt = $conn->prepare($listSql);
$areas = [];

if ($listStmt) {
    $listParams = $params;
    $listParams[] = $per_page;
    $listParams[] = $offset;
    $listTypes = $types . 'ii';

    bindDynamicParams($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $res = $listStmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $areas[] = $row;
    }
    $listStmt->close();
}

/* -------------------------------------------------------
   Parent dropdown options
------------------------------------------------------- */
$parents = [];
$parentRes = $conn->query("SELECT id, title FROM focus_areas WHERE parent_id IS NULL ORDER BY display_order ASC, title ASC");
if ($parentRes) {
    while ($row = $parentRes->fetch_assoc()) {
        $parents[] = $row;
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.focus-area-preview{display:flex;align-items:flex-start;gap:12px;}
.focus-area-icon{width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f86505 0%,#f86505a8 100%);border-radius:8px;color:#fff;font-size:18px;flex-shrink:0;}
.focus-area-thumb{width:60px;height:40px;object-fit:cover;border-radius:6px;border:2px solid #e5e7eb;flex-shrink:0;}
.hierarchy-indicator{color:#9ca3af;margin-right:8px;font-size:16px;}
.badge-parent{background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px;}
.badge-child{background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;margin-left:8px;}
.filter-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:16px;}
.filter-bar .form-group{margin-bottom:0;min-width:220px;}
.pagination-wrap{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 0;flex-wrap:wrap;}
.pagination{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
.pagination a,.pagination span{padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px;text-decoration:none;color:#374151;background:#fff;}
.pagination a:hover{background:#f9fafb;}
.pagination .active{background:#f86505;color:#fff;border-color:#f86505;}
.table-meta{font-size:12px;color:#6b7280;}
.outcome-box{margin-top:6px;padding:8px 10px;background:#f9fafb;border-left:3px solid #f86505;border-radius:4px;font-size:12px;color:#374151;}
</style>

<div class="content-header">
    <h2><i class="fas fa-bullseye" style="color:#f86505;"></i> Manage Focus Areas</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Focus Area
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo e($flash['type']); ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo e($flash['message']); ?>
</div>
<?php endif; ?>

<div class="card" style="padding:16px;margin-bottom:16px;">
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Search</label>
            <input
                type="text"
                name="search"
                value="<?php echo e($search); ?>"
                placeholder="Search title, slug, description, outcome..."
            >
        </div>

        <div class="form-group">
            <label>Filter by Parent Focus Area</label>
            <select name="parent_filter">
                <option value="">-- All --</option>
                <option value="top" <?php echo ($filter_parent_id === 'top' ? 'selected' : ''); ?>>Top Level Only</option>
                <?php foreach ($parents as $parent): ?>
                    <option value="<?php echo (int)$parent['id']; ?>" <?php echo ((string)$filter_parent_id === (string)$parent['id'] ? 'selected' : ''); ?>>
                        <?php echo e($parent['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="focus-areas.php" class="btn">
                <i class="fas fa-times"></i> Reset
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="50">Order</th>
                    <th>Focus Area</th>
                    <th width="160">Parent</th>
                    <th width="90">Children</th>
                    <th width="100">Status</th>
                    <th width="80">Menu</th>
                    <th width="230">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($areas)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
                        <strong>No focus areas found</strong><br>
                        <small>Try adjusting your search or filter.</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($areas as $area): ?>
                    <tr>
                        <td style="text-align:center;font-weight:600;color:#6b7280;">
                            <?php echo (int)$area['display_order']; ?>
                        </td>

                        <td>
                            <div class="focus-area-preview">
                                <?php if (!empty($area['featured_image'])): ?>
                                    <img
                                        src="../uploads/focus-areas/<?php echo e($area['featured_image']); ?>"
                                        alt="<?php echo e($area['title']); ?>"
                                        class="focus-area-thumb"
                                    >
                                <?php elseif (!empty($area['icon'])): ?>
                                    <div class="focus-area-icon"><i class="<?php echo e($area['icon']); ?>"></i></div>
                                <?php else: ?>
                                    <div class="focus-area-icon"><i class="fas fa-bullseye"></i></div>
                                <?php endif; ?>

                                <div>
                                    <?php if (!empty($area['parent_id'])): ?>
                                        <span class="hierarchy-indicator" title="Child area">+-</span>
                                    <?php endif; ?>

                                    <strong style="display:inline-block;margin-bottom:4px;"><?php echo e($area['title']); ?></strong>

                                    <?php if (empty($area['parent_id']) && (int)$area['children_count'] > 0): ?>
                                        <span class="badge-parent">PARENT</span>
                                    <?php elseif (!empty($area['parent_id'])): ?>
                                        <span class="badge-child">CHILD</span>
                                    <?php endif; ?>

                                    <div class="table-meta">
                                        <i class="fas fa-link" style="font-size:10px;"></i>
                                        <?php echo e($area['slug']); ?>
                                    </div>

                                    <?php if (!empty($area['description'])): ?>
                                        <div class="table-meta" style="margin-top:4px;">
                                            <?php echo e(mb_strimwidth(strip_tags($area['description']), 0, 120, '...')); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($area['outcome'])): ?>
                                        <div class="outcome-box">
                                            <strong>Outcome:</strong>
                                            <?php echo e(mb_strimwidth(strip_tags($area['outcome']), 0, 140, '...')); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($area['link'])): ?>
                                        <div class="table-meta" style="margin-top:4px;">
                                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                            <?php echo e($area['link']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($area['parent_title'])): ?>
                                <small style="color:#6b7280;">
                                    <i class="fas fa-level-up-alt" style="margin-right:4px;"></i>
                                    <?php echo e($area['parent_title']); ?>
                                </small>
                            <?php else: ?>
                                <span style="color:#d1d5db;">-</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:center;">
                            <?php if ((int)$area['children_count'] > 0): ?>
                                <span style="background:#e0e7ff;color:#4338ca;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                                    <?php echo (int)$area['children_count']; ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#d1d5db;">0</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge badge-<?php echo $area['status'] === 'published' ? 'success' : ($area['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                <?php echo e(ucfirst($area['status'])); ?>
                            </span>
                        </td>

                        <td style="text-align:center;">
                            <?php if (!empty($area['show_in_menu'])): ?>
                                <span style="color:#10b981;" title="Shown in menu"><i class="fas fa-check-circle"></i></span>
                            <?php else: ?>
                                <span style="color:#ef4444;" title="Hidden from menu"><i class="fas fa-times-circle"></i></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if (!empty($area['link'])): ?>
                                    <a href="<?php echo e($area['link']); ?>" class="btn btn-sm" target="_blank" title="Open Link">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="../focus-area.php?slug=<?php echo urlencode($area['slug']); ?>" class="btn btn-sm" target="_blank" title="View Page">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    onclick='editFocusArea(<?php echo json_encode($area, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>)'
                                    title="Edit Focus Area"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger"
                                    onclick="deleteFocusArea(<?php echo (int)$area['id']; ?>, <?php echo (int)$area['children_count']; ?>, <?php echo json_encode($area['title']); ?>)"
                                    title="Delete Focus Area"
                                >
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        <div class="table-meta">
            Showing <?php echo $total_rows > 0 ? ($offset + 1) : 0; ?> -
            <?php echo min($offset + $per_page, $total_rows); ?>
            of <?php echo $total_rows; ?> result(s)
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $queryBase = [
                    'search' => $search,
                    'parent_filter' => $filter_parent_id
                ];
                ?>

                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $page - 1])); ?>">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="active"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $p])); ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($queryBase, ['page' => $page + 1])); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="areaModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-bullseye"></i> <span id="modalTitleText">Create Focus Area</span></h3>
            <button class="modal-close" type="button" onclick="closeAreaModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="areaForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="areaId" value="0">

                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Focus Area Title *</label>
                        <input type="text" name="title" id="areaTitle" required placeholder="e.g., Digital Innovation" onkeyup="generateSlugFromTitle()">
                    </div>
                    <div class="form-group col-4">
                        <label>Display Order</label>
                        <input type="number" name="display_order" id="areaOrder" value="0" min="0">
                        <small>Lower = higher priority</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" name="slug" id="areaSlug" placeholder="auto-generated-from-title">
                    <small>Leave empty to auto-generate. Used in URL: /focus-area.php?slug=<strong>your-slug</strong></small>
                </div>

                <div class="form-group">
                    <label>Link / URL (optional)</label>
                    <input type="text" name="link" id="areaLink" placeholder="e.g. programs.php or https://example.org">
                    <small>If provided, the homepage "Learn More" button can use this link.</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Parent Focus Area</label>
                        <select name="parent_id" id="areaParent">
                            <option value="">-- None (Top Level) --</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo (int)$parent['id']; ?>"><?php echo e($parent['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Make this a sub-area of another focus area</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Icon Class (Font Awesome)</label>
                        <input type="text" name="icon" id="areaIcon" placeholder="fas fa-laptop">
                        <small>Example: fas fa-laptop, fas fa-globe</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="areaDescription" rows="2" placeholder="Brief description"></textarea>
                </div>

                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="areaContent" rows="6" placeholder="Full page content. HTML allowed."></textarea>
                </div>

                <div class="form-group">
                    <label>Outcome</label>
                    <textarea name="outcome" id="areaOutcome" rows="3" placeholder="Expected outcome for this focus area"></textarea>
                    <small>This is useful for parent or child focus areas.</small>
                </div>

                <div class="form-group">
                    <label>Featured Image</label>
                    <input type="file" name="featured_image" id="areaImage" accept="image/*">
                    <small>Recommended size: 1200x800px. Formats: JPG, PNG, WebP</small>
                </div>

                <div id="currentImagePreview" style="display:none;margin-bottom:15px;">
                    <label>Current Image:</label>
                    <div style="margin-top:8px;">
                        <img id="currentImage" src="" style="max-width:300px;border-radius:8px;border:2px solid #e5e7eb;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Status</label>
                        <select name="status" id="areaStatus">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="show_in_menu" id="areaMenu" value="1" checked>
                            <span>Show in Navigation Menu</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>SEO Meta Title</label>
                    <input type="text" name="meta_title" id="areaMetaTitle" placeholder="Leave empty to use focus area title">
                </div>

                <div class="form-group">
                    <label>SEO Meta Description</label>
                    <textarea name="meta_description" id="areaMetaDesc" rows="2" placeholder="Brief description for search engines"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeAreaModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Focus Area
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const parentsData = <?php echo json_encode($parents, JSON_UNESCAPED_UNICODE); ?>;

function openCreateModal() {
    document.getElementById('modalTitleText').textContent = 'Create Focus Area';
    document.getElementById('areaForm').reset();
    document.getElementById('areaId').value = '0';
    document.getElementById('areaOrder').value = '0';
    document.getElementById('currentImagePreview').style.display = 'none';
    resetParentDropdown();
    document.getElementById('areaModal').style.display = 'flex';
}

function editFocusArea(area) {
    document.getElementById('modalTitleText').textContent = 'Edit Focus Area';
    document.getElementById('areaId').value = area.id || 0;
    document.getElementById('areaTitle').value = area.title || '';
    document.getElementById('areaSlug').value = area.slug || '';
    document.getElementById('areaLink').value = area.link || '';
    document.getElementById('areaDescription').value = area.description || '';
    document.getElementById('areaContent').value = area.content || '';
    document.getElementById('areaOutcome').value = area.outcome || '';
    document.getElementById('areaIcon').value = area.icon || '';
    document.getElementById('areaOrder').value = area.display_order || 0;
    document.getElementById('areaStatus').value = area.status || 'published';
    document.getElementById('areaMenu').checked = parseInt(area.show_in_menu || 0, 10) === 1;
    document.getElementById('areaMetaTitle').value = area.meta_title || '';
    document.getElementById('areaMetaDesc').value = area.meta_description || '';

    resetParentDropdown(area.id);
    document.getElementById('areaParent').value = area.parent_id || '';

    if (area.featured_image) {
        document.getElementById('currentImage').src = '../uploads/focus-areas/' + area.featured_image;
        document.getElementById('currentImagePreview').style.display = 'block';
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }

    document.getElementById('areaModal').style.display = 'flex';
}

function resetParentDropdown(excludeId = null) {
    const select = document.getElementById('areaParent');
    select.innerHTML = '<option value="">-- None (Top Level) --</option>';

    parentsData.forEach(parent => {
        if (!excludeId || parseInt(parent.id, 10) !== parseInt(excludeId, 10)) {
            const opt = document.createElement('option');
            opt.value = parent.id;
            opt.textContent = parent.title;
            select.appendChild(opt);
        }
    });
}

function closeAreaModal() {
    document.getElementById('areaModal').style.display = 'none';
    document.getElementById('areaForm').reset();
}

function deleteFocusArea(id, childrenCount, title) {
    if (childrenCount > 0) {
        alert('Cannot delete "' + title + '" because it has ' + childrenCount + ' child area(s).\n\nPlease delete or reassign the child areas first.');
        return;
    }

    if (confirm('Are you sure you want to delete "' + title + '"?\n\nThis will also delete the featured image and cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML =
            '<input type="hidden" name="action" value="delete">' +
            '<input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function generateSlugFromTitle() {
    const title = document.getElementById('areaTitle').value || '';
    const slug = title.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/--+/g, '-')
        .replace(/^-+|-+$/g, '');

    document.getElementById('areaSlug').placeholder = slug || 'auto-generated-from-title';
}

window.onclick = function (e) {
    if (e.target && e.target.id === 'areaModal') {
        closeAreaModal();
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeAreaModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>