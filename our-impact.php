<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$hero = getHeroImage($conn, 'impact');

$page = [
    'hero_title'        => 'Our Impact',
    'hero_subtitle'     => '',
    'intro_title'       => 'Overall Impact',
    'intro_text'        => "Around here, we're really big on numbers... our results speak for themselves.\n\nOur programs and services are dedicated to empowering young girls and women by enhancing their learning outcomes, enabling STEM excellence, and facilitating access to global job opportunities.\n\nThrough our initiatives, participants gain confidence, attain financial independence, and enjoy an improved quality of life.",
    'annual_cta_title'  => 'Want to see the official impact data?',
    'annual_cta_text'   => 'Our annual reports speak to the success of the WITU model and the lives our Programmes have changed.',
    'annual_cta_button' => 'Read the Annual Reports',
    'annual_cta_link'   => 'annual-reports.php',
];

$res = $conn->query("SELECT * FROM impact_page WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $page = array_merge($page, $row);
}

$current = [];
$alumni  = [];

$res = $conn->query("
    SELECT 
        id,
        group_key,
        stat_value,
        stat_label,
        icon_class,
        bg_color,
        title_color,
        label_color,
        sort_order,
        is_active
    FROM impact_stats
    WHERE is_active = 1
    ORDER BY group_key ASC, sort_order ASC, id ASC
");

if ($res) {
    while ($r = $res->fetch_assoc()) {
        if (($r['group_key'] ?? '') === 'alumni') {
            $alumni[] = $r;
        } else {
            $current[] = $r;
        }
    }
}

$storiesCategory = 'Impact Stories';
$stories = [];

$st = $conn->prepare("
    SELECT title, slug, excerpt, content, featured_image, published_at, created_at
    FROM news_blog
    WHERE status = 'published' AND category = ?
    ORDER BY published_at DESC, created_at DESC
    LIMIT 4
");

if ($st) {
    $st->bind_param('s', $storiesCategory);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $stories[] = $r;
    }
    $st->close();
}

// One extra row is fetched only to learn whether more than three exist.
$hasMoreStories = count($stories) > 3;
$stories = array_slice($stories, 0, 3);

$storiesLink = 'blog.php?category=' . urlencode($storiesCategory);

$page_title = 'Our Impact';
include 'header.php';
?>

<style>
:root {
    --orange:#f86505;
    --dark:#0d0d0d;
    --charcoal:#1a1a1a;
    --mid:#3d3d3d;
    --muted:#7a7a7a;
    --cream:#faf8f5;
    --border:rgba(0,0,0,.08);
    --ease:cubic-bezier(.22,.61,.36,1);
}

.imp-wrap {
    width:min(1180px, 92vw);
    margin-inline:auto;
}

.imp-hero {
    position:relative;
    min-height:56vh;
    display:flex;
    align-items:center;
    background:var(--charcoal);
    overflow:hidden;
}

.imp-hero-bg {
    position:absolute;
    inset:0;
    background-size:cover;
    background-position:center;
    filter:brightness(.32) saturate(.5);
    transform:scale(1.04);
    animation:impZoom 10s var(--ease) forwards;
}

@keyframes impZoom {
    to { transform:scale(1); }
}

.imp-hero-slash {
    position:absolute;
    right:-60px;
    top:0;
    bottom:0;
    width:36vw;
    background:var(--orange);
    opacity:.07;
    clip-path:polygon(28% 0,100% 0,100% 100%,0% 100%);
}

.imp-hero-grain {
    position:absolute;
    inset:0;
    opacity:.04;
    pointer-events:none;
}

.imp-hero-inner {
    position:relative;
    z-index:2;
    padding:90px 0 80px;
}

.imp-eyebrow,
.imp-section-kicker {
    display:inline-flex;
    align-items:center;
    gap:10px;
    font-size:10px;
    font-weight:700;
    letter-spacing:.22em;
    text-transform:uppercase;
    color:var(--orange);
}

.imp-eyebrow {
    margin-bottom:18px;
    opacity:0;
    animation:impFadeUp .65s var(--ease) .1s forwards;
}

.imp-eyebrow::before,
.imp-section-kicker::before {
    content:'';
    display:block;
    width:28px;
    height:2px;
    background:var(--orange);
}

.imp-hero h1 {
    font-size:clamp(2.2rem, 5vw, 4.2rem);
    font-weight:900;
    line-height:1.1;
    color:#fff;
    max-width:720px;
    opacity:0;
    animation:impFadeUp .8s var(--ease) .22s forwards;
}

.imp-hero-sub {
    margin-top:18px;
    font-size:1.05rem;
    color:rgba(255,255,255,.72);
    max-width:560px;
    line-height:1.72;
    opacity:0;
    animation:impFadeUp .8s var(--ease) .36s forwards;
}

.imp-intro {
    background:var(--cream);
    padding:80px 0 72px;
}

.imp-intro-center {
    max-width:820px;
    margin:0 auto;
    text-align:center;
}

.imp-intro-center h2 {
    font-size:clamp(1.8rem, 3.5vw, 2.8rem);
    font-weight:900;
    line-height:1.15;
    margin-bottom:32px;
}

.imp-intro-paras {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
    gap:14px;
}

.imp-intro-para {
    font-size:.96rem;
    line-height:1.78;
    color:var(--mid);
    background:#fff;
    border-left:3px solid var(--orange);
    padding:16px 20px;
    border-radius:0 8px 8px 0;
}

.imp-stats {
    padding:80px 0;
    background:#eeeeee;
}

.imp-block-head {
    margin-bottom:32px;
}

.imp-block-head .imp-section-kicker {
    display:flex;
    margin-bottom:10px;
}

.imp-block-head h2 {
    font-size:clamp(1.5rem, 3vw, 2.2rem);
    font-weight:900;
    margin-bottom:8px;
}

.imp-block-head p {
    font-size:.95rem;
    color:var(--muted);
    max-width:580px;
    line-height:1.65;
}

.imp-grid {
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:18px;
}

.imp-card {
    background:#fff;
    border:1px solid var(--border);
    border-radius:14px;
    padding:24px 22px 22px;
    text-align:left;
    box-shadow:0 4px 18px rgba(0,0,0,.05);
    position:relative;
    overflow:hidden;
    opacity:0;
    transform:translateY(22px);
    transition:opacity .55s var(--ease), transform .55s var(--ease), box-shadow .25s var(--ease);
}

.imp-card.in-view {
    opacity:1;
    transform:translateY(0);
}

.imp-card:hover {
    transform:translateY(-5px);
    box-shadow:0 18px 40px rgba(0,0,0,.1);
}

.imp-card::before {
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:4px;
    background:var(--orange);
    transform:scaleX(0);
    transform-origin:left;
    transition:transform .45s var(--ease);
}

.imp-card.in-view::before,
.imp-card:hover::before {
    transform:scaleX(1);
}

.imp-card-top {
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:12px;
}

.imp-card-icon {
    flex:0 0 48px;
    width:48px;
    height:48px;
    border-radius:12px;
    background:rgba(248,101,5,.09);
    display:flex;
    align-items:center;
    justify-content:center;
    transition:background .3s ease;
}

.imp-card-icon i {
    font-size:20px;
    color:var(--orange);
    transition:color .3s ease;
}

.imp-card:hover .imp-card-icon {
    background:var(--orange);
}

.imp-card:hover .imp-card-icon i {
    color:#fff;
}

.imp-card-value {
    font-size:2rem;
    font-weight:800;
    line-height:1.1;
    letter-spacing:-.01em;
    color:var(--dark);
    font-variant-numeric:tabular-nums;
    min-width:0;
    overflow-wrap:anywhere;
}

.imp-card-label {
    font-size:.92rem;
    line-height:1.55;
    color:var(--mid);
    margin:0;
}

/* Impact stories */
.imp-stories {
    padding:80px 0;
    background:#fff;
}

.imp-stories-grid {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:24px;
}

.imp-story {
    display:flex;
    flex-direction:column;
    background:#fff;
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,.05);
    transition:transform .25s var(--ease), box-shadow .25s var(--ease);
}

