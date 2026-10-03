<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_learning'))  $has_permission = true;
    elseif (hasPermission($conn, 'manage_content')) $has_permission = true;
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage learning courses. Please contact your administrator to assign the "manage_learning" or "manage_content" permission.</p>');
}

$page_title = 'Manage Learning';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* -- Save (create or update) ---------------------------- */
    if ($action === 'save') {
        $id               = (int)($_POST['id']             ?? 0);
        $title            = clean($conn, $_POST['title']            ?? '');
        $slug             = clean($conn, $_POST['slug']             ?? '');
        $description      = clean($conn, $_POST['description']      ?? '');
        $content          = $_POST['content'] ?? '';
        $icon             = clean($conn, $_POST['icon']             ?? '');
        $duration         = clean($conn, $_POST['duration']         ?? '');
        $level            = clean($conn, $_POST['level']            ?? 'all');
        $category         = clean($conn, $_POST['category']         ?? '');
        $instructor       = clean($conn, $_POST['instructor']       ?? '');
        $price            = floatval($_POST['price']                ?? 0);
        $apply_link       = clean($conn, $_POST['apply_link']       ?? '');
        $display_order    = (int)($_POST['display_order']           ?? 0);
        $status           = clean($conn, $_POST['status']           ?? 'published');
        $show_in_menu     = isset($_POST['show_in_menu']) ? 1 : 0;
        $meta_title       = clean($conn, $_POST['meta_title']       ?? '');
        $meta_description = clean($conn, $_POST['meta_description'] ?? '');

        $slug = empty($slug) ? generateSlug($title) : generateSlug($slug);

        /* Featured image */
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $up = uploadFile($_FILES['featured_image'], 'learning');
            if ($up['success']) $featured_image = $up['filename'];
        }

        /* Hero image */
        $hero_image = '';
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === 0) {
            $up = uploadFile($_FILES['hero_image'], 'learning');
            if ($up['success']) $hero_image = $up['filename'];
        }

        if ($id > 0) {
            /* Fetch old images for cleanup */
            $st = $conn->prepare("SELECT featured_image, hero_image FROM learning WHERE id = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $old = $st->get_result()->fetch_assoc();
            $st->close();

            if ($featured_image && $old && $old['featured_image']) deleteFile($old['featured_image'], 'learning');
            if ($hero_image     && $old && $old['hero_image'])     deleteFile($old['hero_image'],     'learning');

            $sql    = "UPDATE learning SET title=?, slug=?, description=?, content=?, icon=?, duration=?, level=?, category=?, instructor=?, price=?, apply_link=?, display_order=?, status=?, show_in_menu=?, meta_title=?, meta_description=?";
            $types  = "sssssssssdsissss";
            $params = [$title, $slug, $description, $content, $icon, $duration, $level, $category, $instructor, $price, $apply_link, $display_order, $status, $show_in_menu, $meta_title, $meta_description];

            if ($featured_image) { $sql .= ", featured_image=?"; $types .= "s"; $params[] = $featured_image; }
            if ($hero_image)     { $sql .= ", hero_image=?";     $types .= "s"; $params[] = $hero_image; }

            $sql .= " WHERE id=?";
            $types  .= "i";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Course updated successfully');
        } else {
            $stmt = $conn->prepare("INSERT INTO learning (title, slug, description, content, featured_image, hero_image, icon, duration, level, category, instructor, price, apply_link, display_order, status, show_in_menu, meta_title, meta_description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssssssdsissssi",
                $title, $slug, $description, $content,
                $featured_image, $hero_image,
                $icon, $duration, $level, $category, $instructor,
                $price, $apply_link, $display_order, $status, $show_in_menu,
                $meta_title, $meta_description,
                $_SESSION['user_id']
            );
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Course created successfully');
        }

        header("Location: learning.php");
        exit();
    }

  
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $st = $conn->prepare("SELECT featured_image, hero_image FROM learning WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $course = $st->get_result()->fetch_assoc();
        $st->close();

        if ($course) {
            if ($course['featured_image']) deleteFile($course['featured_image'], 'learning');
            if ($course['hero_image'])     deleteFile($course['hero_image'],     'learning');
        }

        $st = $conn->prepare("DELETE FROM learning WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $st->close();

        setFlashMessage('success', 'Course deleted successfully');
        header("Location: learning.php");
        exit();
    }
}


$courses = [];
$result  = $conn->query("SELECT l.*, u.name AS author_name FROM learning l LEFT JOIN users u ON l.created_by = u.id ORDER BY l.display_order, l.created_at");
if ($result) while ($row = $result->fetch_assoc()) $courses[] = $row;

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.course-preview { display: flex; align-items: center; gap: 12px; }

