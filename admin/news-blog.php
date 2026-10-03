<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);


$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_news_blog')) {
        $has_permission = true;
    } elseif (hasPermission($conn, 'manage_content')) {
        $has_permission = true;
    }
} else {
    $has_permission = true;
}

if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage blog posts. Please contact your administrator to assign the "manage_blog" or "manage_content" permission.</p>');
}

$page_title = 'Manage News & Blog';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? 0;
        $title = clean($conn, $_POST['title']);
        $slug = clean($conn, $_POST['slug']);
        $excerpt = clean($conn, $_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? ''; // Keep HTML formatting
        $category = clean($conn, $_POST['category'] ?? '');
        $tags = clean($conn, $_POST['tags'] ?? '');
        $status = clean($conn, $_POST['status'] ?? 'published');
        $published_at = clean($conn, $_POST['published_at'] ?? date('Y-m-d H:i:s'));
        
        // Auto-generate slug if empty
        if (empty($slug)) {
            $slug = generateSlug($title);
        } else {
            $slug = generateSlug($slug);
        }
        
        $featured_image = '';
        
        // Handle featured image upload
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['featured_image'], 'blog');
            if ($upload_result['success']) {
                $featured_image = $upload_result['filename'];
            }
        }
        
        if ($id > 0) {
            // Update existing post
            $sql = "UPDATE news_blog SET title = ?, slug = ?, excerpt = ?, content = ?, category = ?, tags = ?, status = ?, published_at = ?";
            $params = [$title, $slug, $excerpt, $content, $category, $tags, $status, $published_at];
            $types = "ssssssss";
            
            if ($featured_image) {
                // Delete old image
                $stmt = $conn->prepare("SELECT featured_image FROM news_blog WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                if ($old && $old['featured_image']) {
                    deleteFile($old['featured_image'], 'blog');
                }
                
                $sql .= ", featured_image = ?";
                $params[] = $featured_image;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            setFlashMessage('success', 'Post updated successfully');
        } else {
            // Insert new post
            $stmt = $conn->prepare("INSERT INTO news_blog (title, slug, excerpt, content, featured_image, author_id, category, tags, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssissss", $title, $slug, $excerpt, $content, $featured_image, $_SESSION['user_id'], $category, $tags, $status, $published_at);
            $stmt->execute();
            setFlashMessage('success', 'Post created successfully');
        }
        
        header("Location: news-blog.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get image before deleting
        $stmt = $conn->prepare("SELECT featured_image FROM news_blog WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        
        if ($post && $post['featured_image']) {
            deleteFile($post['featured_image'], 'blog');
        }
        
        $stmt = $conn->prepare("DELETE FROM news_blog WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        setFlashMessage('success', 'Post deleted successfully');
        header("Location: news-blog.php");
        exit();
    }
}

// Get all posts
$posts = [];
$result = $conn->query("SELECT n.*, u.name as author_name FROM news_blog n LEFT JOIN users u ON n.author_id = u.id ORDER BY n.published_at DESC, n.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}

// Get categories for filter
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM news_blog WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}

include 'layout/header.php';
include 'layout/sidebar.php';
?>

<style>
.post-preview {
    display: flex;
    align-items: center;
    gap: 12px;
}

.post-thumb {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid #e5e7eb;
}

.post-placeholder {
    width: 80px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    border-radius: 6px;
    color: #9ca3af;
    font-size: 24px;
}

.category-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    background: #e0e7ff;
    color: #3730a3;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-newspaper" style="color: #3b82f6;"></i> Manage News & Blog</h2>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Post
    </button>
</div>

<?php 
$flash = getFlashMessage();
if ($flash): 
?>
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
                    <th>Post</th>
                    <th width="120">Category</th>
                    <th width="120">Author</th>
                    <th width="100">Status</th>
                    <th width="80">Views</th>
                    <th width="120">Published</th>
                    <th width="200">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 15px; display: block;"></i>
                            <strong>No posts found</strong><br>
                            <small>Create your first blog post to get started!</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <div class="post-preview">
                                <?php if ($post['featured_image']): ?>
                                    <img src="../uploads/blog/<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>"
                                         class="post-thumb">
                                <?php else: ?>
                                    <div class="post-placeholder">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong style="display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($post['title']); ?></strong>
                                    <small style="color: #6b7280;">
                                        <i class="fas fa-link" style="font-size: 10px;"></i>
                                        <?php echo htmlspecialchars($post['slug']); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($post['category']): ?>
                                <span class="category-badge"><?php echo htmlspecialchars($post['category']); ?></span>
                            <?php else: ?>
                                <span style="color: #d1d5db;"></span>
                            <?php endif; ?>
                        </td>
                        <td style="color: #6b7280;">
                            <i class="fas fa-user" style="font-size: 12px; margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($post['author_name']); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst($post['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center; color: #6b7280;">
                            <i class="fas fa-eye" style="font-size: 12px; margin-right: 4px;"></i>
                            <?php echo number_format($post['view_count']); ?>
                        </td>
                        <td style="color: #6b7280;">
                            <i class="fas fa-calendar" style="font-size: 12px; margin-right: 4px;"></i>
                            <?php echo date('M j, Y', strtotime($post['published_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="../blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" 
                                   class="btn btn-sm" 
                                   target="_blank" 
                                   title="View Post">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick='editPost(<?php echo json_encode($post); ?>)' 
                                        class="btn btn-sm btn-primary" 
                                        title="Edit Post">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePost(<?php echo $post['id']; ?>, '<?php echo addslashes($post['title']); ?>')" 
                                        class="btn btn-sm btn-danger" 
                                        title="Delete Post">
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

<!-- Post Modal -->
<div id="postModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 id="modalTitle">
                <i class="fas fa-newspaper"></i> <span id="modalTitleText">Create Post</span>
            </h3>
            <button class="modal-close" onclick="closePostModal()">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="postForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="postId" value="0">
                
                <div class="form-group">
                    <label>Post Title *</label>
                    <input type="text" 
                           name="title" 
                           id="postTitle" 
                           required 
                           placeholder="e.g., Announcing Our New Initiative"
                           onkeyup="generateSlugFromTitle()">
                </div>
                
                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" 
                           name="slug" 
                           id="postSlug"
                           placeholder="auto-generated-from-title">
                    <small>Leave empty to auto-generate. Used in URL: /blog-post.php?slug=<strong>your-slug</strong></small>
                </div>
                
                <div class="form-group">
                    <label>Excerpt</label>
                    <textarea name="excerpt" 
                              id="postExcerpt"
                              rows="2" 
                              placeholder="Brief summary for preview cards (optional)"></textarea>
                    <small>Short description shown in blog listings (recommended: 120-160 characters)</small>
                </div>
                
                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" 
                              id="postContent"
                              rows="12" 
                              required
                              placeholder="Write your blog post content here..."></textarea>
                    <small>Full article content. HTML allowed for formatting.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Category</label>
                        <input type="text" 
                               name="category" 
                               id="postCategory"
                               list="categoryList" 
                               placeholder="e.g., News, Events, Impact">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small>Select or type a new category</small>
                    </div>
                    <div class="form-group col-6">
                        <label>Tags</label>
                        <input type="text" 
                               name="tags" 
                               id="postTags"
                               placeholder="tag1, tag2, tag3">
                        <small>Comma-separated keywords</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Featured Image</label>
                    <input type="file" 
                           name="featured_image" 
                           id="postImage"
                           accept="image/*">
                    <small>Recommended size: 1200x630px. Formats: JPG, PNG, WebP</small>
                </div>
                
                <div id="currentImagePreview" style="display: none; margin-bottom: 15px;">
                    <label>Current Image:</label>
                    <div style="margin-top: 8px;">
                        <img id="currentImage" src="" style="max-width: 300px; border-radius: 8px; border: 2px solid #e5e7eb;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Status</label>
                        <select name="status" id="postStatus">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label>Published Date & Time</label>
                        <input type="datetime-local" 
                               name="published_at" 
                               id="postPublishedAt"
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                
                <div id="postStats" style="display: none;">
                    <div class="form-group">
                        <small style="color: #6b7280; padding: 12px; background: #f9fafb; border-radius: 6px; display: block;">
                            <strong>Post Stats:</strong> 
                            <span id="statsContent"></span>
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closePostModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Post
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const categories = <?php echo json_encode($categories); ?>;

function openCreateModal() {
    document.getElementById('modalTitleText').textContent = 'Create Post';
    document.getElementById('postForm').reset();
    document.getElementById('postId').value = '0';
    document.getElementById('postPublishedAt').value = '<?php echo date('Y-m-d\TH:i'); ?>';
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('postStats').style.display = 'none';
    document.getElementById('postModal').style.display = 'flex';
}

function editPost(post) {
    document.getElementById('modalTitleText').textContent = 'Edit Post';
    document.getElementById('postId').value = post.id;
    document.getElementById('postTitle').value = post.title;
    document.getElementById('postSlug').value = post.slug;
    document.getElementById('postExcerpt').value = post.excerpt || '';
    document.getElementById('postContent').value = post.content || '';
    document.getElementById('postCategory').value = post.category || '';
    document.getElementById('postTags').value = post.tags || '';
    document.getElementById('postStatus').value = post.status;
    
    // Format published date for datetime-local input
    if (post.published_at) {
        const date = new Date(post.published_at);
        const formatted = date.getFullYear() + '-' + 
            String(date.getMonth() + 1).padStart(2, '0') + '-' + 
            String(date.getDate()).padStart(2, '0') + 'T' + 
            String(date.getHours()).padStart(2, '0') + ':' + 
            String(date.getMinutes()).padStart(2, '0');
        document.getElementById('postPublishedAt').value = formatted;
    }
    
    // Handle featured image
    if (post.featured_image) {
        document.getElementById('currentImage').src = '../uploads/blog/' + post.featured_image;
        document.getElementById('currentImagePreview').style.display = 'block';
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }
    
    // Show post stats
    const statsContent = `
        ID: ${post.id} | 
        Views: ${parseInt(post.view_count).toLocaleString()} | 
        Created: ${new Date(post.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
    `;
    document.getElementById('statsContent').textContent = statsContent;
    document.getElementById('postStats').style.display = 'block';
    
    document.getElementById('postModal').style.display = 'flex';
}

function closePostModal() {
    document.getElementById('postModal').style.display = 'none';
    document.getElementById('postForm').reset();
}

function deletePost(id, title) {
    if (confirm(`Are you sure you want to delete "${title}"?\n\nThis will also delete the featured image and cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function generateSlugFromTitle() {
    const title = document.getElementById('postTitle').value;
    const slug = title
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/--+/g, '-')
        .trim();
    document.getElementById('postSlug').placeholder = slug || 'auto-generated-from-title';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'postModal') {
        closePostModal();
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePostModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>