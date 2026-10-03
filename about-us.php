<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('decodeHtmlEntities')) {
    function decodeHtmlEntities($text): string {
        $text = (string)$text;
        $previous = '';

        while ($previous !== $text) {
            $previous = $text;
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $text;
    }
}

if (!function_exists('limitWords')) {
    function limitWords($text, int $limit = 10): string {
        $text = trim(strip_tags(decodeHtmlEntities((string)$text)));

        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/', $text);

        if (!$words || count($words) <= $limit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
}

if (!function_exists('assetPath')) {
    function assetPath($path, string $defaultDir = ''): string {
        $path = trim((string)$path);

        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (
            str_starts_with($path, 'uploads/') ||
            str_starts_with($path, 'assets/') ||
            str_starts_with($path, 'dashboard/uploads/')
        ) {
            return $path;
        }

        return $defaultDir !== ''
            ? trim($defaultDir, '/') . '/' . $path
            : $path;
    }
}

if (!function_exists('buildVideoEmbedUrl')) {
    function buildVideoEmbedUrl(string $url): string {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([^&\n?#]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $url;
    }
}

if (!function_exists('sectionHasVisualBackground')) {
    function sectionHasVisualBackground(array $about, string $section): bool {
        $type  = strtolower(trim((string)($about[$section . '_bg_type'] ?? 'color')));
        $image = assetPath($about[$section . '_bg_image'] ?? '', 'uploads/about/backgrounds');
        $video = assetPath($about[$section . '_bg_video'] ?? '', 'uploads/about/backgrounds');

        return ($image !== '' && in_array($type, ['image', 'color'], true)) || ($video !== '' && $type === 'video');
    }
}

if (!function_exists('sectionStyle')) {
    function sectionStyle(array $about, string $section, string $fallbackColor): string {
        $type  = strtolower(trim((string)($about[$section . '_bg_type'] ?? 'color')));
        $color = trim((string)($about[$section . '_bg_color'] ?? $fallbackColor));
        $image = assetPath($about[$section . '_bg_image'] ?? '', 'uploads/about/backgrounds');

        if ($color === '') {
            $color = $fallbackColor;
        }

        if ($image !== '' && in_array($type, ['image', 'color'], true)) {
            return "
                background-image:
                    linear-gradient(
                        135deg,
                        rgba(11,44,58,.68) 0%,
                        rgba(11,44,58,.45) 42%,
                        rgba(253,101,1,.58) 100%
                    ),
                    url('" . h($image) . "');
                background-position:center;
                background-size:cover;
                background-repeat:no-repeat;
            ";
        }

        return "background-color:" . h($color) . ";";
    }
}

if (!function_exists('sectionVideo')) {
    function sectionVideo(array $about, string $section): string {
        $type = strtolower(trim((string)($about[$section . '_bg_type'] ?? 'color')));

        if ($type !== 'video') {
            return '';
        }

        return assetPath($about[$section . '_bg_video'] ?? '', 'uploads/about/backgrounds');
    }
}

if (!function_exists('renderItemMedia')) {
    function renderItemMedia(array $item, string $title = ''): void {
        $mediaType = strtolower(trim((string)($item['media_type'] ?? 'image')));
        $iconClass = trim((string)($item['icon_class'] ?? 'fas fa-star'));
        $image     = assetPath($item['image'] ?? '', 'uploads/about');

        if ($mediaType === 'icon') {
            echo '<div class="item-icon"><i class="' . h($iconClass ?: 'fas fa-star') . '"></i></div>';
            return;
        }

        if ($image !== '') {
            echo '<img src="' . h($image) . '" alt="' . h($title) . '" class="item-image">';
        }
    }
}

function fetchAboutItems(mysqli $conn, string $table): array {
    $items = [];

    $res = $conn->query("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }

    return $items;
}

$site_name = SITE_NAME ?? 'WITU';

$about = [
    'hero_image' => '',
    'mission' => '',
    'vision' => '',
    'impact_statement' => '',
    'history_text' => '',
    'founder_name' => '',
    'founder_title' => '',
    'founder_video_url' => '',

    'values_bg_color' => '#ff98001a',
    'values_bg_type' => 'color',
    'values_bg_image' => '',
    'values_bg_video' => '',

    'goals_bg_color' => '#ffffff',
    'goals_bg_type' => 'color',
    'goals_bg_image' => '',
    'goals_bg_video' => '',

    'approach_bg_color' => '#f8fafc',
    'approach_bg_type' => 'color',
    'approach_bg_image' => '',
    'approach_bg_video' => '',

    'model_bg_color' => '#ffffff',
    'model_bg_type' => 'color',
    'model_bg_image' => '',
    'model_bg_video' => '',

    'why_bg_color' => '#f8fafc',
    'why_bg_type' => 'color',
    'why_bg_image' => '',
    'why_bg_video' => '',

    'cta_title' => '',
    'cta_text' => '',
    'cta_button_text' => '',
    'cta_button_link' => '',
    'cta_background_image' => '',
];

$res = $conn->query("SELECT * FROM about_page WHERE is_active = 1 ORDER BY id DESC LIMIT 1");

if ($res && $row = $res->fetch_assoc()) {
    $about = array_merge($about, $row);
}

foreach (['values', 'goals', 'approach', 'model', 'why'] as $sec) {
    if (!empty(trim((string)($about[$sec . '_bg_image'] ?? '')))) {
        $about[$sec . '_bg_type'] = 'image';
    }
}

$values   = fetchAboutItems($conn, 'about_values');
$goals    = fetchAboutItems($conn, 'about_goals');
$approach = fetchAboutItems($conn, 'about_approach');
$model    = fetchAboutItems($conn, 'about_model');
$why      = fetchAboutItems($conn, 'about_why_choose');

$page_title = 'About Us - ' . $site_name;

$heroImage = assetPath($about['hero_image'] ?? '', 'uploads/about/backgrounds');

$heroStyle = $heroImage !== ''
    ? "
        background-image:
            linear-gradient(rgba(11,44,58,.70), rgba(11,44,58,.70)),
            url('" . h($heroImage) . "');
        background-position:center;
        background-size:cover;
        background-repeat:no-repeat;
    "
    : "background:#ff98001a;";

$ctaImage = assetPath($about['cta_background_image'] ?? '', 'uploads/about/backgrounds');

$ctaStyle = $ctaImage !== ''
    ? "
        background-image:
            linear-gradient(rgba(11,44,58,.76), rgba(11,44,58,.76)),
            url('" . h($ctaImage) . "');
        background-position:center;
        background-size:cover;
        background-repeat:no-repeat;
    "
    : "background:#ff98001a;";

$embedUrl = buildVideoEmbedUrl($about['founder_video_url'] ?? '');

include 'header.php';
?>

<style>
section {
    padding: 90px 0;
}

.section-bg {
    position: relative;
    overflow: hidden;
}

.section-bg > .container {
    position: relative;
    z-index: 2;
}

.section-bg-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
}

.section-bg-overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
    background: linear-gradient(
        135deg,
        rgba(11,44,58,.72) 0%,
        rgba(11,44,58,.50) 45%,
        rgba(253,101,1,.58) 100%
    );
}

.section-header {
    text-align: center;
    margin-bottom: 40px;
}

.section-header h2 {
    font-size: 38px;
    font-weight: 900;
    margin-bottom: 10px;
    color: #0b2c3a;
}

.section-header p {
    max-width: 800px;
    margin: 0 auto;
    color: #6b7280;
    line-height: 1.7;
}

.section-header-light h2 {
    color: #ffffff;
    text-shadow: 0 3px 14px rgba(0,0,0,.45);
}

.section-header-light p {
    color: rgba(255,255,255,.94);
    text-shadow: 0 2px 10px rgba(0,0,0,.45);
}

.about-hero {
    position: relative;
    color: #fff;
    padding: 120px 0;
    <?php echo $heroStyle; ?>
}

.about-hero .container {
    position: relative;
    z-index: 2;
    text-align: center;
}

.about-hero h1 {
    font-size: 50px;
    font-weight: 900;
    margin-bottom: 15px;
}

.about-hero p {
    max-width: 850px;
    margin: 0 auto;
    font-size: 18px;
    opacity: .95;
    line-height: 1.7;
}

.hero-meta {
    margin-top: 22px;
}

.two-col,
.history-founder-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: stretch;
}

.card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.06);
    padding: 20px;
}

.card h3 {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 900;
    color: #0b2c3a;
}

.card p {
    margin: 0;
    color: #374151;
    line-height: 1.7;
}

.grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #e5e7eb;
    font-weight: 900;
    color: #0b2c3a;
    margin: 5px;
}

