<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) { header('Location: focus-areas.php'); exit; }

/* -- Focus area + parent -- */
$stmt = $conn->prepare("
    SELECT fa.*,
           parent.title AS parent_title,
           parent.slug  AS parent_slug
    FROM focus_areas fa
    LEFT JOIN focus_areas parent ON fa.parent_id = parent.id
    WHERE fa.slug = ? AND fa.status = 'published'
    LIMIT 1
");
$stmt->bind_param('s', $slug);
$stmt->execute();
$area = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$area) { header('HTTP/1.1 404 Not Found'); include '404.php'; exit; }

/* -- Children (sub-areas) -- */
$children = [];
if (empty($area['parent_id'])) {
    $cs = $conn->prepare("
        SELECT * FROM focus_areas
        WHERE parent_id = ? AND status = 'published'
        ORDER BY display_order ASC, id ASC
    ");
    $cs->bind_param('i', $area['id']);
    $cs->execute();
    $children = $cs->get_result()->fetch_all(MYSQLI_ASSOC);
    $cs->close();
}

/* -- Sibling areas (same parent, excl. current) -- */
$siblings = [];
if (!empty($area['parent_id'])) {
    $ss = $conn->prepare("
        SELECT id, title, slug, featured_image, icon, description
        FROM focus_areas
        WHERE parent_id = ? AND status = 'published' AND id != ?
        ORDER BY display_order ASC
        LIMIT 4
    ");
    $ss->bind_param('ii', $area['parent_id'], $area['id']);
    $ss->execute();
    $siblings = $ss->get_result()->fetch_all(MYSQLI_ASSOC);
    $ss->close();
}

/* -- Related programmes -- */
$programmes = [];
$pr = @$conn->query("
    SELECT id, title, slug, summary, featured_image, icon
    FROM programmes
    WHERE status = 'published'
    ORDER BY display_order ASC, id ASC
    LIMIT 3
");
if ($pr) $programmes = $pr->fetch_all(MYSQLI_ASSOC);

/* -- Meta -- */
$page_title       = htmlspecialchars($area['meta_title']       ?: $area['title'],       ENT_QUOTES, 'UTF-8');
$meta_description = htmlspecialchars($area['meta_description'] ?: $area['description'], ENT_QUOTES, 'UTF-8');
$body_class       = 'focus-area-detail-page';

$t  = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$tb = fn($v, $n = 110) => htmlspecialchars(
    mb_strlen($v ?? '') > $n ? mb_substr($v, 0, $n).'…' : ($v ?? ''),
    ENT_QUOTES, 'UTF-8'
);

include 'header.php';
?>

<section class="fad-hero <?php echo !empty($area['featured_image']) ? 'has-photo' : 'no-photo'; ?>">
  <?php if (!empty($area['featured_image'])): ?>
    <div class="fad-hero-bg"
         style="background-image:url('uploads/focus-areas/<?php echo $t($area['featured_image']); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>
  <div class="fad-hero-overlay" aria-hidden="true"></div>
  <div class="fad-hero-slash"   aria-hidden="true"></div>
  <div class="fad-hero-grain"   aria-hidden="true"></div>

  <div class="container fad-hero-inner">
   
    <span class="fad-eyebrow">
      <?php echo !empty($area['parent_title']) ? $t($area['parent_title']).' · Sub-Area' : 'Focus Area'; ?>
    </span>
    <h1><?php echo $t($area['title']); ?></h1>

    <?php if (!empty($area['description'])): ?>
      <p class="fad-hero-sub"><?php echo nl2br($t($area['description'])); ?></p>
    <?php endif; ?>
  </div>
</section>

<!-- -----------------------------------------------
     BODY
----------------------------------------------- -->
<div class="fad-body">
  <div class="container fad-layout">

    <!-- -- MAIN -- -->
    <main class="fad-main">

      <?php if (!empty($area['content'])): ?>
      <div class="fad-content">
        <?php echo $area['content']; ?>
      </div>
      <?php endif; ?>

      <!-- Sub-areas (children) -->
      <?php if (!empty($children)): ?>
      <div class="fad-children">
        <div class="fad-children-head">
         
          <h2>Explore this focus area</h2>
          <p>Drill deeper into the specific programmes and initiatives within <?php echo $t($area['title']); ?>.</p>
        </div>
        <div class="fal-grid fad-children-grid" id="fadChildGrid">
          <?php
          $accents = ['#f86505','#2e6f8a','#66c2c4','#b6d1a6'];
          foreach ($children as $ci => $child):
            $accent  = $accents[$ci % count($accents)];
            $cslug   = htmlspecialchars($child['slug'] ?? '', ENT_QUOTES, 'UTF-8');
            $cicon   = $t($child['icon'] ?? 'fas fa-bullseye');
            $clink   = "focus-area.php?slug={$cslug}";
          ?>
          <article class="fal-card fad-child-card"
                   style="--fal-accent:<?php echo $accent; ?>;">
            <a href="<?php echo $clink; ?>" class="fal-card-thumb" tabindex="-1" aria-hidden="true">
              <?php if (!empty($child['featured_image'])): ?>
                <img src="uploads/focus-areas/<?php echo $t($child['featured_image']); ?>"
                     alt="<?php echo $t($child['title']); ?>" loading="lazy">
              <?php else: ?>
                <div class="fal-card-placeholder"></div>
              <?php endif; ?>
              <div class="fal-thumb-overlay"></div>
            </a>
            <div class="fal-card-body">
              
              <h3 class="fal-card-title">
                <a href="<?php echo $clink; ?>"><?php echo $t($child['title']); ?></a>
              </h3>
              <?php if (!empty($child['description'])): ?>
                <p class="fal-card-desc"><?php echo $t($child['description']); ?></p>
              <?php endif; ?>
              <a href="<?php echo $clink; ?>" class="fal-card-cta">
                Explore
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Related programmes -->
      <?php if (!empty($programmes)): ?>
      <div class="fad-programmes">
        <div class="fad-programmes-head">
      
          <h2>Related Programmes</h2>
          <p>These WITU programmes operate within or alongside <?php echo $t($area['title']); ?>.</p>
        </div>
        <div class="fad-prog-grid">
          <?php foreach ($programmes as $pi => $prog):
            $paccents = ['#f86505','#2e6f8a','#66c2c4'];
            $pacc = $paccents[$pi % 3];
            $pslug = htmlspecialchars($prog['slug'] ?? '', ENT_QUOTES, 'UTF-8');
            $plink = "programme.php?slug={$pslug}";
          ?>
          <article class="fad-prog-card" style="--fad-prog-accent:<?php echo $pacc; ?>;">
            <a href="<?php echo $plink; ?>" class="fad-prog-thumb" tabindex="-1" aria-hidden="true">
              <?php if (!empty($prog['featured_image'])): ?>
                <img src="uploads/programmes/<?php echo $t($prog['featured_image']); ?>"
                     alt="<?php echo $t($prog['title']); ?>" loading="lazy">
              <?php else: ?>
                <div class="fad-prog-placeholder">
                  <i class="<?php echo $t($prog['icon'] ?? 'fas fa-star'); ?>"></i>
                </div>
              <?php endif; ?>
            </a>
            <div class="fad-prog-body">
              <h4><a href="<?php echo $plink; ?>"><?php echo $t($prog['title']); ?></a></h4>
              <p><?php echo $tb($prog['summary'] ?? '', 100); ?></p>
              <a href="<?php echo $plink; ?>" class="fad-prog-link">
                Learn More <i class="fas fa-arrow-right"></i>
              </a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <div class="fad-prog-more">
          <a href="programmes.php" class="witu-btn witu-btn-primary">
            <i class="fas fa-th-large"></i> View All Programmes
          </a>
        </div>
      </div>
      <?php endif; ?>

    </main>

    <!-- -- SIDEBAR -- -->
    <aside class="fad-sidebar">

      <!-- Get involved card -->
      <div class="fad-cta-card">
        <div class="fad-cta-icon"><i class="fas fa-hands-helping"></i></div>
        <h3>Get Involved</h3>
        <p>Support our work in <?php echo $t($area['title']); ?>- partner, volunteer, donate, or apply.</p>
        <div class="fad-cta-btns">
          <a href="partner.php" class="witu-btn witu-btn-primary fad-cta-full">
            <i class="fas fa-arrow-right"></i> Get Involved
          </a>
          <a href="donate.php" class="witu-btn witu-btn-outline-white fad-cta-full">
            <i class="fas fa-heart"></i> Donate
          </a>
        </div>
      </div>

      <!-- Nav: siblings or focus areas list -->
      <?php if (!empty($siblings)): ?>
      <div class="fad-nav-card">
        <h4 class="fad-nav-title">
          <i class="fas fa-th-large"></i>
          More in <?php echo $t($area['parent_title']); ?>
        </h4>
        <ul class="fad-nav-list">
          <?php foreach ($siblings as $sib): ?>
          <li>
            <a href="focus-area.php?slug=<?php echo urlencode($sib['slug']); ?>">
              <?php if (!empty($sib['icon'])): ?>
                <i class="<?php echo $t($sib['icon']); ?>"></i>
              <?php else: ?>
                <i class="fas fa-chevron-right"></i>
              <?php endif; ?>
              <span><?php echo $t($sib['title']); ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php else: ?>
      <!-- Back to all focus areas -->
      <div class="fad-nav-card">
        <h4 class="fad-nav-title"><i class="fas fa-th-large"></i> Focus Areas</h4>
        <ul class="fad-nav-list">
          <li>
            <a href="focus-areas.php">
              <i class="fas fa-arrow-left"></i>
              <span>All Focus Areas</span>
            </a>
          </li>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Share -->
      <div class="fad-share-card">
        <h4 class="fad-nav-title"><i class="fas fa-share-alt"></i> Share</h4>
        <div class="pd-share-btns">
          <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($area['title'].'- WITU'); ?>&url=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>"
             target="_blank" rel="noopener" class="pd-share-btn twitter" aria-label="Share on X">
            <i class="fab fa-x-twitter"></i>
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>"
             target="_blank" rel="noopener" class="pd-share-btn facebook" aria-label="Share on Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>&title=<?php echo urlencode($area['title']); ?>"
             target="_blank" rel="noopener" class="pd-share-btn linkedin" aria-label="Share on LinkedIn">
            <i class="fab fa-linkedin-in"></i>
          </a>
          <a href="https://wa.me/?text=<?php echo urlencode($area['title'].'- https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>"
             target="_blank" rel="noopener" class="pd-share-btn whatsapp" aria-label="Share on WhatsApp">
            <i class="fab fa-whatsapp"></i>
          </a>
        </div>
      </div>

    </aside>
  </div>
</div>

<!-- -----------------------------------------------
     BOTTOM CTA
----------------------------------------------- -->
<section class="pg-cta">
  <div class="container pg-cta-inner">
    <div class="pg-cta-text">
      <h2>Ready to make an impact?</h2>
      <p>Join WITU- as a partner, funder, employer, volunteer, or participant- and help us build opportunity for women and girls.</p>
    </div>
    <div class="pg-cta-btns">
      <a href="partner.php"    class="witu-btn witu-btn-white">Get Involved</a>
      <a href="programmes.php"      class="witu-btn witu-btn-outline-white">Our Programmes</a>
      <a href="donate.php"          class="witu-btn witu-btn-outline-white">Donate</a>
    </div>
  </div>
</section>

<script>
(function () {
  const cards = document.querySelectorAll('.fal-card');
  if (!cards.length || !window.IntersectionObserver) {
    cards.forEach(c => c.classList.add('in-view'));
    return;
  }
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, { threshold: 0.08 });
  cards.forEach(c => io.observe(c));
})();
</script>

<?php include 'footer.php'; ?>