.imp-story:hover {
    transform:translateY(-4px);
    box-shadow:0 18px 40px rgba(0,0,0,.1);
}

.imp-story-thumb {
    display:block;
    aspect-ratio:16 / 10;
    background:var(--cream);
    overflow:hidden;
}

.imp-story-thumb img {
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
    transition:transform .5s var(--ease);
}

.imp-story:hover .imp-story-thumb img {
    transform:scale(1.04);
}

.imp-story-placeholder {
    width:100%;
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:rgba(248,101,5,.45);
    font-size:32px;
}

.imp-story-body {
    display:flex;
    flex-direction:column;
    flex:1;
    padding:22px 22px 24px;
}

.imp-story-date {
    font-size:.78rem;
    color:var(--muted);
    margin-bottom:8px;
}

.imp-story-title {
    font-size:1.12rem;
    font-weight:800;
    line-height:1.35;
    margin:0 0 10px;
}

.imp-story-title a {
    color:var(--dark);
    text-decoration:none;
}

.imp-story-title a:hover {
    color:var(--orange);
}

.imp-story-excerpt {
    font-size:.92rem;
    line-height:1.65;
    color:var(--mid);
    margin:0 0 18px;
}

.imp-story-link {
    margin-top:auto;
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:.85rem;
    font-weight:700;
    color:var(--orange);
    text-decoration:none;
}

