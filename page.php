<?php

$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// Get page by slug
$stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$page = $result->fetch_assoc();

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit();
}

$author_name = 'Admin';
if ($page['created_by']) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $page['created_by']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($author = $result->fetch_assoc()) {
        $author_name = $author['name'];
    }
}

$related_pages = [];
$stmt = $conn->prepare("SELECT * FROM pages WHERE id != ? AND status = 'published' ORDER BY RAND() LIMIT 3");
$stmt->bind_param("i", $page['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $related_pages[] = $row;
}


$page_title = $page['title'];
$meta_description = $page['meta_description'] ?: substr(strip_tags($page['content']), 0, 160);


$additional_head = '
<!-- Page-specific Open Graph -->
<meta property="og:type" content="article">
<meta property="article:author" content="' . htmlspecialchars($author_name) . '">
<meta property="article:published_time" content="' . $page['created_at'] . '">
<meta property="article:modified_time" content="' . $page['updated_at'] . '">';

if ($page['featured_image']) {
    $additional_head .= '
<meta property="og:image" content="' . SITE_URL . '/uploads/pages/' . $page['featured_image'] . '">'; 
}


$body_class = 'single-page page-' . $page['slug'];

include 'header.php';
?>

<!-- Breadcrumbs -->
<!--<nav class="breadcrumbs" style="background: #f9fafb; padding: 15px 0; border-bottom: 1px solid #e5e7eb;">
    <div class="container">
        <a href="index.php" style="color: #6b7280; text-decoration: none;">
            <i class="fas fa-home"></i> Home
        </a>
        <span style="color: #9ca3af; margin: 0 10px;">/</span>
        <span style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($page['title']); ?></span>
    </div>
</nav> -->

<!-- Page Header -->
<div class="page-header" style="background: linear-gradient(135deg, #f86505 0%, #f86505a8 100%); color: white; padding: 60px 0;">
    <div class="container">
        <h1 style="font-size: 42px; margin-bottom: 15px;"><?php echo htmlspecialchars($page['title']); ?></h1>
        
        <!-- Page Meta -->
       <!-- <div style="display: flex; gap: 25px; font-size: 14px; opacity: 0.95; flex-wrap: wrap; align-items: center;">
            <span>
                <i class="far fa-calendar"></i> 
                <?php echo formatDate($page['created_at']); ?>
            </span>
            <span>
                <i class="far fa-user"></i> 
                By <?php echo htmlspecialchars($author_name); ?>
            </span>
            <?php if ($page['updated_at'] != $page['created_at']): ?>
            <span>
                <i class="fas fa-sync-alt"></i> 
                Updated <?php echo formatDate($page['updated_at']); ?>
            </span>
            <?php endif; ?>
        </div> -->
    </div>
</div>

<!-- Page Content -->
<div class="page-content" style="padding: 60px 0;">
    <div class="container">
        <div style="max-width: 900px; margin: 0 auto;">
            
            <!-- Featured Image -->
            <?php if ($page['featured_image']): ?>
                <img src="uploads/pages/<?php echo $page['featured_image']; ?>" 
                     alt="<?php echo htmlspecialchars($page['title']); ?>" 
                     style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 10px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <?php endif; ?>
            
            <!-- Social Share & Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="margin: 0; font-size: 14px; color: #6b7280; font-weight: 500;">Share this page</h3>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <!-- Social Share Buttons -->
                    <button onclick="shareOnSocial('facebook')" class="btn btn-sm" style="background: #1877f2; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;" title="Share on Facebook">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </button>
                    <button onclick="shareOnSocial('twitter')" class="btn btn-sm" style="background: #1da1f2; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;" title="Share on Twitter">
                        <i class="fab fa-twitter"></i> Twitter
                    </button>
                    <button onclick="shareOnSocial('linkedin')" class="btn btn-sm" style="background: #0077b5; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;" title="Share on LinkedIn">
                        <i class="fab fa-linkedin-in"></i> LinkedIn
                    </button>
                    <button onclick="copyLink()" class="btn btn-sm" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;" title="Copy Link">
                        <i class="fas fa-link"></i> Copy
                    </button>
                    <button onclick="window.print()" class="btn btn-sm" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;" title="Print Page">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Main Content -->
            <article class="page-article" style="font-size: 16px; line-height: 1.8; color: #374151;">
                <?php echo $page['content']; ?>
            </article>
            
            <!-- Page Footer Info -->
            <div style="margin-top: 50px; padding: 25px; background: #f9fafb; border-radius: 10px; border-left: 4px solid #fd6501;">
                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                    <i class="far fa-clock"></i> 
                    <strong>Last updated:</strong> <?php echo formatDateTime($page['updated_at']); ?> by <?php echo htmlspecialchars($author_name); ?>
                </p>
            </div>
            
            <!-- Related Pages -->
            <?php if (!empty($related_pages)): ?>
            <div style="margin-top: 60px;">
                <h3 style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb; color: #1f2937;">
                    <i class="fas fa-link"></i> Related Pages
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach ($related_pages as $related): ?>
                    <a href="page.php?slug=<?php echo $related['slug']; ?>" style="display: block; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-decoration: none; transition: all 0.3s; border: 1px solid #e5e7eb;">
                        <?php if ($related['featured_image']): ?>
                            <img src="../uploads/pages/<?php echo $related['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>"
                                 style="width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 15px;">
                        <?php endif; ?>
                        <h4 style="margin: 0 0 10px 0; color: #1f2937; font-size: 18px;"><?php echo htmlspecialchars($related['title']); ?></h4>
                        <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.5;">
                            <?php echo truncate(strip_tags($related['content']), 100); ?>
                        </p>
                        <span style="display: inline-block; margin-top: 10px; color: #fd6501; font-size: 14px; font-weight: 500;">
                            Read more <i class="fas fa-arrow-right"></i>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
           
            
        </div>
    </div>
</div>

<style>
/* Page-specific styles */
.page-article img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
}

