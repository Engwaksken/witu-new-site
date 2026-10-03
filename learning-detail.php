<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// -- Fetch course ---------------------------------------------
$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header("Location: learning.php"); exit; }

$st = $conn->prepare("SELECT * FROM learning WHERE slug = ? AND status = 'published' LIMIT 1");
$st->bind_param("s", $slug);
$st->execute();
$course = $st->get_result()->fetch_assoc();
$st->close();

if (!$course) { header("Location: learning.php"); exit; }

// -- Prev / Next ----------------------------------------------
$prev = $next = null;

$rp = $conn->prepare("SELECT id, title, slug FROM learning WHERE status='published' AND (display_order < ? OR (display_order = ? AND id < ?)) ORDER BY display_order DESC, id DESC LIMIT 1");
$rp->bind_param("iii", $course['display_order'], $course['display_order'], $course['id']);
$rp->execute();
$prev = $rp->get_result()->fetch_assoc();
$rp->close();

$rn = $conn->prepare("SELECT id, title, slug FROM learning WHERE status='published' AND (display_order > ? OR (display_order = ? AND id > ?)) ORDER BY display_order ASC, id ASC LIMIT 1");
$rn->bind_param("iii", $course['display_order'], $course['display_order'], $course['id']);
$rn->execute();
$next = $rn->get_result()->fetch_assoc();
$rn->close();

// -- SEO ------------------------------------------------------
$page_title       = !empty($course['meta_title'])       ? $course['meta_title']       : $course['title'];
$meta_description = !empty($course['meta_description']) ? $course['meta_description'] : $course['description'];

// -- Image paths ----------------------------------------------
$hero_src     = !empty($course['hero_image'])     ? 'uploads/learning/' . $course['hero_image']     : '';
$featured_src = !empty($course['featured_image']) ? 'uploads/learning/' . $course['featured_image'] : '';

include 'header.php';
?>