.imp-story-link:hover {
    text-decoration:underline;
}

.imp-stories-more {
    text-align:center;
    margin-top:40px;
}

.imp-stories-btn {
    display:inline-flex;
    align-items:center;
    gap:9px;
    background:var(--orange);
    color:#fff;
    font-size:.9rem;
    font-weight:700;
    letter-spacing:.06em;
    text-transform:uppercase;
    text-decoration:none;
    padding:14px 34px;
    border-radius:4px;
    border:1.5px solid var(--orange);
    transition:.22s ease;
}

.imp-stories-btn:hover {
    background:transparent;
    color:var(--orange);
    transform:translateY(-2px);
}

.imp-divider {
    display:flex;
    align-items:center;
    gap:16px;
    margin:64px 0 48px;
}

.imp-divider::before,
.imp-divider::after {
    content:'';
    flex:1;
    height:1px;
    background:var(--border);
}

.imp-divider-label {
    font-size:10px;
    font-weight:700;
    letter-spacing:.2em;
    text-transform:uppercase;
    color:var(--muted);
    white-space:nowrap;
}

.imp-empty {
    text-align:center;
    padding:48px 20px;
    color:var(--muted);
    border:1px dashed #e5e7eb;
    border-radius:12px;
    grid-column:1 / -1;
}

.imp-empty i {
    font-size:34px;
    color:#d1d5db;
    display:block;
    margin-bottom:12px;
}

.imp-cta {
    background:var(--charcoal);
    padding:80px 0;
    text-align:center;
    position:relative;
    overflow:hidden;
}

.imp-cta::before {
    content:'';
    position:absolute;
    inset:0;
    background:radial-gradient(ellipse 65% 55% at 50% 130%, rgba(248,101,5,.18), transparent);
}

.imp-cta-inner {
    position:relative;
    z-index:1;
}

.imp-cta h3 {
    font-size:clamp(1.6rem, 3.5vw, 2.6rem);
    font-weight:900;
    color:#fff;
    line-height:1.15;
    margin-bottom:14px;
}

.imp-cta h3 em {
    font-style:normal;
    color:var(--orange);
}

.imp-cta p {
    font-size:1rem;
    color:rgba(255,255,255,.65);
    max-width:600px;
    margin:0 auto 34px;
    line-height:1.72;
}

.imp-cta-btn {
    display:inline-flex;
    align-items:center;
    gap:9px;
    border:1.5px solid var(--orange);
    color:var(--orange);
    font-size:.9rem;
    font-weight:700;
    letter-spacing:.06em;
    text-transform:uppercase;
    text-decoration:none;
    padding:14px 36px;
    border-radius:4px;
    transition:.22s ease;
}

.imp-cta-btn:hover {
    background:var(--orange);
    color:#fff;
    transform:translateY(-2px);
}

@keyframes impFadeUp {
    from {
        opacity:0;
        transform:translateY(18px);
    }
    to {
        opacity:1;
        transform:translateY(0);
    }
}

@media (max-width:992px) {
    .imp-grid,
    .imp-stories-grid {
        grid-template-columns:repeat(2, 1fr);
    }
}

@media (max-width:768px) {
    .imp-hero-inner {
        padding:72px 0 64px;
    }
}

@media (max-width:560px) {
    .imp-grid,
    .imp-stories-grid {
        grid-template-columns:1fr;
    }

    .imp-card-value {
        font-size:1.75rem;
    }

    .imp-intro,
    .imp-stories,
    .imp-stats,
    .imp-cta {
        padding:60px 0;
    }
}
</style>