.page-article h2,
.page-article h3,
.page-article h4 {
    margin-top: 30px;
    margin-bottom: 15px;
    color: #1f2937;
}

.page-article p {
    margin-bottom: 20px;
}

.page-article ul,
.page-article ol {
    margin: 20px 0;
    padding-left: 30px;
}

.page-article li {
    margin-bottom: 10px;
}

.page-article blockquote {
    border-left: 4px solid #fd6501;
    padding: 15px 20px;
    margin: 25px 0;
    background: #f9fafb;
    font-style: italic;
    color: #4b5563;
}

.page-article a {
    color: #fd6501;
    text-decoration: underline;
}

.page-article a:hover {
    color: #1e40af;
}

/* Button hover effects */
.page-content button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transition: all 0.2s;
}

/* Related pages hover effect */
.page-content a[href*="page.php"]:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

/* Print styles */
@media print {
    .breadcrumbs,
    .page-content button,
    nav,
    footer {
        display: none !important;
    }
    
    .page-content {
        padding: 0 !important;
    }
    
    .page-header {
        background: white !important;
        color: black !important;
        padding: 20px 0 !important;
    }
}

/* Responsive styles */
@media (max-width: 768px) {
    .page-header h1 {
        font-size: 28px !important;
    }
    
    .page-content > div > div {
        padding: 0 15px;
    }
    
    .page-content button {
        font-size: 12px;
        padding: 6px 12px !important;
    }
    
    .breadcrumbs {
        font-size: 12px;
    }
}

/* Smooth scroll */
html {
    scroll-behavior: smooth;
}
</style>

<script>
// Social sharing functions
function shareOnSocial(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);
    
    let shareUrl = '';
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
            break;
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

// Copy link function
function copyLink() {
    const url = window.location.href;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copied to clipboard!');
    }
}
</script>

<?php include 'footer.php'; ?>