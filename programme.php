<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/helpers.php';

function pd_decode($value): string
{
    $text = (string)($value ?? '');

    // Remove database/browser escaped slashes like WITU\\&amp;#039;s
    $text = str_replace(['\\\\', '\&', "\\'", '\\"'], ['\\', '&', "'", '"'], $text);
    $text = stripslashes($text);

    // Decode repeatedly because some values are double-encoded:
    // &amp;#039; => &#039; => '
    for ($i = 0; $i < 5; $i++) {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded === $text) {
            break;
        }

        $text = $decoded;
    }

    return trim($text);
}

function pd_e($value): string
{
    return htmlspecialchars(pd_decode($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function pd_clean_html($value): string
{
    $html = pd_decode($value);

    return $html;
}

function pd_trim_text($value, int $limit = 120): string
{
    $text = trim(strip_tags(pd_decode($value)));

    if (function_exists('mb_strlen')) {
        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit) . '...'
            : $text;
    }

    return strlen($text) > $limit
        ? substr($text, 0, $limit) . '...'
        : $text;
}

function pd_programme_link(array $programme): string
{
    $slug = trim((string)($programme['slug'] ?? ''));

    return $slug !== ''
        ? 'programme.php?slug=' . urlencode($slug)
        : '#';
}

function pd_programme_image(array $programme): string
{
    $image = trim((string)($programme['featured_image'] ?? ''));

    return $image !== ''
        ? 'uploads/programmes/' . rawurlencode($image)
        : '';
}

$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    header('Location: programmes.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT p.*, parent.title AS parent_title, parent.slug AS parent_slug
    FROM programmes p
    LEFT JOIN programmes parent ON parent.id = p.parent_id
    WHERE p.slug = ?
      AND p.status = 'published'
    LIMIT 1
");

$stmt->bind_param('s', $slug);
$stmt->execute();

$programme = $stmt->get_result()->fetch_assoc();

if (!$programme) {
    header('HTTP/1.1 404 Not Found');
    include '404.php';
    exit;
}

$programme_id = (int)$programme['id'];
$parent_id    = (int)($programme['parent_id'] ?? 0);

$child_stmt = $conn->prepare("
    SELECT id, title, slug, summary, description, featured_image, icon
    FROM programmes
    WHERE status = 'published'
      AND parent_id = ?
    ORDER BY display_order ASC, id ASC
");

$child_stmt->bind_param('i', $programme_id);
$child_stmt->execute();

$children = $child_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$siblings = [];

if ($parent_id > 0) {
    $sib_stmt = $conn->prepare("
        SELECT id, title, slug, summary, description, featured_image, icon
        FROM programmes
        WHERE status = 'published'
          AND parent_id = ?
          AND id != ?
        ORDER BY display_order ASC, id ASC
    ");

    $sib_stmt->bind_param('ii', $parent_id, $programme_id);
    $sib_stmt->execute();

    $siblings = $sib_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$rel_stmt = $conn->prepare("
    SELECT id, title, slug, summary, description, featured_image, icon
    FROM programmes
    WHERE status = 'published'
      AND id != ?
      AND (parent_id IS NULL OR parent_id = 0)
    ORDER BY RAND()
    LIMIT 3
");

$rel_stmt->bind_param('i', $programme_id);
$rel_stmt->execute();

$related = $rel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = pd_e(!empty($programme['meta_title']) ? $programme['meta_title'] : $programme['title']);

$meta_description = pd_e(
    !empty($programme['meta_description'])
        ? $programme['meta_description']
        : ($programme['summary'] ?? '')
);

$body_class = 'programme-detail-page';

$current_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

include 'header.php';
?>

<style>
.pd-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,0.14);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
}

.pd-breadcrumb a {
    color: #fff;
    text-decoration: none;
}

.pd-breadcrumb a:hover {
    text-decoration: underline;
}

.pd-child-section {
    margin-top: 34px;
    padding: 28px;
    border-radius: 24px;
    background: #fff7ed;
    border: 1px solid rgba(248, 101, 5, 0.18);
}

.pd-child-section h2 {
    margin: 0 0 10px;
    font-size: 1.55rem;
    color: #111827;
}

.pd-child-section p {
    margin: 0 0 22px;
    color: #6b7280;
    line-height: 1.7;
}

.pd-child-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.pd-child-card {
    display: flex;
    gap: 14px;
    padding: 16px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid rgba(229, 231, 235, 0.95);
    color: inherit;
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease;
}

.pd-child-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.1);
}

.pd-child-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f86505;
    color: #fff;
    flex-shrink: 0;
}

.pd-child-body h3 {
    margin: 0 0 6px;
    font-size: 1rem;
    color: #111827;
}

.pd-child-body span {
    display: block;
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.55;
}

.pd-parent-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 15px;
    padding: 9px 13px;
    border-radius: 999px;
    background: rgba(248, 101, 5, 0.1);
    color: #9a3412;
    font-weight: 800;
    font-size: 0.86rem;
    text-decoration: none;
}

.pd-parent-pill:hover {
    background: rgba(248, 101, 5, 0.16);
}

@media (max-width: 768px) {
    .pd-child-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="pd-hero <?php echo !empty($programme['featured_image']) ? 'has-photo' : 'no-photo'; ?>">
    <?php if (!empty($programme['featured_image'])): ?>
        <div class="pd-hero-bg" style="background-image:url('<?php echo pd_e(pd_programme_image($programme)); ?>');"></div>
    <?php endif; ?>

    <div class="pd-hero-overlay"></div>
    <div class="pd-hero-slash"></div>

    <div class="container pd-hero-inner">

        <div class="pd-breadcrumb">
            <a href="programmes.php">Programmes</a>

            <?php if ($parent_id > 0 && !empty($programme['parent_title'])): ?>
                <i class="fas fa-angle-right"></i>
                <a href="programme.php?slug=<?php echo urlencode($programme['parent_slug']); ?>">
                    <?php echo pd_e($programme['parent_title']); ?>
                </a>
            <?php endif; ?>

            <i class="fas fa-angle-right"></i>
            <span><?php echo pd_e($programme['title']); ?></span>
        </div>

        <h1><?php echo pd_e($programme['title']); ?></h1>

        <?php if (!empty($programme['summary'])): ?>
            <p class="pd-hero-sub"><?php echo pd_e($programme['summary']); ?></p>
        <?php endif; ?>

        <div class="pd-hero-actions">
            <a href="contact.php?programme=<?php echo urlencode($programme['slug']); ?>" class="witu-btn witu-btn-primary">
                <i class="fas fa-paper-plane"></i> Apply Now
            </a>
        </div>
    </div>
</section>

<div class="pd-body">
    <div class="container pd-layout">

        <main class="pd-main">

            <?php if (!empty($programme['description'])): ?>
                <div class="pd-lead-block">
                    <i class="fas fa-quote-left pd-lead-icon"></i>
                    <p><?php echo nl2br(pd_e($programme['description'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($programme['content'])): ?>
                <div class="pd-content">
                    <?php echo pd_clean_html($programme['content']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($children)): ?>
                <section class="pd-child-section">
                    <h2>Programmes under <?php echo pd_e($programme['title']); ?></h2>
                    <p>Explore the related programmes connected to this main programme.</p>

                    <div class="pd-child-grid">
                        <?php foreach ($children as $child): ?>
                            <a href="<?php echo pd_e(pd_programme_link($child)); ?>" class="pd-child-card">
                                <span class="pd-child-icon">
                                    <i class="<?php echo pd_e(!empty($child['icon']) ? $child['icon'] : 'fas fa-star'); ?>"></i>
                                </span>

                                <span class="pd-child-body">
                                    <h3><?php echo pd_e($child['title']); ?></h3>

                                    <?php $child_text = pd_trim_text($child['summary'] ?? $child['description'] ?? '', 115); ?>

                                    <?php if ($child_text !== ''): ?>
                                        <span><?php echo pd_e($child_text); ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($siblings)): ?>
                <section class="pd-child-section">
                    <h2>Other programmes under <?php echo pd_e($programme['parent_title']); ?></h2>
                    <p>These programmes are part of the same parent programme.</p>

                    <div class="pd-child-grid">
                        <?php foreach ($siblings as $sibling): ?>
                            <a href="<?php echo pd_e(pd_programme_link($sibling)); ?>" class="pd-child-card">
                                <span class="pd-child-icon">
                                    <i class="<?php echo pd_e(!empty($sibling['icon']) ? $sibling['icon'] : 'fas fa-star'); ?>"></i>
                                </span>

                                <span class="pd-child-body">
                                    <h3><?php echo pd_e($sibling['title']); ?></h3>

                                    <?php $sibling_text = pd_trim_text($sibling['summary'] ?? $sibling['description'] ?? '', 115); ?>

                                    <?php if ($sibling_text !== ''): ?>
                                        <span><?php echo pd_e($sibling_text); ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </main>

        <aside class="pd-sidebar">

            <div class="pd-apply-card">
                <div class="pd-apply-icon">
                    <i class="fas fa-rocket"></i>
                </div>

                <h3>Ready to join?</h3>
                <p>Apply to <?php echo pd_e($programme['title']); ?> and begin your journey.</p>

                <a href="contact.php?programme=<?php echo urlencode($programme['slug']); ?>" class="witu-btn witu-btn-primary pd-apply-btn">
                    <i class="fas fa-paper-plane"></i> Apply Now
                </a>
            </div>

            <div class="pd-info-card">
                <h4 class="pd-info-title">
                    <i class="fas fa-info-circle"></i> Programme Details
                </h4>

                <ul class="pd-info-list">
                    <li>
                        <i class="fas fa-tag"></i>
                        <div>
                            <strong>Programme</strong>
                            <span><?php echo pd_e($programme['title']); ?></span>
                        </div>
                    </li>

                    <?php if ($parent_id > 0 && !empty($programme['parent_title'])): ?>
                        <li>
                            <i class="fas fa-sitemap"></i>
                            <div>
                                <strong>Parent Programme</strong>
                                <span>
                                    <a href="programme.php?slug=<?php echo urlencode($programme['parent_slug']); ?>">
                                        <?php echo pd_e($programme['parent_title']); ?>
                                    </a>
                                </span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <li>
                        <i class="fas fa-check-circle" style="color:#10b981;"></i>
                        <div>
                            <strong>Status</strong>
                            <span class="pd-status-pill">Accepting Applications</span>
                        </div>
                    </li>

                    <?php if (!empty($programme['duration'])): ?>
                        <li>
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Duration</strong>
                                <span><?php echo pd_e($programme['duration']); ?></span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($programme['location'])): ?>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>Location</strong>
                                <span><?php echo pd_e($programme['location']); ?></span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($programme['eligibility'])): ?>
                        <li>
                            <i class="fas fa-users"></i>
                            <div>
                                <strong>Eligibility</strong>
                                <span><?php echo pd_e($programme['eligibility']); ?></span>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php if ($parent_id > 0 && !empty($programme['parent_slug'])): ?>
                    <a href="programme.php?slug=<?php echo urlencode($programme['parent_slug']); ?>" class="pd-parent-pill">
                        <i class="fas fa-arrow-left"></i>
                        Back to <?php echo pd_e($programme['parent_title']); ?>
                    </a>
                <?php endif; ?>

                <a href="programmes.php" class="pd-back-link">
                    <i class="fas fa-arrow-left"></i> All Programmes
                </a>
            </div>

            <div class="pd-share-card">
                <h4 class="pd-info-title">
                    <i class="fas fa-share-alt"></i> Share
                </h4>

                <div class="pd-share-btns">
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(pd_decode($programme['title']) . ' - WITU'); ?>&url=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener" class="pd-share-btn twitter" aria-label="Share on X / Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>

                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener" class="pd-share-btn facebook" aria-label="Share on Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>

                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($current_url); ?>&title=<?php echo urlencode(pd_decode($programme['title'])); ?>" target="_blank" rel="noopener" class="pd-share-btn linkedin" aria-label="Share on LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>

                    <a href="https://wa.me/?text=<?php echo urlencode(pd_decode($programme['title']) . ' - ' . $current_url); ?>" target="_blank" rel="noopener" class="pd-share-btn whatsapp" aria-label="Share on WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>

        </aside>
    </div>
</div>

<?php if (!empty($related)): ?>
<section class="pd-related">
    <div class="container">
        <div class="pd-related-head">
            <h2>Other Programmes</h2>
        </div>

        <div class="pd-related-grid">
            <?php
            $rel_accents = ['#f86505', '#2e6f8a', '#66c2c4'];

            foreach ($related as $idx => $rel):
                $accent = $rel_accents[$idx % count($rel_accents)];
                $rel_img = pd_programme_image($rel);
            ?>
                <article class="pd-rel-card" style="--rel-accent:<?php echo pd_e($accent); ?>;">
                    <a href="<?php echo pd_e(pd_programme_link($rel)); ?>" class="pd-rel-thumb" tabindex="-1" aria-hidden="true">
                        <?php if ($rel_img !== ''): ?>
                            <img src="<?php echo pd_e($rel_img); ?>" alt="<?php echo pd_e($rel['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="pd-rel-placeholder">
                                <i class="<?php echo pd_e(!empty($rel['icon']) ? $rel['icon'] : 'fas fa-star'); ?>"></i>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="pd-rel-body">
                        <h3>
                            <a href="<?php echo pd_e(pd_programme_link($rel)); ?>">
                                <?php echo pd_e($rel['title']); ?>
                            </a>
                        </h3>

                        <p><?php echo pd_e(pd_trim_text($rel['summary'] ?? $rel['description'] ?? '', 100)); ?></p>

                        <a href="<?php echo pd_e(pd_programme_link($rel)); ?>" class="pd-rel-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="pg-cta">
    <div class="container pg-cta-inner">
        <div class="pg-cta-text">
            <h2>Take the next step</h2>
            <p>Apply today, partner with WITU, or support our work to help women and girls build skills, agency, and economic independence.</p>
        </div>

        <div class="pg-cta-btns">
            <a href="contact.php?programme=<?php echo urlencode($programme['slug']); ?>" class="witu-btn witu-btn-white">
                <i class="fas fa-paper-plane"></i> Apply Now
            </a>

            <a href="donate.php" class="witu-btn witu-btn-outline-white">Donate</a>
            <a href="partner.php" class="witu-btn witu-btn-outline-white">Partner With Us</a>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>