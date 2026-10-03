<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/helpers.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: blog.php');
    exit;
}

function safeDate(?string $date): string {
    if (function_exists('formatDate')) {
        return formatDate($date);
    }

    return $date ? date('F j, Y', strtotime($date)) : '';
}

function excerptText(string $text, int $limit = 160): string {
    $text = trim(strip_tags($text));

    if (function_exists('truncate')) {
        return truncate($text, $limit);
    }

    return mb_strlen($text) > $limit
        ? mb_substr($text, 0, $limit) . '...'
        : $text;
}

function currentPageUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
}

$stmt = $conn->prepare("
    SELECT *
    FROM news_blog
    WHERE slug = ?
      AND status = 'published'
    LIMIT 1
");
$stmt->bind_param('s', $slug);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$updateViews = $conn->prepare("UPDATE news_blog SET view_count = view_count + 1 WHERE id = ?");
$updateViews->bind_param('i', $post['id']);
$updateViews->execute();

$author_name = 'Admin';

if (!empty($post['author_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $post['author_id']);
    $stmt->execute();
    $author = $stmt->get_result()->fetch_assoc();

    if ($author && !empty($author['name'])) {
        $author_name = $author['name'];
    }
}

$related_posts = [];

$stmt = $conn->prepare("
    SELECT id, title, slug, category, featured_image, published_at, created_at
    FROM news_blog
    WHERE category = ?
      AND id != ?
      AND status = 'published'
    ORDER BY COALESCE(published_at, created_at) DESC
    LIMIT 3
");
$stmt->bind_param('si', $post['category'], $post['id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $related_posts[] = $row;
}

$tags = [];

if (!empty($post['tags'])) {
    $tags = array_filter(array_map('trim', explode(',', $post['tags'])));
}

$page_title = $post['title'];
$meta_description = !empty($post['excerpt'])
    ? $post['excerpt']
    : excerptText($post['content'], 160);

$body_class = 'single-blog-post';

$article_url = currentPageUrl();
$image_url = '';

if (!empty($post['featured_image'])) {
    $image_url = '../uploads/blog/' . $post['featured_image'];
}

$additional_head = '
<meta property="og:type" content="article">
<meta property="og:title" content="' . e($post['title']) . '">
<meta property="og:description" content="' . e($meta_description) . '">
<meta property="og:url" content="' . e($article_url) . '">
<meta property="article:published_time" content="' . e($post['published_at'] ?? $post['created_at']) . '">
<meta property="article:author" content="' . e($author_name) . '">
<meta property="article:section" content="' . e($post['category']) . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=Source+Serif+4:wght@300;400;500;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
';

if ($image_url !== '') {
    $additional_head .= '<meta property="og:image" content="' . e($image_url) . '">';
}

include __DIR__ . '/header.php';
?>

<style>
:root {
    --orange: #f86505;
    --orange-soft: rgba(248, 101, 5, .1);
    --orange-mid: rgba(248, 101, 5, .22);
    --dark: #111827;
    --dark-soft: #374151;
    --muted: #6b7280;
    --border: #e5e7eb;
    --light: #f9fafb;
    --white: #ffffff;
    --shadow: 0 20px 50px rgba(17, 24, 39, .10);
    --font-display: 'Playfair Display', Georgia, serif;
    --font-body: 'Source Serif 4', Georgia, serif;
    --font-ui: 'DM Sans', Arial, sans-serif;
}

body.single-blog-post {
    background: var(--light);
}

#read-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    height: 4px;
    background: var(--orange);
    z-index: 99999;
}

.article-hero {
    position: relative;
    min-height: 620px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    background: var(--dark);
}

.hero-bg {
    position: absolute;
    inset: 0;
}

.hero-bg img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(to top, rgba(0,0,0,.88), rgba(0,0,0,.54), rgba(0,0,0,.18)),
        radial-gradient(circle at 15% 80%, rgba(248,101,5,.45), transparent 35%);
}

.hero-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1120px;
    margin: 0 auto;
    padding: 90px 24px;
    color: #fff;
}

.breadcrumb {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    font-family: var(--font-ui);
    font-size: 13px;
    color: rgba(255,255,255,.75);
}

.breadcrumb a {
    color: rgba(255,255,255,.85);
    text-decoration: none;
}

.breadcrumb a:hover {
    color: var(--orange);
}

.article-category {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 999px;
    background: rgba(248,101,5,.18);
    border: 1px solid rgba(248,101,5,.38);
    color: #fff;
    font-family: var(--font-ui);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.article-hero h1 {
    max-width: 900px;
    margin: 24px 0;
    font-family: var(--font-display);
    font-size: clamp(34px, 6vw, 72px);
    line-height: 1.02;
    font-weight: 900;
}

.hero-excerpt {
    max-width: 780px;
    font-family: var(--font-body);
    font-size: 21px;
    line-height: 1.7;
    color: rgba(255,255,255,.82);
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-top: 34px;
    font-family: var(--font-ui);
    font-size: 14px;
    color: rgba(255,255,255,.82);
}

.hero-meta div {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.article-shell {
    max-width: 1180px;
    margin: -70px auto 0;
    padding: 0 24px 80px;
    position: relative;
    z-index: 5;
}

.article-card {
    background: var(--white);
    border-radius: 24px;
    box-shadow: var(--shadow);
    padding: 54px;
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) 280px;
    gap: 38px;
}

.floating-share {
    position: sticky;
    top: 110px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.floating-share button {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--white);
    color: var(--orange);
    cursor: pointer;
    transition: .25s ease;
}

.floating-share button:hover {
    background: var(--orange);
    color: #fff;
    transform: translateY(-2px);
}

.article-body {
    min-width: 0;
    font-family: var(--font-body);
    font-size: 20px;
    line-height: 1.88;
    color: var(--dark-soft);
}

.article-body > *:first-child {
    margin-top: 0;
}

.article-body p {
    margin: 0 0 1.6em;
}

.article-body h2,
.article-body h3,
.article-body h4 {
    color: var(--dark);
    line-height: 1.25;
}

.article-body h2 {
    margin: 2.3em 0 .7em;
    font-family: var(--font-display);
    font-size: 36px;
}

.article-body h3 {
    margin: 2em 0 .7em;
    font-family: var(--font-display);
    font-size: 27px;
}

.article-body h4 {
    margin: 1.8em 0 .7em;
    font-family: var(--font-ui);
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--orange);
}

.article-body a {
    color: var(--orange);
    text-underline-offset: 4px;
}

.article-body img {
    max-width: 100%;
    height: auto;
    border-radius: 18px;
    margin: 26px 0;
}

.article-body blockquote {
    margin: 34px 0;
    padding: 28px 32px;
    border-left: 5px solid var(--orange);
    background: var(--orange-soft);
    border-radius: 16px;
    font-family: var(--font-display);
    font-size: 23px;
    color: var(--dark);
}

.article-body ul,
.article-body ol {
    margin: 1.3em 0 1.7em;
    padding-left: 1.4em;
}

.article-body li {
    margin-bottom: .55em;
}

.article-body pre {
    background: #0f172a;
    color: #e5e7eb;
    padding: 24px;
    border-radius: 16px;
    overflow-x: auto;
    font-size: 14px;
    line-height: 1.7;
}

.article-body code {
    background: var(--orange-soft);
    color: var(--orange);
    padding: 2px 6px;
    border-radius: 6px;
}

.article-body pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}

.article-sidebar {
    position: sticky;
    top: 110px;
}

.sidebar-box {
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 24px;
    background: #fff;
}

.sidebar-title {
    margin: 0 0 16px;
    font-family: var(--font-ui);
    font-size: 12px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--muted);
    font-weight: 800;
}