.pill i {
    color: #fd6501;
}

.value-card,
.goal-card,
.approach-card,
.model-card,
.why-card {
    padding: 0;
    overflow: hidden;
}

.item-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
    background: #f3f4f6;
}

.item-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(253,101,1,.10);
    color: #fd6501;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 22px auto 0;
}

.item-icon i {
    font-size: 30px;
}

.item-content {
    padding: 16px;
}

.value-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.value-title strong {
    font-size: 15px;
    color: #0b2c3a;
}

.goal-heading {
    font-size: 22px;
    font-weight: 900;
    margin: 0 0 6px;
    color: #0b2c3a;
}

.goal-sub {
    margin: 0;
    color: #4b5563;
}

.approach-card h4 {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 900;
    color: #0b2c3a;
}

.list {
    margin: 0;
    padding-left: 18px;
    color: #374151;
    line-height: 1.7;
}

.list li {
    margin: 6px 0;
}

.impact {
    background: linear-gradient(135deg, #fd6501 0%, #fd6501a8 100%);
    color: #fff;
    border-radius: 18px;
    padding: 26px;
    box-shadow: 0 12px 30px rgba(0,0,0,.10);
}

.impact h3 {
    margin: 0 0 10px;
    font-size: 22px;
    font-weight: 900;
    color: #fff;
}

.impact p {
    margin: 0;
    opacity: .95;
    line-height: 1.75;
    color: #fff;
}

.history-founder-section {
    background: #444444;
}

.history-founder-card {
    height: 100%;
}

.history-founder-card h2 {
    margin: 0 0 12px;
    font-size: 28px;
    font-weight: 900;
    color: #0b2c3a;
}

.history-founder-card .intro {
    color: #6b7280;
    margin-bottom: 18px;
}

.founder-card {
    background: linear-gradient(135deg, #fd6501, #e85b00);
    color: #fff;
}

.founder-card h2,
.founder-card p,
.founder-card .intro {
    color: #fff;
}

.video-wrap {
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.08);
}

.video-embed {
    width: 100%;
    aspect-ratio: 16/9;
    border: 0;
    display: block;
}

.founder-meta {
    margin-top: 14px;
    text-align: center;
}

.founder-meta strong {
    display: block;
    font-size: 16px;
}

.founder-meta small {
    opacity: .9;
}

.cta-section {
    position: relative;
    color: #fff;
    <?php echo $ctaStyle; ?>
}

.cta-inner {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    background: rgba(255,255,255,.08);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 24px;
    padding: 40px 28px;
}

.cta-inner h2 {
    color: #fff;
    font-size: 40px;
    font-weight: 900;
    margin: 0 0 14px;
}

.cta-inner p {
    color: rgba(255,255,255,.92);
    margin: 0 auto 20px;
    max-width: 760px;
    line-height: 1.8;
}

.cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #fd6501;
    padding: 14px 24px;
    border-radius: 999px;
    font-weight: 900;
    text-decoration: none;
    transition: .25s ease;
}