<section class="imp-hero <?php echo $hero ? 'has-bg' : ''; ?>">
    <?php if ($hero): ?>
        <div class="imp-hero-bg"
             style="background-image:url('<?php echo h($hero['src']); ?>');"
             aria-hidden="true"></div>
    <?php endif; ?>

    <div class="imp-hero-slash" aria-hidden="true"></div>
    <div class="imp-hero-grain" aria-hidden="true"></div>

    <div class="imp-wrap">
        <div class="imp-hero-inner">
            <p class="imp-eyebrow">Impact</p>

            <h1>
                <?php
                echo ($hero && !empty($hero['title']))
                    ? nl2br(h($hero['title']))
                    : nl2br(h($page['hero_title']));
                ?>
            </h1>

            <?php
            $sub = ($hero && !empty($hero['subtitle']))
                ? $hero['subtitle']
                : ($page['hero_subtitle'] ?? '');

            if (!empty(trim($sub))):
            ?>
                <p class="imp-hero-sub"><?php echo nl2br(h($sub)); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="imp-intro">
    <div class="imp-wrap">
        <div class="imp-intro-center">
            <span class="imp-section-kicker">Numbers That Matter</span>
            <h2><?php echo h($page['intro_title'] ?? 'Overall Impact'); ?></h2>
        </div>

        <?php if (!empty(trim($page['intro_text'] ?? ''))): ?>
            <div class="imp-intro-paras">
                <?php
                foreach (preg_split('/\n{2,}/', trim($page['intro_text'])) as $para):
                    $para = trim($para);
                    if (!$para) continue;
                ?>
                    <div class="imp-intro-para"><?php echo nl2br(h($para)); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($stories)): ?>
<section class="imp-stories">
    <div class="imp-wrap">

        <div class="imp-block-head">
            <span class="imp-section-kicker">Impact Stories</span>
            <h2>Stories of Change</h2>
            <p>Behind every number is a woman whose life our programmes have changed.</p>
        </div>

        <div class="imp-stories-grid">
            <?php foreach ($stories as $story):
                $storyUrl = 'blog-post.php?slug=' . urlencode($story['slug']);
                $excerpt  = !empty($story['excerpt'])
                    ? $story['excerpt']
                    : strip_tags($story['content'] ?? '');
            ?>
                <article class="imp-story">
                    <a class="imp-story-thumb" href="<?php echo h($storyUrl); ?>" tabindex="-1" aria-hidden="true">
                        <?php if (!empty($story['featured_image'])): ?>
                            <?php echo responsiveImg(
                                'uploads/blog/' . $story['featured_image'],
                                (string)$story['title'],
                                [],
                                IMG_PHOTO_WIDTHS,
                                '(max-width: 560px) 92vw, (max-width: 992px) 46vw, 380px'
                            ); ?>
                        <?php else: ?>
                            <span class="imp-story-placeholder"><i class="fas fa-book-open"></i></span>
                        <?php endif; ?>
                    </a>

                    <div class="imp-story-body">
                        <div class="imp-story-date">
                            <?php echo h(formatDate($story['published_at'] ?: $story['created_at'])); ?>
                        </div>

                        <h3 class="imp-story-title">
                            <a href="<?php echo h($storyUrl); ?>"><?php echo h($story['title']); ?></a>
                        </h3>

                        <p class="imp-story-excerpt"><?php echo h(truncate(trim($excerpt), 130)); ?></p>

                        <a class="imp-story-link" href="<?php echo h($storyUrl); ?>"
                           aria-label="Read story: <?php echo h($story['title']); ?>">
                            Read Story <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($hasMoreStories): ?>
            <div class="imp-stories-more">
                <a class="imp-stories-btn" href="<?php echo h($storiesLink); ?>">
                    See More Stories <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>

