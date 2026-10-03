<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/helpers.php';

/* -------------------------------------------------
   Helpers
------------------------------------------------- */
function pg_decode($value): string
{
    $text = (string)($value ?? '');

    // Remove escaped slashes from old addslashes()/magic quotes data
    $text = stripslashes($text);

    // Decode HTML entities
    return html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}

function pg_e($value): string
{
    return htmlspecialchars(
        pg_decode($value),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}

function pg_trim_text($text, int $limit = 160): string
{
    $text = trim(strip_tags(pg_decode($text)));

    if (function_exists('mb_strlen')) {
        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit - 3) . '...'
            : $text;
    }

    return strlen($text) > $limit
        ? substr($text, 0, $limit - 3) . '...'
        : $text;
}

function pg_programme_link(array $programme): string
{
    $slug = trim((string)($programme['slug'] ?? ''));

    return $slug !== ''
        ? 'programme.php?slug=' . urlencode($slug)
        : '#';
}

function pg_programme_image_url(array $programme): string
{
    $image = trim((string)($programme['featured_image'] ?? ''));

    return $image !== ''
        ? 'uploads/programmes/' . rawurlencode($image)
        : '';
}

/* -------------------------------------------------
   Programmes list
------------------------------------------------- */
$programmes = [];

$result = @$conn->query("
    SELECT *
    FROM programmes
    WHERE status = 'published'
    ORDER BY 
        CASE WHEN parent_id IS NULL OR parent_id = 0 THEN display_order ELSE 999999 END ASC,
        parent_id ASC,
        display_order ASC,
        id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
        $row['id']        = isset($row['id']) ? (int)$row['id'] : 0;
        $programmes[]     = $row;
    }
}

/* -------------------------------------------------
   Group parent and child programmes
------------------------------------------------- */
$parents = [];
$children_by_parent = [];
$orphans = [];

foreach ($programmes as $programme) {
    if (empty($programme['parent_id'])) {
        $parents[$programme['id']] = $programme;
    } else {
        $children_by_parent[$programme['parent_id']][] = $programme;
    }
}


foreach ($children_by_parent as $parent_id => $children) {
    if (!isset($parents[$parent_id])) {
        foreach ($children as $child) {
            $orphans[] = $child;
        }

        unset($children_by_parent[$parent_id]);
    }
}

$display_programmes = array_merge(array_values($parents), $orphans);

$page_title       = 'Programmes';
$meta_description = 'Explore WITU\'s flagship programmes - ElevateHer360, Elevate Accelerator, and more - building digital skills, agency, and economic opportunity for women and girls.';
$body_class       = 'programmes-page';

include 'header.php';
?>

<style>


</style>

<!-- -----------------------------------------------
     HERO
----------------------------------------------- -->
<section class="pg-hero">
  <div class="pg-hero-bg" style="background-image:url('assets/images/programmes-hero.jpg');"></div>
  <div class="pg-hero-slash"></div>
  <div class="pg-hero-grain"></div>

  <div class="container pg-hero-inner">
    <span class="pg-eyebrow">What We Do</span>
    <h1>Our Programmes</h1>
    <p class="pg-hero-sub">
      WITU delivers its work through flagship programmes designed to equip women and girls with digital skills, agency, networks, and pathways to dignified work and economic opportunity.
    </p>
  </div>
</section>

<!-- -----------------------------------------------
     INTRO STRIP
----------------------------------------------- -->
<section class="pg-intro-strip">
  <div class="container pg-intro-grid">
    <div class="pg-intro-stat">
      <strong>20,000+</strong>
      <span>Women and girls reached</span>
    </div>

    <div class="pg-intro-stat">
      <strong>56</strong>
      <span>Districts across Uganda</span>
    </div>

    <div class="pg-intro-stat">
      <strong>10,000+</strong>
      <span>Connected to work &amp; income opportunities</span>
    </div>

    <div class="pg-intro-stat">
      <strong>1,000+</strong>
      <span>Entrepreneurs supported</span>
    </div>
  </div>
</section>

<!-- -----------------------------------------------
     PROGRAMMES GRID