#toc-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

#toc-list li {
    margin-bottom: 8px;
}

#toc-list a {
    display: block;
    padding: 7px 0 7px 12px;
    border-left: 2px solid transparent;
    color: var(--muted);
    text-decoration: none;
    font-family: var(--font-ui);
    font-size: 13px;
    line-height: 1.45;
}

#toc-list a:hover,
#toc-list a.active {
    color: var(--orange);
    border-left-color: var(--orange);
}

.side-related {
    display: flex;
    gap: 13px;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--border);
    text-decoration: none;
}

.side-related:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: 0;
}

.side-related img,
.side-related-placeholder {
    width: 76px;
    height: 58px;
    object-fit: cover;
    border-radius: 12px;
    flex-shrink: 0;
}

.side-related-placeholder {
    background: var(--orange-soft);
    color: var(--orange);
    display: flex;
    align-items: center;
    justify-content: center;
}

.side-related strong {
    display: block;
    color: var(--dark);
    font-family: var(--font-ui);
    font-size: 13px;
    line-height: 1.4;
}

.side-related span {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-family: var(--font-ui);
    font-size: 11px;
}

.tags-section,
.author-card,
.article-nav,
.newsletter-section,
.related-section {
    grid-column: 2 / 3;
}

.tags-section {
    margin-top: 44px;
    padding-top: 30px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tag-link {
    padding: 8px 15px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--light);
    color: var(--dark-soft);
    text-decoration: none;
    font-family: var(--font-ui);
    font-size: 13px;
    font-weight: 600;
}

