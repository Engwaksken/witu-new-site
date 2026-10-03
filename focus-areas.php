<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

/* -- Top-level published focus areas -- */
$areas = [];
$res = @$conn->query("
    SELECT *
    FROM focus_areas
    WHERE status = 'published'
      AND (parent_id IS NULL OR parent_id = 0)
    ORDER BY display_order ASC, id ASC
");
if ($res) while ($row = $res->fetch_assoc()) $areas[] = $row;

/* -- Count child areas per parent -- */
$child_counts = [];
if (!empty($areas)) {
    $ids = implode(',', array_map('intval', array_column($areas, 'id')));
    $cr  = @$conn->query("
        SELECT parent_id, COUNT(*) AS cnt
        FROM focus_areas
        WHERE parent_id IN ($ids) AND status='published'
        GROUP BY parent_id
    ");
    if ($cr) while ($row = $cr->fetch_assoc())
        $child_counts[(int)$row['parent_id']] = (int)$row['cnt'];
}

$page_title       = 'Focus Areas';
$meta_description = 'Explore WITU\'s focus areas - Digital Skills, Workforce Development, Entrepreneurship, and more - building opportunity for women and girls across Uganda.';
$body_class       = 'focus-areas-page';

include 'header.php';
?>

<section class="fal-hero">
  <div class="fal-hero-bg"></div>
  <div class="fal-hero-slash"></div>
  <div class="fal-hero-grain"></div>
  <div class="container fal-hero-inner">
    <span class="fal-eyebrow"> What We Focus On</span>
    <h1>Our Focus Areas</h1>
    <p class="fal-hero-sub">WITU's work is organised around key areas that address the barriers women and girls face in accessing technology, skills, work, and economic opportunity.</p>
   
  </div>
</section>

<!-- -----------------------------------------------
     INTRO
----------------------------------------------- -->
<section class="fal-intro">
  <div class="container fal-intro-grid">
    <div class="fal-intro-text">
      <span class="fal-kicker"> Our Approach</span>
      <h2>Integrated, community-centred, and evidence-led</h2>
      <p>Each focus area is built on deep community insight, real-world evidence, and partnerships that ensure our work creates lasting change. We don't work in silos - our focus areas reinforce and amplify each other.</p>
    </div>
    <div class="fal-intro-stats">
      <div class="fal-istat"><strong><?php echo count($areas) ?: '6+'; ?></strong><span>Focus Areas</span></div>
      <div class="fal-istat"><strong>20,000+</strong><span>Women &amp; girls reached</span></div>
      <div class="fal-istat"><strong>56</strong><span>Districts</span></div>
    </div>
  </div>
</section>

<!-- -----------------------------------------------
     FOCUS AREAS GRID
----------------------------------------------- -->
<section class="fal-section">
  <div class="container">

    <?php if (empty($areas)): ?>
    <div class="fal-empty">
      <i class="fas fa-folder-open"></i>
      <h3>No focus areas yet</h3>
      <p>Check back soon - focus areas will appear here once published.</p>
    </div>

    <?php else: ?>
    <div class="fal-grid" id="falGrid">
      <?php
      $accents = ['#f86505','#2e6f8a','#66c2c4','#b6d1a6','#e65c1a','#1a5c8a'];
      foreach ($areas as $idx => $area):
        $t       = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
        $accent  = $accents[$idx % count($accents)];
        $has_img = !empty($area['featured_image']);
        $slug    = htmlspecialchars($area['slug'] ?? '', ENT_QUOTES, 'UTF-8');
        $icon    = htmlspecialchars($area['icon'] ?? 'fas fa-bullseye', ENT_QUOTES, 'UTF-8');
        $link    = "focus-area.php?slug={$slug}";
        $sub_cnt = $child_counts[(int)$area['id']] ?? 0;
      ?>
      <article class="fal-card" data-idx="<?php echo $idx; ?>"
               style="--fal-accent:<?php echo $accent; ?>;">

        <!-- thumb -->
        <a href="<?php echo $link; ?>" class="fal-card-thumb" tabindex="-1" aria-hidden="true">
          <?php if ($has_img): ?>
            <img src="uploads/focus-areas/<?php echo $t($area['featured_image']); ?>"
                 alt="<?php echo $t($area['title']); ?>"
                 loading="<?php echo $idx < 3 ? 'eager' : 'lazy'; ?>">
          <?php else: ?>
            <div class="fal-card-placeholder">
              <i class="<?php echo $icon; ?>"></i>
            </div>
          <?php endif; ?>
          <div class="fal-thumb-overlay"></div>
        </a>

        <!-- body -->
        <div class="fal-card-body">
          <div class="fal-card-meta">
            <div class="fal-card-icon-wrap">
              <i class="<?php echo $icon; ?>"></i>
            </div>
            <?php if ($sub_cnt > 0): ?>
              <span class="fal-sub-badge"><?php echo $sub_cnt; ?> sub-area<?php echo $sub_cnt > 1 ? 's' : ''; ?></span>
            <?php endif; ?>
          </div>

          <h3 class="fal-card-title">
            <a href="<?php echo $link; ?>"><?php echo $t($area['title']); ?></a>
          </h3>

          <?php if (!empty($area['description'])): ?>
            <p class="fal-card-desc"><?php echo $t($area['description']); ?></p>
          <?php endif; ?>

          <a href="<?php echo $link; ?>" class="fal-card-cta">
            Explore
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </a>
        </div>

      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- -----------------------------------------------
     HOW WE WORK STRIP
----------------------------------------------- -->
<!--<section class="fal-how">
  <div class="container">
    <div class="fal-how-head">
      
      <h2>Our working principles</h2>
    </div>
    <div class="fal-how-grid">
      <div class="fal-how-card">
        <div class="fal-how-icon"><i class="fas fa-users"></i></div>
        <h4>Community-Centred</h4>
        <p>Every programme begins with listening to the communities we serve and co-designing solutions with them.</p>
      </div>
      <div class="fal-how-card">
        <div class="fal-how-icon"><i class="fas fa-chart-bar"></i></div>
        <h4>Evidence-Led</h4>
        <p>We use data, continuous feedback, and rigorous evaluation to ensure our work is effective and impactful.</p>
      </div>
      <div class="fal-how-card">
        <div class="fal-how-icon"><i class="fas fa-handshake"></i></div>
        <h4>Partnership-Driven</h4>
        <p>We work with employers, government, academia, and civil society to maximise reach and sustainability.</p>
      </div>
      <div class="fal-how-card">
        <div class="fal-how-icon"><i class="fas fa-seedling"></i></div>
        <h4>Long-Term Focus</h4>
        <p>We invest in relationships, systems, and ecosystems - not just one-off interventions.</p>
      </div>
    </div>
  </div>
</section> -->

<!-- -----------------------------------------------
     BOTTOM CTA
----------------------------------------------- -->
<section class="pg-cta">
  <div class="container pg-cta-inner">
    <div class="pg-cta-text">
      <h2>Want to get involved?</h2>
      <p>Partner with us, apply to one of our programmes, or support our work to create lasting change for women and girls.</p>
    </div>
    <div class="pg-cta-btns">
      <a href="get-involved.php"    class="witu-btn witu-btn-white">Get Involved</a>
      <a href="programmes.php"      class="witu-btn witu-btn-outline-white">Our Programmes</a>
      <a href="donate.php"          class="witu-btn witu-btn-outline-white">Donate</a>
    </div>
  </div>
</section>

<script>
(function () {
  if (!window.IntersectionObserver) {
    document.querySelectorAll('.fal-card').forEach(c => c.classList.add('in-view'));
    return;
  }
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.fal-card').forEach(c => io.observe(c));
})();
</script>

<?php include 'footer.php'; ?>