.cta-btn:hover {
    transform: translateY(-2px);
    color: #e85b00;
    text-decoration: none;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: #6b7280;
}

@media (max-width: 1300px) {
    .grid-5 {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1200px) {
    .grid-4,
    .grid-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .two-col,
    .history-founder-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    section {
        padding: 60px 0;
    }

    .about-hero {
        padding: 90px 0;
    }

    .about-hero h1 {
        font-size: 38px;
    }

    .grid-4,
    .grid-5 {
        grid-template-columns: 1fr;
    }

    .section-header h2,
    .cta-inner h2 {
        font-size: 30px;
    }

    .history-founder-card h2 {
        font-size: 24px;
    }
}
</style>

<section class="about-hero">
    <div class="container">
        <h1>About Us</h1>
        <p>
            Women in Technology Uganda (WITU) is building pathways to dignified work and financial security
            through market-driven digital skills, entrepreneurship, and supportive ecosystems.
        </p>

        <div class="hero-meta">
            <span class="pill"><i class="fas fa-bullseye"></i> Mission - Vision - Values</span>
            <span class="pill"><i class="fas fa-chart-line"></i> Strategy - Impact - Model</span>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="two-col">
            <div class="card">
                <h3><i class="fas fa-bullseye" style="color:#fd6501;margin-right:8px;"></i> Mission</h3>
                <p><?php echo nl2br(h(decodeHtmlEntities($about['mission'] ?? ''))); ?></p>
            </div>

            <div class="card">
                <h3><i class="fas fa-eye" style="color:#fd6501;margin-right:8px;"></i> Vision</h3>
                <p><?php echo nl2br(h(decodeHtmlEntities($about['vision'] ?? ''))); ?></p>
            </div>
        </div>
    </div>
</section>

<?php
$sections = [
    'values' => [
        'title' => 'Core Values',
        'desc' => '',
        'items' => $values,
        'empty' => 'No values found. Add them in Admin.',
        'fallback' => '#ff98001a',
        'card' => 'value-card',
        'grid' => 'grid-5',
    ],
    'goals' => [
        'title' => 'Our Goals',
        'desc' => 'Clear outcomes that drive our programs and partnerships.',
        'items' => $goals,
        'empty' => 'No goals found. Add them in Admin.',
        'fallback' => '#ffffff',
        'card' => 'goal-card',
        'grid' => 'grid-4',
    ],
    'approach' => [
        'title' => 'Our Strategic Approach',
        'desc' => 'We move from needs to opportunity through an intentional pathway.',
        'items' => $approach,
        'empty' => 'No approach items found. Add them in Admin.',
        'fallback' => '#f8fafc',
        'card' => 'approach-card',
        'grid' => 'grid-4',
    ],
    'model' => [
        'title' => 'Our Model',
        'desc' => 'Principles that keep our work market-driven, relevant, and sustainable.',
        'items' => $model,
        'empty' => 'No model items found. Add them in Admin.',
        'fallback' => '#ffffff',
        'card' => 'model-card',
        'grid' => 'grid-4',
    ],
    'why' => [
        'title' => 'Why Choose Us',
        'desc' => 'Training, experience, and support designed for real digital careers.',
        'items' => $why,
        'empty' => 'No items found. Add them in Admin.',
        'fallback' => '#f8fafc',
        'card' => 'why-card',
        'grid' => 'grid-4',
    ],
];

foreach ($sections as $key => $section):
    $hasVisualBg = sectionHasVisualBackground($about, $key);
?>
<section class="section-bg" style="<?php echo sectionStyle($about, $key, $section['fallback']); ?>">
    <?php if ($video = sectionVideo($about, $key)): ?>
        <video class="section-bg-video" autoplay muted loop playsinline>
            <source src="<?php echo h($video); ?>">
        </video>
        <div class="section-bg-overlay"></div>
    <?php endif; ?>

    <div class="container">
        <div class="section-header <?php echo $hasVisualBg ? 'section-header-light' : ''; ?>">
            <h2><?php echo h($section['title']); ?></h2>

            <?php if (!empty($section['desc'])): ?>
                <p><?php echo h($section['desc']); ?></p>
            <?php endif; ?>
        </div>

        <div class="<?php echo h($section['grid']); ?>">
            <?php if (empty($section['items'])): ?>
                <div class="card empty-state"><?php echo h($section['empty']); ?></div>
            <?php else: ?>
                <?php foreach ($section['items'] as $item): ?>
                    <div class="card <?php echo h($section['card']); ?>">
                        <?php
                        $heading = $item['title'] ?? $item['heading'] ?? '';
                        renderItemMedia($item, $heading);
                        ?>

                        <div class="item-content">
                            <?php if ($key === 'values'): ?>
                                <div class="value-title">
                                    <strong><?php echo h(limitWords($item['title'] ?? '', 10)); ?></strong>
                                </div>
                                <p><?php echo h(limitWords($item['description'] ?? '', 10)); ?></p>

                            <?php elseif ($key === 'goals'): ?>
                                <div class="goal-heading"><?php echo h(limitWords($item['heading'] ?? '', 10)); ?></div>
                                <p class="goal-sub"><?php echo h(limitWords($item['subtext'] ?? '', 10)); ?></p>

                            <?php elseif ($key === 'approach'): ?>
                                <h4><?php echo h(limitWords($item['heading'] ?? '', 10)); ?></h4>
                                <ul class="list">
                                    <?php if (!empty($item['subtext1'])): ?>
                                        <li><?php echo h(limitWords($item['subtext1'], 10)); ?></li>
                                    <?php endif; ?>

                                    <?php if (!empty($item['subtext2'])): ?>
                                        <li><?php echo h(limitWords($item['subtext2'], 10)); ?></li>
                                    <?php endif; ?>
                                </ul>

                            <?php else: ?>
                                <h3><?php echo h(limitWords($item['heading'] ?? '', 10)); ?></h3>
                                <p><?php echo h(limitWords($item['description'] ?? '', 10)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($key === 'approach' && !empty(trim((string)($about['impact_statement'] ?? '')))): ?>
            <div style="margin-top:22px;" class="impact">
                <h3>Strategic Impact Statement</h3>
                <p><?php echo nl2br(h(decodeHtmlEntities($about['impact_statement'] ?? ''))); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>

<?php if (
    !empty(trim((string)($about['cta_title'] ?? ''))) ||
    !empty(trim((string)($about['cta_text'] ?? ''))) ||
    !empty(trim((string)($about['cta_button_text'] ?? '')))
): ?>
<section class="cta-section">
    <div class="container">
        <div class="cta-inner">
            <?php if (!empty($about['cta_title'])): ?>
                <h2><?php echo h(decodeHtmlEntities($about['cta_title'])); ?></h2>
            <?php endif; ?>

            <?php if (!empty($about['cta_text'])): ?>
                <p><?php echo nl2br(h(decodeHtmlEntities($about['cta_text']))); ?></p>
            <?php endif; ?>

            <?php if (!empty($about['cta_button_text']) && !empty($about['cta_button_link'])): ?>
                <a href="<?php echo h($about['cta_button_link']); ?>" class="cta-btn">
                    <?php echo h(decodeHtmlEntities($about['cta_button_text'])); ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'footer.php'; ?>