.tag-link:hover {
    background: var(--orange-soft);
    color: var(--orange);
    border-color: var(--orange-mid);
}

.author-card {
    margin-top: 42px;
    display: flex;
    gap: 20px;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 20px;
    background: var(--light);
}

.author-avatar {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: var(--orange);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 900;
    flex-shrink: 0;
}

.author-card h4 {
    margin: 0 0 6px;
    color: var(--dark);
    font-family: var(--font-display);
    font-size: 22px;
}

.author-card p {
    margin: 0;
    color: var(--muted);
    line-height: 1.7;
}

.article-nav {
    margin-top: 44px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.article-nav a {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--dark);
    text-decoration: none;
    font-family: var(--font-ui);
    font-weight: 700;
}

.article-nav a:hover {
    color: var(--orange);
}

.related-section {
    margin-top: 70px;
    padding-top: 45px;
    border-top: 2px solid var(--dark);
}

.related-heading,
.newsletter-section h2 {
    margin: 0 0 28px;
    font-family: var(--font-display);
    font-size: 34px;
    color: var(--dark);
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.related-card {
    overflow: hidden;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: #fff;
    text-decoration: none;
    transition: .25s ease;
}

.related-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.related-card img,
.related-placeholder {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.related-placeholder {
    background: var(--orange-soft);
    color: var(--orange);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
}

.related-card-body {
    padding: 20px;
}

.related-card-body small {
    color: var(--orange);
    font-family: var(--font-ui);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.related-card-body h4 {
    margin: 10px 0;
    font-family: var(--font-display);
    color: var(--dark);
    font-size: 19px;
    line-height: 1.35;
}

.related-card-body span {
    color: var(--muted);
    font-family: var(--font-ui);
    font-size: 13px;
}

.newsletter-section {
    margin-top: 70px;
    padding: 48px;
    border-radius: 24px;
    background: linear-gradient(135deg, #f86505, #ff9345);
    color: #fff;
    text-align: center;
}

.newsletter-section h2 {
    color: #fff;
}

.newsletter-section p {
    max-width: 620px;
    margin: 0 auto 24px;
    font-size: 17px;
}

.newsletter-section form {
    display: flex;
    justify-content: center;
    gap: 10px;
    max-width: 560px;
    margin: 0 auto;
}

.newsletter-section input {
    flex: 1;
    border: 0;
    border-radius: 999px;
    padding: 15px 18px;
    outline: none;
}

.newsletter-section button {
    border: 0;
    border-radius: 999px;
    padding: 15px 26px;
    background: var(--dark);
    color: #fff;
    font-weight: 700;
    cursor: pointer;
}

.newsletter-section button:disabled {
    opacity: .7;
    cursor: not-allowed;
}

.newsletter-message {
    margin: 18px auto 0;
    max-width: 560px;
    padding: 12px 16px;
    border-radius: 12px;
    font-family: var(--font-ui);
    font-size: 14px;
    display: none;
}

.newsletter-message.success {
    display: block;
    background: rgba(34, 197, 94, .15);
    color: #fff;
    border: 1px solid rgba(255,255,255,.4);
}

.newsletter-message.error {
    display: block;
    background: rgba(239, 68, 68, .18);
    color: #fff;
    border: 1px solid rgba(255,255,255,.4);
}

.toast-copy {
    position: fixed;
    right: 24px;
    bottom: 24px;
    background: var(--dark);
    color: #fff;
    padding: 13px 18px;
    border-radius: 12px;
    font-family: var(--font-ui);
    font-size: 14px;
    opacity: 0;
    pointer-events: none;
    transform: translateY(15px);
    transition: .25s ease;
    z-index: 9999;
}

.toast-copy.show {
    opacity: 1;
    transform: translateY(0);
}

@media (max-width: 1100px) {
    .article-card {
        grid-template-columns: 60px minmax(0, 1fr);
    }

    .article-sidebar {
        display: none;
    }

    .tags-section,
    .author-card,
    .article-nav,
    .newsletter-section,
    .related-section {
        grid-column: 2 / 3;
    }
}

@media (max-width: 768px) {
    .article-hero {
        min-height: 520px;
    }

    .hero-content {
        padding: 70px 20px;
    }

    .hero-excerpt {
        font-size: 18px;
    }

    .article-shell {
        margin-top: -45px;
        padding: 0 14px 60px;
    }

    .article-card {
        grid-template-columns: 1fr;
        padding: 28px 20px;
        border-radius: 18px;
    }

    .floating-share {
        position: static;
        flex-direction: row;
        order: 2;
    }

    .article-body {
        font-size: 18px;
    }

    .tags-section,
    .author-card,
    .article-nav,
    .newsletter-section,
    .related-section {
        grid-column: 1 / -1;
    }

    .related-grid {
        grid-template-columns: 1fr;
    }

    .newsletter-section {
        padding: 34px 20px;
    }

    .newsletter-section form {
        flex-direction: column;
    }

    .article-nav {
        flex-direction: column;
    }
}
</style>

<div id="read-progress"></div>

<header class="article-hero">
    <?php if (!empty($post['featured_image'])): ?>
        <div class="hero-bg">
            <img src="../uploads/blog/<?php echo e($post['featured_image']); ?>"
                 alt="<?php echo e($post['title']); ?>">
        </div>
    <?php endif; ?>

    <div class="hero-overlay"></div>

    <div class="hero-content">
       

        <span class="article-category">
            <i class="fas fa-tag"></i>
            <?php echo e($post['category']); ?>
        </span>

        <h1><?php echo e($post['title']); ?></h1>

        <?php if (!empty($post['excerpt'])): ?>
            <p class="hero-excerpt"><?php echo e($post['excerpt']); ?></p>
        <?php endif; ?>

        <div class="hero-meta">
            <!--<div>
                <i class="fas fa-user"></i>
                <?php echo e($author_name); ?>
            </div> -->

            <div>
                <i class="fas fa-calendar-alt"></i>
                <?php echo safeDate($post['published_at'] ?: $post['created_at']); ?>
            </div>

            <div>
                <i class="fas fa-eye"></i>
                <?php echo number_format((int)$post['view_count'] + 1); ?> views
            </div>

            
        </div>
    </div>
</header>

<div class="article-shell">
    <div class="article-card">

        <div class="floating-share">
            <button type="button" onclick="shareOnSocial('facebook')" title="Share on Facebook">
                <i class="fab fa-facebook-f"></i>
            </button>

            <button type="button" onclick="shareOnSocial('twitter')" title="Share on X">
                <i class="fab fa-x-twitter"></i>
            </button>

            <button type="button" onclick="shareOnSocial('linkedin')" title="Share on LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </button>

            <button type="button" onclick="copyLink()" title="Copy link">
                <i class="fas fa-link"></i>
            </button>
        </div>

        <div class="article-content-column">
            <article class="article-body" id="article-content">
                <?php echo $post['content']; ?>
            </article>
        </div>

        <aside class="article-sidebar">
            <div class="sidebar-box" id="toc-section" style="display:none;">
                <p class="sidebar-title">In this article</p>
                <ul id="toc-list"></ul>
            </div>

            <?php if (!empty($related_posts)): ?>
                <div class="sidebar-box">
                    <p class="sidebar-title">Related articles</p>

                    <?php foreach ($related_posts as $related): ?>
                        <a class="side-related" href="blog-post.php?slug=<?php echo urlencode($related['slug']); ?>">
                            <?php if (!empty($related['featured_image'])): ?>
                                <img src="../uploads/blog/<?php echo e($related['featured_image']); ?>"
                                     alt="<?php echo e($related['title']); ?>">
                            <?php else: ?>
                                <div class="side-related-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <strong><?php echo e($related['title']); ?></strong>
                                <span><?php echo safeDate($related['published_at'] ?: $related['created_at']); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <?php if (!empty($tags)): ?>
            <div class="tags-section">
                <?php foreach ($tags as $tag): ?>
                    <a href="blog.php?search=<?php echo urlencode($tag); ?>" class="tag-link">
                        #<?php echo e($tag); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

      

        <div class="article-nav">
            <a href="blog.php">
                <i class="fas fa-arrow-left"></i>
                Back to all articles
            </a>

            <a href="#read-progress" onclick="window.scrollTo({top:0, behavior:'smooth'}); return false;">
                Back to top
                <i class="fas fa-arrow-up"></i>
            </a>
        </div>

        <?php if (!empty($related_posts)): ?>
            <section class="related-section">
                <h2 class="related-heading">More like this</h2>

                <div class="related-grid">
                    <?php foreach ($related_posts as $related): ?>
                        <a class="related-card" href="blog-post.php?slug=<?php echo urlencode($related['slug']); ?>">
                            <?php if (!empty($related['featured_image'])): ?>
                                <img src="../uploads/blog/<?php echo e($related['featured_image']); ?>"
                                     alt="<?php echo e($related['title']); ?>">
                            <?php else: ?>
                                <div class="related-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>

                            <div class="related-card-body">
                                <small><?php echo e($related['category'] ?: $post['category']); ?></small>
                                <h4><?php echo e($related['title']); ?></h4>
                                <span><?php echo safeDate($related['published_at'] ?: $related['created_at']); ?></span>
                            </div> 
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="newsletter-section">
            <h2>Stay Updated</h2>
            <p>Get the latest news, stories, opportunities and updates directly in your inbox.</p>

        <form id="newsletterForm" method="post" action="javascript:void(0);">
    <input type="email" name="email" placeholder="Enter your email address" required>
    <button type="submit" id="newsletterBtn">Subscribe</button>
</form>

<div id="newsletterMessage" class="newsletter-message"></div>
        </section>

    </div>
</div>

<div class="toast-copy" id="toast-copy">Link copied successfully</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const progressBar = document.getElementById('read-progress');

    if (progressBar) {
        window.addEventListener('scroll', function () {
            const doc = document.documentElement;
            const total = doc.scrollHeight - doc.clientHeight;
            const progress = total > 0 ? (doc.scrollTop / total) * 100 : 0;
            progressBar.style.width = progress + '%';
        });
    }

    const articleContent = document.getElementById('article-content');
    const readTime = document.getElementById('read-time');

    if (articleContent && readTime) {
        const words = articleContent.innerText.trim().split(/\s+/).filter(Boolean).length;
        const minutes = Math.max(1, Math.ceil(words / 220));
        readTime.textContent = minutes + ' min read';
    }

    (function buildTOC() {
        const headings = document.querySelectorAll('#article-content h2, #article-content h3');
        const tocSection = document.getElementById('toc-section');
        const tocList = document.getElementById('toc-list');

        if (!tocSection || !tocList || headings.length < 2) return;

        tocSection.style.display = 'block';

        headings.forEach(function (heading, index) {
            const id = 'section-' + index;
            heading.id = id;

            const li = document.createElement('li');
            const a = document.createElement('a');

            a.href = '#' + id;
            a.textContent = heading.textContent;

            if (heading.tagName === 'H3') {
                a.style.paddingLeft = '24px';
            }

            a.addEventListener('click', function (event) {
                event.preventDefault();

                heading.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

            li.appendChild(a);
            tocList.appendChild(li);
        });

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                const link = tocList.querySelector('a[href="#' + entry.target.id + '"]');

                if (!link) return;

                if (entry.isIntersecting) {
                    tocList.querySelectorAll('a').forEach(function (a) {
                        a.classList.remove('active');
                    });

                    link.classList.add('active');
                }
            });
        }, {
            rootMargin: '-25% 0px -65% 0px'
        });

        headings.forEach(function (heading) {
            observer.observe(heading);
        });
    })();

    const newsletterForm = document.getElementById('newsletterForm');
    const newsletterBtn = document.getElementById('newsletterBtn');
    const newsletterMessage = document.getElementById('newsletterMessage');

    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!newsletterMessage || !newsletterBtn) return;

            newsletterMessage.className = 'newsletter-message';
            newsletterMessage.style.display = 'block';
            newsletterMessage.textContent = 'Subscribing...';

            newsletterBtn.disabled = true;
            newsletterBtn.textContent = 'Please wait...';

            try {
                const response = await fetch('/newsletter-subscribe.php', {
                    method: 'POST',
                    body: new FormData(newsletterForm),
                    headers: {
                        'Accept': 'application/json'
                    },
                    cache: 'no-store'
                });

                const rawText = await response.text();

                let data;

                try {
                    data = JSON.parse(rawText);
                } catch (jsonError) {
                    throw new Error(rawText || 'Invalid server response.');
                }

                newsletterMessage.textContent = data.message || 'Request completed.';
                newsletterMessage.className = data.success
                    ? 'newsletter-message success'
                    : 'newsletter-message error';

                if (data.success) {
                    newsletterForm.reset();
                }

            } catch (error) {
                newsletterMessage.textContent = error.message || 'Something went wrong. Please try again.';
                newsletterMessage.className = 'newsletter-message error';
            }

            newsletterBtn.disabled = false;
            newsletterBtn.textContent = 'Subscribe';
        });
    }
});

function shareOnSocial(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);

    const shareUrls = {
        facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
        twitter: 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title,
        linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + url
    };

    if (shareUrls[platform]) {
        window.open(
            shareUrls[platform],
            '_blank',
            'width=700,height=520'
        );
    }
}

function copyLink() {
    const toast = document.getElementById('toast-copy');

    if (!navigator.clipboard) {
        fallbackCopy(window.location.href);
        showToast(toast);
        return;
    }

    navigator.clipboard.writeText(window.location.href).then(function () {
        showToast(toast);
    });
}

function fallbackCopy(text) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
}

function showToast(toast) {
    if (!toast) return;

    toast.classList.add('show');

    setTimeout(function () {
        toast.classList.remove('show');
    }, 2300);
}
</script>
<?php include __DIR__ . '/footer.php'; ?>