<section class="imp-stats">
    <div class="imp-wrap">

        <div class="imp-block-head">
            <span class="imp-section-kicker">Live Data</span>
            <h2>Current Stats</h2>
            <p>A snapshot of where we stand today - updated as our programmes grow.</p>
        </div>

        <div class="imp-grid">
            <?php if (empty($current)): ?>
                <div class="imp-empty">
                    <i class="fas fa-chart-bar"></i>
                    <strong>No stats added yet.</strong><br>
                    <small>Please add current stats in Admin &gt; Our Impact.</small>
                </div>
            <?php else: ?>
                <?php foreach ($current as $idx => $s):
                    $bg    = !empty(trim($s['bg_color'] ?? '')) ? h($s['bg_color']) : '#ffffff';
                    $valC  = !empty(trim($s['title_color'] ?? '')) ? h($s['title_color']) : 'var(--dark)';
                    $labC  = !empty(trim($s['label_color'] ?? '')) ? h($s['label_color']) : 'var(--mid)';
                    $value = trim((string)($s['stat_value'] ?? '0'));
                    $label = trim((string)($s['stat_label'] ?? ''));
                    $icon  = !empty(trim($s['icon_class'] ?? '')) ? trim($s['icon_class']) : 'fas fa-chart-line';
                    $delay = ($idx % 4) * 80;
                ?>
                    <div class="imp-card" style="background:<?php echo $bg; ?>;transition-delay:<?php echo $delay; ?>ms">
                        <div class="imp-card-top">
                            <div class="imp-card-icon" aria-hidden="true">
                                <i class="<?php echo h($icon); ?>"></i>
                            </div>

                            <div class="imp-card-value js-count"
                                 data-value="<?php echo h($value); ?>"
                                 style="color:<?php echo $valC; ?>;">
                                <?php echo h($value); ?>
                            </div>
                        </div>

                        <p class="imp-card-label" style="color:<?php echo $labC; ?>;">
                            <?php echo h($label); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="imp-divider">
            <span class="imp-divider-label">Alumni Success</span>
        </div>

        <div class="imp-block-head">
            <h2>Life After WITU is Bright</h2>
            <p>See the success of our alumni long after they complete our programmes.</p>
        </div>

        <div class="imp-grid">
            <?php if (empty($alumni)): ?>
                <div class="imp-empty">
                    <i class="fas fa-user-graduate"></i>
                    <strong>No alumni stats added yet.</strong><br>
                    <small>Please add alumni stats in Admin &gt; Our Impact.</small>
                </div>
            <?php else: ?>
                <?php foreach ($alumni as $idx => $s):
                    $bg    = !empty(trim($s['bg_color'] ?? '')) ? h($s['bg_color']) : '#ffffff';
                    $valC  = !empty(trim($s['title_color'] ?? '')) ? h($s['title_color']) : 'var(--dark)';
                    $labC  = !empty(trim($s['label_color'] ?? '')) ? h($s['label_color']) : 'var(--mid)';
                    $value = trim((string)($s['stat_value'] ?? '0'));
                    $label = trim((string)($s['stat_label'] ?? ''));
                    $icon  = !empty(trim($s['icon_class'] ?? '')) ? trim($s['icon_class']) : 'fas fa-chart-line';
                    $delay = ($idx % 4) * 80;
                ?>
                    <div class="imp-card" style="background:<?php echo $bg; ?>;transition-delay:<?php echo $delay; ?>ms">
                        <div class="imp-card-top">
                            <div class="imp-card-icon" aria-hidden="true">
                                <i class="<?php echo h($icon); ?>"></i>
                            </div>

                            <div class="imp-card-value js-count"
                                 data-value="<?php echo h($value); ?>"
                                 style="color:<?php echo $valC; ?>;">
                                <?php echo h($value); ?>
                            </div>
                        </div>

                        <p class="imp-card-label" style="color:<?php echo $labC; ?>;">
                            <?php echo h($label); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<section class="imp-cta">
    <div class="imp-wrap">
        <div class="imp-cta-inner">
            <h3>
                <?php
                $words = explode(' ', $page['annual_cta_title']);
                if (count($words) > 1) {
                    $last = array_pop($words);
                    echo h(implode(' ', $words)) . ' <em>' . h($last) . '</em>';
                } else {
                    echo h($page['annual_cta_title']);
                }
                ?>
            </h3>

            <?php if (!empty(trim($page['annual_cta_text'] ?? ''))): ?>
                <p><?php echo nl2br(h($page['annual_cta_text'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($page['annual_cta_link'])): ?>
                <a class="imp-cta-btn" href="<?php echo h($page['annual_cta_link']); ?>">
                    <?php echo h($page['annual_cta_button'] ?? 'Read the Annual Reports'); ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    function parseStat(raw) {
        raw = String(raw || '').trim();

        var match = raw.match(/-?[\d,]+(\.\d+)?/);
        if (!match) return null;

        var numberText = match[0];
        var cleanNumber = parseFloat(numberText.replace(/,/g, ''));

        if (isNaN(cleanNumber)) return null;

        return {
            raw: raw,
            number: cleanNumber,
            prefix: raw.slice(0, match.index),
            suffix: raw.slice(match.index + numberText.length),
            decimals: numberText.indexOf('.') >= 0 ? numberText.split('.')[1].length : 0
        };
    }

    function formatNumber(value, decimals) {
        return Number(value).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function animateCounter(el) {
        if (!el || el.dataset.counted === '1') return;

        var parsed = parseStat(el.dataset.value || el.textContent);
        if (!parsed) return;

        el.dataset.counted = '1';

        var duration = 1500;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;

            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = parsed.number * eased;

            el.textContent = parsed.prefix + formatNumber(current, parsed.decimals) + parsed.suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = parsed.raw;
            }
        }

        requestAnimationFrame(step);
    }

    var cards = document.querySelectorAll('.imp-card');

    if (!cards.length) return;

    if (!('IntersectionObserver' in window)) {
        cards.forEach(function (card) {
            card.classList.add('in-view');
            animateCounter(card.querySelector('.js-count'));
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                animateCounter(entry.target.querySelector('.js-count'));
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    cards.forEach(function (card) {
        observer.observe(card);
    });
})();
</script>

<?php include 'footer.php'; ?>