.course-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #f86505 0%, #f86505a8 100%);
    border-radius: 8px; color: #fff; font-size: 18px;
}
.course-thumb {
    width: 60px; height: 40px; flex-shrink: 0;
    object-fit: cover; border-radius: 6px; border: 2px solid #e5e7eb;
}
.level-badge {
    padding: 4px 10px; border-radius: 12px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.level-beginner     { background: #d1fae5; color: #065f46; }
.level-intermediate { background: #fef3c7; color: #92400e; }
.level-advanced     { background: #fee2e2; color: #991b1b; }
.level-all          { background: #e0e7ff; color: #3730a3; }
.price-tag          { font-weight: 700; color: #10b981; font-size: 14px; }

.hero-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; padding: 2px 7px;
    background: #ede9fe; color: #6d28d9; border-radius: 999px;
    margin-left: 6px; vertical-align: middle;
}

/* image upload rows inside modal */
.img-upload-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
    margin-bottom: 4px;
}
.img-upload-item { display: flex; flex-direction: column; gap: 6px; }
.img-upload-item > label {
    font-weight: 700; font-size: 13px; color: #374151;
    display: flex; align-items: center; gap: 6px; margin: 0;
}
.img-preview-wrap { margin-top: 8px; display: none; }
.img-preview-wrap img {
    width: 100%; height: 90px; object-fit: cover;
    border-radius: 8px; border: 2px solid #e5e7eb; display: block;
}
.img-preview-wrap small { color: #9ca3af; font-size: 11px; margin-top: 4px; display: block; }
@media (max-width: 580px) { .img-upload-row { grid-template-columns: 1fr; } }
</style>

<div class="content-header">
    <h2><i class="fas fa-graduation-cap" style="color:#f86505;"></i> Manage Learning Courses</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Course
    </button>
</div>

<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="50">Order</th>
                    <th>Course</th>
                    <th width="100">Level</th>
                    <th width="120">Duration</th>
                    <th width="100">Price</th>
                    <th width="100">Status</th>
                    <th width="200">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($courses)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:48px;color:#d1d5db;margin-bottom:15px;display:block;"></i>
                        <strong>No courses found</strong><br>
                        <small>Create your first learning course to get started!</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td style="text-align:center;font-weight:600;color:#6b7280;">
                        <?php echo (int)$course['display_order']; ?>
                    </td>
                    <td>
                        <div class="course-preview">
                            <?php if (!empty($course['featured_image'])): ?>
                                <img src="../uploads/learning/<?php echo htmlspecialchars($course['featured_image']); ?>"
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     class="course-thumb">
                            <?php elseif (!empty($course['icon'])): ?>
                                <div class="course-icon">
                                    <i class="<?php echo htmlspecialchars($course['icon']); ?>"></i>
                                </div>
                            <?php else: ?>
                                <div class="course-icon"><i class="fas fa-graduation-cap"></i></div>
                            <?php endif; ?>
                            <div>
                                <strong style="display:block;margin-bottom:3px;">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                    <?php if (!empty($course['hero_image'])): ?>
                                        <span class="hero-badge" title="Has hero image">
                                            <i class="fas fa-panorama" style="font-size:9px;"></i> Hero
                                        </span>
                                    <?php endif; ?>
                                </strong>
                                <small style="color:#6b7280;display:block;margin-bottom:3px;">
                                    <i class="fas fa-link" style="font-size:10px;"></i>
                                    <?php echo htmlspecialchars($course['slug']); ?>
                                </small>
                                <?php if (!empty($course['category'])): ?>
                                    <small style="color:#f86505;">
                                        <i class="fas fa-tag" style="font-size:10px;"></i>
                                        <?php echo htmlspecialchars($course['category']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="level-badge level-<?php echo htmlspecialchars($course['level']); ?>">
                            <?php echo ucfirst($course['level']); ?>
                        </span>
                    </td>
                    <td style="color:#6b7280;">
                        <?php if (!empty($course['duration'])): ?>
                            <i class="fas fa-clock" style="margin-right:4px;"></i>
                            <?php echo htmlspecialchars($course['duration']); ?>
                        <?php else: ?>
                            <span style="color:#d1d5db;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($course['price'] > 0): ?>
                            <span class="price-tag">UGX <?php echo number_format($course['price'], 0); ?></span>
                        <?php else: ?>
                            <span style="color:#10b981;font-weight:600;">FREE</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $course['status'] === 'published' ? 'success' : ($course['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($course['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="../learning-detail.php?slug=<?php echo urlencode($course['slug']); ?>"
                               class="btn btn-sm" target="_blank" title="View Course">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick='editCourse(<?php echo json_encode($course); ?>)'
                                    class="btn btn-sm btn-primary" title="Edit Course">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteCourse(<?php echo (int)$course['id']; ?>, '<?php echo addslashes(htmlspecialchars($course['title'])); ?>')"
                                    class="btn btn-sm btn-danger" title="Delete Course">
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
</div>

<!-- -- MODAL ------------------------------------------------ -->
<div id="courseModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:900px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>
                <i class="fas fa-graduation-cap"></i>
                <span id="modalTitleText">Create Course</span>
            </h3>
            <button class="modal-close" onclick="closeCourseModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="courseForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="courseId" value="0">

                <!-- Title + Order -->
                <div class="form-row">
                    <div class="form-group col-8">
                        <label>Course Title *</label>
                        <input type="text" name="title" id="courseTitle" required
                               placeholder="e.g., Web Development Bootcamp"
                               onkeyup="generateSlugFromTitle()">
                    </div>
                    <div class="form-group col-4">
                        <label>Display Order</label>
                        <input type="number" name="display_order" id="courseOrder" value="0" min="0">
                        <small>Lower = higher priority</small>
                    </div>
                </div>

                <!-- Slug -->
                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" name="slug" id="courseSlug" placeholder="auto-generated-from-title">
                    <small>Leave empty to auto-generate. Used in URL: /learning-detail.php?slug=<strong>your-slug</strong></small>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="courseDescription" rows="2" required
                              placeholder="Brief course description (shown in cards)"></textarea>
                    <small>Concise overview for course cards</small>
                </div>

                <!-- Content -->
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="courseContent" rows="8"
                              placeholder="Full course content. HTML allowed."></textarea>
                    <small>Detailed course content, curriculum, what students will learn, etc.</small>
                </div>

                <!-- Category + Instructor -->
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Category</label>
                        <input type="text" name="category" id="courseCategory" placeholder="e.g., Web Development">
                        <small>Course category or subject area</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Instructor</label>
                        <input type="text" name="instructor" id="courseInstructor" placeholder="e.g., John Doe">
                        <small>Instructor or trainer name</small>
                    </div>
                </div>

                <!-- Duration + Level + Price -->
                <div class="form-row">
                    <div class="form-group col-4">
                        <label>Duration</label>
                        <input type="text" name="duration" id="courseDuration" placeholder="e.g., 8 weeks">
                        <small>Course length</small>
                    </div>
                    <div class="form-group col-4">
                        <label>Level</label>
                        <select name="level" id="courseLevel">
                            <option value="all">All Levels</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Price (UGX)</label>
                        <input type="number" name="price" id="coursePrice"
                               value="0" min="0" step="0.01" placeholder="0.00">
                        <small>0 for free courses</small>
                    </div>
                </div>

                <!-- Apply Link -->
                <div class="form-group">
                    <label>Apply / Enroll Link *</label>
                    <input type="url" name="apply_link" id="courseApplyLink" required
                           placeholder="https://forms.gle/example or https://apply.example.com">
                    <small>URL where students can apply or enroll</small>
                </div>

                <!-- Icon -->
                <div class="form-group">
                    <label>Icon Class (Font Awesome)</label>
                    <input type="text" name="icon" id="courseIcon" placeholder="fas fa-graduation-cap">
                    <small>Used as fallback when no featured image. Example: fas fa-code, fas fa-chart-line</small>
                </div>

                <!-- -- IMAGES -------------------------------------- -->
                <div class="form-group">
                    <label style="font-weight:700;color:#374151;margin-bottom:10px;display:block;">Images</label>
                    <div class="img-upload-row">

                        <!-- Featured Image -->
                        <div class="img-upload-item">
                            <label>
                                <i class="fas fa-image" style="color:#f86505;"></i>
                                Featured Image
                            </label>
                            <input type="file" name="featured_image" id="courseImage"
                                   accept="image/*"
                                   onchange="previewImg(this,'featuredPreviewImg','featuredPreviewWrap')">
                            <small style="color:#6b7280;">Card thumbnail - 1200x800 px recommended</small>
                            <div class="img-preview-wrap" id="featuredPreviewWrap">
                                <img id="featuredPreviewImg" src="" alt="Featured preview">
                                <small>Uploading replaces the current image.</small>
                            </div>
                        </div>

                        <!-- Hero Image -->
                        <div class="img-upload-item">
                            <label>
                                <i class="fas fa-panorama" style="color:#6d28d9;"></i>
                                Hero Image
                            </label>
                            <input type="file" name="hero_image" id="courseHeroImage"
                                   accept="image/*"
                                   onchange="previewImg(this,'heroPreviewImg','heroPreviewWrap')">
                            <small style="color:#6b7280;">Full-width detail page banner - 1600x600 px recommended</small>
                            <div class="img-preview-wrap" id="heroPreviewWrap">
                                <img id="heroPreviewImg" src="" alt="Hero preview">
                                <small>Uploading replaces the current image.</small>
                            </div>
                        </div>

                    </div>
                </div>
              

                <!-- Status + Menu -->
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Status</label>
                        <select name="status" id="courseStatus">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:24px;">
                            <input type="checkbox" name="show_in_menu" id="courseMenu" value="1" checked>
                            <span>Show in Learning Menu</span>
                        </label>
                    </div>
                </div>

                <!-- SEO -->
                <div class="form-group">
                    <label>SEO Meta Title</label>
                    <input type="text" name="meta_title" id="courseMetaTitle"
                           placeholder="Leave empty to use course title">
                    <small>Custom title for search engines (max 60 chars)</small>
                </div>
                <div class="form-group">
                    <label>SEO Meta Description</label>
                    <textarea name="meta_description" id="courseMetaDesc" rows="2"
                              placeholder="Brief description for search engines"></textarea>
                    <small>Summary for search results (max 160 chars)</small>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeCourseModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitleText').textContent = 'Create Course';
    document.getElementById('courseForm').reset();
    document.getElementById('courseId').value = '0';
    hidePreview('featuredPreviewWrap');
    hidePreview('heroPreviewWrap');
    document.getElementById('courseModal').style.display = 'flex';
}

function editCourse(course) {
    document.getElementById('modalTitleText').textContent = 'Edit Course';

    document.getElementById('courseId').value          = course.id;
    document.getElementById('courseTitle').value       = course.title        || '';
    document.getElementById('courseSlug').value        = course.slug         || '';
    document.getElementById('courseDescription').value = course.description  || '';
    document.getElementById('courseContent').value     = course.content      || '';
    document.getElementById('courseCategory').value    = course.category     || '';
    document.getElementById('courseInstructor').value  = course.instructor   || '';
    document.getElementById('courseDuration').value    = course.duration     || '';
    document.getElementById('courseLevel').value       = course.level        || 'all';
    document.getElementById('coursePrice').value       = course.price        || 0;
    document.getElementById('courseApplyLink').value   = course.apply_link   || '';
    document.getElementById('courseIcon').value        = course.icon         || '';
    document.getElementById('courseOrder').value       = course.display_order|| 0;
    document.getElementById('courseStatus').value      = course.status       || 'published';
    document.getElementById('courseMenu').checked      = course.show_in_menu == 1;
    document.getElementById('courseMetaTitle').value   = course.meta_title   || '';
    document.getElementById('courseMetaDesc').value    = course.meta_description || '';

    // Featured image preview
    if (course.featured_image) {
        showPreview('featuredPreviewImg', '../uploads/learning/' + course.featured_image, 'featuredPreviewWrap');
    } else {
        hidePreview('featuredPreviewWrap');
    }

    // Hero image preview
    if (course.hero_image) {
        showPreview('heroPreviewImg', '../uploads/learning/' + course.hero_image, 'heroPreviewWrap');
    } else {
        hidePreview('heroPreviewWrap');
    }

    document.getElementById('courseModal').style.display = 'flex';
}

function closeCourseModal() {
    document.getElementById('courseModal').style.display = 'none';
    document.getElementById('courseForm').reset();
    hidePreview('featuredPreviewWrap');
    hidePreview('heroPreviewWrap');
}

function deleteCourse(id, title) {
    if (confirm('Are you sure you want to delete "' + title + '"?\n\nThis will also delete all uploaded images and cannot be undone.')) {
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
    const title = document.getElementById('courseTitle').value;
    const slug  = title.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/--+/g, '-')
        .trim();
    document.getElementById('courseSlug').placeholder = slug || 'auto-generated-from-title';
}

function previewImg(input, imgId, wrapId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            showPreview(imgId, e.target.result, wrapId);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function showPreview(imgId, src, wrapId) {
    document.getElementById(imgId).src = src;
    document.getElementById(wrapId).style.display = 'block';
}

function hidePreview(wrapId) {
    document.getElementById(wrapId).style.display = 'none';
}

// Close on outside click
window.onclick = function(e) {
    if (e.target.id === 'courseModal') closeCourseModal();
};

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCourseModal();
});
</script>

<?php include 'layout/footer.php'; ?>