<style>
:root {
  --orange   : #f86505;
  --orange-lt: #ff8c38;
  --dark     : #0d0d0d;
  --charcoal : #1a1a1a;
  --mid      : #3d3d3d;
  --muted    : #7a7a7a;
  --cream    : #faf8f5;
  --white    : #ffffff;
  --border   : rgba(0,0,0,.08);
  --ff       : Arial, Helvetica, sans-serif;
  --ease     : cubic-bezier(.22,.61,.36,1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--ff); color: var(--dark); background: #fff; -webkit-font-smoothing: antialiased; }
.ld-container { width: min(960px, 92vw); margin-inline: auto; }

/* -- BREADCRUMB ---------------------------------------------- */
.ld-breadcrumb {
  background: var(--cream);
  border-bottom: 1px solid var(--border);
  padding: 13px 0;
}
.ld-breadcrumb-inner {
  display: flex; align-items: center; gap: 8px;
  font-size: .82rem; color: var(--muted);
}
.ld-breadcrumb a { color: var(--muted); text-decoration: none; transition: color .18s; }
.ld-breadcrumb a:hover { color: var(--orange); }
.ld-breadcrumb-sep { font-size: .7rem; color: #ccc; }
.ld-breadcrumb-current { color: var(--dark); font-weight: 700;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px; }

/* -- HERO IMAGE (hero_image field) --------------------------- */
.ld-hero {
  position: relative;
  width: 100%;
  background: var(--charcoal);
  overflow: hidden;
}

/* With hero_image: full-height cinematic banner */
.ld-hero.has-hero-img {
  min-height: 58vh;
  display: flex;
  align-items: flex-end;
}
.ld-hero-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: brightness(.3) saturate(.55);
  transform: scale(1.04);
  animation: ldZoom 10s var(--ease) forwards;
}
@keyframes ldZoom { to { transform: scale(1); } }

.ld-hero-slash {
  position: absolute; right: -40px; top: 0; bottom: 0;
  width: 32vw; background: var(--orange); opacity: .07;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}
.ld-hero-grain {
  position: absolute; inset: 0; opacity: .04; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
/* Gradient fade at bottom so text is readable */
.ld-hero-fade {
  position: absolute; bottom: 0; left: 0; right: 0;
  height: 60%;
  background: linear-gradient(to top, rgba(0,0,0,.78) 0%, transparent 100%);
  pointer-events: none;
}

.ld-hero-content {
  position: relative;
  z-index: 2;
  width: 100%;
  padding: 0 0 52px;
}

/* Without hero_image: compact dark strip */
.ld-hero.no-hero-img {
  padding: 64px 0 56px;
}
.ld-hero.no-hero-img::after {
  content: '';
  position: absolute; right: -40px; top: 0; bottom: 0;
  width: 32vw; background: var(--orange); opacity: .07;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}
.ld-hero.no-hero-img .ld-hero-content {
  position: relative; z-index: 2; padding: 0;
}

/* Shared hero text */
.ld-hero-eyebrow {
  display: inline-flex; align-items: center; gap: 9px;
  font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
  color: var(--orange); margin-bottom: 16px;
  opacity: 0; animation: ldFadeUp .65s var(--ease) .1s forwards;
}
.ld-hero-eyebrow::before { content:''; display:block; width:26px; height:2px; background:var(--orange); }

.ld-hero h1 {
  font-size: clamp(1.8rem, 4vw, 3.2rem);
  font-weight: 900; line-height: 1.12; color: #fff;
  max-width: 720px;
  opacity: 0; animation: ldFadeUp .8s var(--ease) .22s forwards;
}
.ld-hero-meta {
  display: flex; flex-wrap: wrap; gap: 18px;
  margin-top: 20px;
  opacity: 0; animation: ldFadeUp .8s var(--ease) .36s forwards;
}
.ld-hero-meta-item {
  display: flex; align-items: center; gap: 7px;
  font-size: .88rem; color: rgba(255,255,255,.72);
}
.ld-hero-meta-item i { color: var(--orange); font-size: 12px; }
.ld-level-badge {
  display: inline-block; padding: 3px 12px; border-radius: 999px;
  font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
}
.ld-level-badge.all          { background: rgba(99,102,241,.85); color:#fff; }
.ld-level-badge.beginner     { background: rgba(16,185,129,.85);  color:#fff; }
.ld-level-badge.intermediate { background: rgba(245,158,11,.9);   color:#fff; }
.ld-level-badge.advanced     { background: rgba(239,68,68,.85);   color:#fff; }

/* -- BODY ---------------------------------------------------- */
.ld-body { padding: 64px 0 96px; }

.ld-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 48px;
}
@media (min-width: 740px) {
  .ld-layout.has-sidebar { grid-template-columns: 1fr 280px; align-items: start; }
}

/* -- Content -- */
.ld-section-label {
  display: inline-flex; align-items: center; gap: 9px;
  font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
  color: var(--orange); margin-bottom: 22px;
}
.ld-section-label::before { content:''; display:block; width:26px; height:2px; background:var(--orange); }

.ld-content {
  font-size: 1rem; line-height: 1.82; color: var(--mid);
}
.ld-content p  { margin-bottom: 1.4em; }
.ld-content p:last-child { margin-bottom: 0; }
.ld-content h2, .ld-content h3, .ld-content h4 {
  font-weight: 900; color: var(--dark); margin: 1.8em 0 .6em;
}
.ld-content ul, .ld-content ol { padding-left: 1.5em; margin-bottom: 1.4em; }
.ld-content li { margin-bottom: .4em; }

/* -- Sidebar -- */
.ld-sidebar { display: flex; flex-direction: column; gap: 20px; }

.ld-sidebar-card {
  border: 1px solid var(--border); border-radius: 8px;
  overflow: hidden; background: var(--cream);
}
.ld-sidebar-card-head {
  padding: 14px 18px;
  background: var(--dark);
  font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  color: rgba(255,255,255,.7);
}
.ld-sidebar-card-body { padding: 16px 18px; }

.ld-sidebar-feat-img {
  width: 100%; aspect-ratio: 16/9;
  object-fit: cover; display: block;
}

.ld-detail-row {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid var(--border);
  font-size: .88rem;
}
.ld-detail-row:last-child { border-bottom: none; }
.ld-detail-row i { color: var(--orange); width: 16px; flex-shrink: 0; font-size: 12px; margin-top: 2px; }
.ld-detail-label { color: var(--muted); min-width: 80px; }
.ld-detail-value { color: var(--dark); font-weight: 700; }

.ld-price-box {
  text-align: center; padding: 18px;
  border-top: 1px solid var(--border);
}
.ld-price-amount {
  font-size: 1.8rem; font-weight: 900; color: var(--dark); display: block; margin-bottom: 4px;
}
.ld-price-amount.free { color: #10b981; }
.ld-price-sub { font-size: .78rem; color: var(--muted); margin-bottom: 16px; display: block; }

.ld-apply-btn {
  display: block; width: 100%; text-align: center;
  background: var(--orange); color: #fff;
  font-family: var(--ff); font-size: .9rem; font-weight: 700;
  letter-spacing: .06em; text-transform: uppercase; text-decoration: none;
  padding: 14px; border-radius: 6px;
  transition: background .2s, transform .2s;
}
.ld-apply-btn:hover { background: var(--orange-lt); transform: translateY(-2px); }

/* -- DIVIDER -------------------------------------------------- */
.ld-divider { height: 1px; background: var(--border); margin: 52px 0; }

/* -- PREV / NEXT ---------------------------------------------- */
.ld-nav { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 540px) { .ld-nav { grid-template-columns: 1fr; } }

.ld-nav-card {
  display: flex; flex-direction: column; gap: 5px;
  text-decoration: none;
  border: 1px solid var(--border); border-radius: 8px;
  padding: 18px 20px;
  background: var(--cream);
  transition: border-color .2s, box-shadow .2s, transform .2s;
}
.ld-nav-card:hover {
  border-color: var(--orange);
  box-shadow: 0 8px 28px rgba(248,101,5,.1);
  transform: translateY(-2px);
}
.ld-nav-card.next { text-align: right; }
.ld-nav-dir {
  font-size: 9px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
  color: var(--orange); display: flex; align-items: center; gap: 5px;
}
.ld-nav-card.next .ld-nav-dir { justify-content: flex-end; }
.ld-nav-title { font-size: .92rem; font-weight: 700; color: var(--dark); line-height: 1.3; }


.ld-back-wrap { margin-top: 38px; text-align: center; }
.ld-back {
  display: inline-flex; align-items: center; gap: 8px;
  border: 1.5px solid var(--border); color: var(--muted);
  font-size: .82rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  text-decoration: none; padding: 11px 26px; border-radius: 4px;
  transition: border-color .2s, color .2s, transform .2s;
}
.ld-back:hover { border-color: var(--orange); color: var(--orange); transform: translateY(-2px); }


@keyframes ldFadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>

<section class="ld-hero <?php echo $hero_src ? 'has-hero-img' : 'no-hero-img'; ?>">

  <?php if ($hero_src): ?>
    <div class="ld-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero_src, ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
    <div class="ld-hero-slash" aria-hidden="true"></div>
    <div class="ld-hero-grain"  aria-hidden="true"></div>
    <div class="ld-hero-fade"   aria-hidden="true"></div>
  <?php endif; ?>

  <div class="ld-container" style="width:min(960px,92vw);margin-inline:auto;<?php echo $hero_src ? '' : 'position:relative;z-index:2;'; ?>">
    <div class="ld-hero-content">
      <p class="ld-hero-eyebrow">
        <?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'Course'; ?>
      </p>

      <h1><?php echo htmlspecialchars($course['title']); ?></h1>

      <div class="ld-hero-meta">
        <span class="ld-level-badge <?php echo htmlspecialchars($course['level']); ?>">
          <?php echo $course['level'] === 'all' ? 'All Levels' : ucfirst($course['level']); ?>
        </span>

        <?php if (!empty($course['duration'])): ?>
          <span class="ld-hero-meta-item">
            <i class="fas fa-clock" aria-hidden="true"></i>
            <?php echo htmlspecialchars($course['duration']); ?>
          </span>
        <?php endif; ?>

        <?php if (!empty($course['instructor'])): ?>
          <span class="ld-hero-meta-item">
            <i class="fas fa-user-tie" aria-hidden="true"></i>
            <?php echo htmlspecialchars($course['instructor']); ?>
          </span>
        <?php endif; ?>

        <span class="ld-hero-meta-item">
          <i class="fas fa-<?php echo $course['price'] > 0 ? 'tag' : 'gift'; ?>" aria-hidden="true"></i>
          <?php echo $course['price'] > 0 ? 'UGX ' . number_format($course['price'], 0) : 'Free'; ?>
        </span>
      </div>
    </div>
  </div>
</section>

<div class="ld-body">
  <div class="ld-container">

    <?php
      $hasSidebar = $featured_src || !empty($course['duration']) || !empty($course['instructor']) || !empty($course['apply_link']);
    ?>
    <div class="ld-layout <?php echo $hasSidebar ? 'has-sidebar' : ''; ?>">

      <!-- -- Main content -- -->
      <div class="ld-main">
        <?php if (!empty(trim($course['description']))): ?>
          <div style="background:var(--cream);border-left:3px solid var(--orange);padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:36px;font-size:1.02rem;line-height:1.72;color:var(--mid);">
            <?php echo nl2br(htmlspecialchars($course['description'])); ?>
          </div>
        <?php endif; ?>

        <span class="ld-section-label">Course Overview</span>

        <div class="ld-content">
          <?php if (!empty(trim($course['content']))): ?>
            <?php
              // Render content — allow HTML if trusted, otherwise escape + nl2br
              $paragraphs = preg_split('/\n{2,}/', trim(strip_tags($course['content'], '<p><h2><h3><h4><ul><ol><li><strong><em><a><br>')));
              // If content has real HTML tags, render directly; otherwise nl2br
              if (preg_match('/<[^>]+>/', $course['content'])):
            ?>
              <?php echo $course['content']; ?>
            <?php else:
              foreach ($paragraphs as $para):
                $para = trim($para);
                if (!$para) continue;
            ?>
              <p><?php echo nl2br(htmlspecialchars($para)); ?></p>
            <?php
              endforeach;
              endif;
            ?>
          <?php else: ?>
            <p style="color:var(--muted);font-style:italic;">No course content available yet.</p>
          <?php endif; ?>
        </div>
      </div>

  
      <?php if ($hasSidebar): ?>
        <aside class="ld-sidebar">

        
          <?php if ($featured_src && !$hero_src): ?>
            <div class="ld-sidebar-card">
              <img src="<?php echo htmlspecialchars($featured_src, ENT_QUOTES); ?>"
                   alt="<?php echo htmlspecialchars($course['title']); ?>"
                   class="ld-sidebar-feat-img">
            </div>
          <?php endif; ?>

          <!-- Course details card -->
          <div class="ld-sidebar-card">
            <div class="ld-sidebar-card-head">Course Details</div>
            <div class="ld-sidebar-card-body">

              <?php if (!empty($course['level'])): ?>
                <div class="ld-detail-row">
                  <i class="fas fa-signal" aria-hidden="true"></i>
                  <span class="ld-detail-label">Level</span>
                  <span class="ld-detail-value"><?php echo $course['level'] === 'all' ? 'All Levels' : ucfirst($course['level']); ?></span>
                </div>
              <?php endif; ?>

              <?php if (!empty($course['duration'])): ?>
                <div class="ld-detail-row">
                  <i class="fas fa-clock" aria-hidden="true"></i>
                  <span class="ld-detail-label">Duration</span>
                  <span class="ld-detail-value"><?php echo htmlspecialchars($course['duration']); ?></span>
                </div>
              <?php endif; ?>

              <?php if (!empty($course['instructor'])): ?>
                <div class="ld-detail-row">
                  <i class="fas fa-user-tie" aria-hidden="true"></i>
                  <span class="ld-detail-label">Instructor</span>
                  <span class="ld-detail-value"><?php echo htmlspecialchars($course['instructor']); ?></span>
                </div>
              <?php endif; ?>

              <?php if (!empty($course['category'])): ?>
                <div class="ld-detail-row">
                  <i class="fas fa-tag" aria-hidden="true"></i>
                  <span class="ld-detail-label">Category</span>
                  <span class="ld-detail-value"><?php echo htmlspecialchars($course['category']); ?></span>
                </div>
              <?php endif; ?>

            </div>

            <?php if (!empty($course['apply_link'])): ?>
              <div class="ld-price-box">
                <span class="ld-price-amount <?php echo $course['price'] <= 0 ? 'free' : ''; ?>">
                  <?php echo $course['price'] > 0 ? 'UGX ' . number_format($course['price'], 0) : 'Free'; ?>
                </span>
                <span class="ld-price-sub">
                  <?php echo $course['price'] > 0 ? 'One-time enrolment fee' : 'No cost to join'; ?>
                </span>
                <a class="ld-apply-btn"
                   href="<?php echo htmlspecialchars($course['apply_link'], ENT_QUOTES); ?>"
                   target="_blank" rel="noopener noreferrer">
                  Apply / Enroll Now
                </a>
              </div>
            <?php endif; ?>
          </div>

        </aside>
      <?php endif; ?>

    </div><!-- /.ld-layout -->

    <div class="ld-divider"></div>

    <!-- Prev / Next -->
    <?php if ($prev || $next): ?>
      <nav class="ld-nav" aria-label="Course navigation">
        <?php if ($prev): ?>
          <a class="ld-nav-card prev" href="learning-detail.php?slug=<?php echo urlencode($prev['slug']); ?>">
            <span class="ld-nav-dir">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Previous Course
            </span>
            <span class="ld-nav-title"><?php echo htmlspecialchars($prev['title']); ?></span>
          </a>
        <?php else: ?>
          <div></div>
        <?php endif; ?>

        <?php if ($next): ?>
          <a class="ld-nav-card next" href="learning-detail.php?slug=<?php echo urlencode($next['slug']); ?>">
            <span class="ld-nav-dir">
              Next Course
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </span>
            <span class="ld-nav-title"><?php echo htmlspecialchars($next['title']); ?></span>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <!-- Back -->
    <div class="ld-back-wrap">
      <a class="ld-back" href="learning.php">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to All Courses
      </a>
    </div>

  </div>
</div>

<?php include 'footer.php'; ?>