----------------------------------------------- -->
<section class="pg-section">
  <div class="container">

    <div class="pg-section-head">
      <h2>Building Skills, Agency &amp; Opportunity</h2>
      <p>
        Each programme is designed to meet women and girls where they are - combining technical training, personal development, mentorship, and direct connections to employment and entrepreneurship opportunities.
      </p>
    </div>

    <?php if (empty($display_programmes)): ?>

      <div class="pg-empty">
        <i class="fas fa-folder-open"></i>
        <h3>No programmes yet</h3>
        <p>Check back soon - programmes will appear here once published.</p>
      </div>

    <?php else: ?>

      <div class="pg-grid">
        <?php
        $accents = ['#f86505', '#2e6f8a', '#66c2c4', '#b6d1a6'];

        foreach ($display_programmes as $idx => $prog):
            $children = $children_by_parent[$prog['id']] ?? [];

            $title   = pg_e($prog['title'] ?? '');
            $summary = pg_e(pg_trim_text($prog['summary'] ?? $prog['description'] ?? '', 180));
            $icon    = pg_e($prog['icon'] ?? 'fas fa-star');
            $link    = pg_e(pg_programme_link($prog));

            $img_url = pg_programme_image_url($prog);
            $has_img = $img_url !== '';

            $accent  = $accents[$idx % count($accents)];
        ?>

        <article class="pg-card <?php echo !empty($children) ? 'pg-card-has-children' : ''; ?>" style="--card-accent:<?php echo pg_e($accent); ?>;">

          <!-- image / placeholder -->
          <a href="<?php echo $link; ?>" class="pg-card-thumb" aria-hidden="true" tabindex="-1">
            <?php if ($has_img): ?>
              <img src="<?php echo pg_e($img_url); ?>"
                   alt="<?php echo $title; ?>"
                   loading="<?php echo $idx < 4 ? 'eager' : 'lazy'; ?>">
            <?php else: ?>
              <div class="pg-card-placeholder">
                <i class="<?php echo $icon; ?>"></i>
              </div>
            <?php endif; ?>

            <div class="pg-card-thumb-overlay"></div>
          </a>

          <!-- body -->
          <div class="pg-card-body">
            <div class="pg-card-num"><?php printf('%02d', $idx + 1); ?></div>

            <h3 class="pg-card-title">
              <a href="<?php echo $link; ?>"><?php echo $title; ?></a>
            </h3>

            <p class="pg-card-desc"><?php echo $summary; ?></p>

            <a href="<?php echo $link; ?>" class="pg-card-cta">
              Learn More
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.5"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </a>

            <?php if (!empty($children)): ?>
              <div class="pg-card-children">
                <h4 class="pg-card-children-title">
                  <i class="fas fa-sitemap"></i>
                  Related Programmes
                </h4>

                <div class="pg-child-list">
                  <?php foreach ($children as $child):
                      $child_title   = pg_e($child['title'] ?? '');
                      $child_summary = pg_e(pg_trim_text($child['summary'] ?? $child['description'] ?? '', 95));
                      $child_icon    = pg_e($child['icon'] ?? 'fas fa-angle-right');
                      $child_link    = pg_e(pg_programme_link($child));
                  ?>

                    <a href="<?php echo $child_link; ?>" class="pg-child-item">
                      <span class="pg-child-icon">
                        <i class="<?php echo $child_icon; ?>"></i>
                      </span>

                      <span class="pg-child-content">
                        <strong><?php echo $child_title; ?></strong>

                        <?php if ($child_summary !== ''): ?>
                          <span><?php echo $child_summary; ?></span>
                        <?php endif; ?>
                      </span>

                      <span class="pg-child-arrow">
                        <i class="fas fa-arrow-right"></i>
                      </span>
                    </a>

                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </article>

        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>
</section>

<!-- -----------------------------------------------
     BOTTOM CTA
----------------------------------------------- -->
<section class="pg-cta">
  <div class="container pg-cta-inner">
    <div class="pg-cta-text">
      <h2>Ready to get involved?</h2>
      <p>Apply to one of our programmes, partner with us, hire our graduates, or support our work through a donation.</p>
    </div>

    <div class="pg-cta-btns">
      <a href="get-involved.php" class="witu-btn witu-btn-white">Get Involved</a>
      <a href="donate.php" class="witu-btn witu-btn-outline-white">Donate</a>
      <a href="partner.php" class="witu-btn witu-btn-outline-white">Partner With Us</a>
    </div>
  </div>
</section>

<!-- Scroll-reveal observer -->
<script>
(function(){
  if (!window.IntersectionObserver) return;

  const cards = document.querySelectorAll('.pg-card');

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  cards.forEach(card => io.observe(card));
})();
</script>

<?php include 'footer.php'; ?>