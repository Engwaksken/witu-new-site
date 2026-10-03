<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// -- Hero image ------------------------------------------------
$hero = getHeroImage($conn, 'newsletters');

// -- Newsletters -----------------------------------------------
$newsletters = [];
$res = $conn->query("SELECT * FROM newsletters WHERE status='published' ORDER BY published_date DESC");
if ($res) while ($row = $res->fetch_assoc()) $newsletters[] = $row;

$page_title = 'Newsletters';
include 'header.php';
?>

<style>
/* -- Tokens ---------------------------------------------------- */
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
.nl-wrap { width: min(1180px, 92vw); margin-inline: auto; }

/* -- HERO ---------------------------------------------------- */
.nl-hero {
  position: relative;
  min-height: 52vh;
  display: flex;
  align-items: center;
  background: var(--charcoal);
  overflow: hidden;
}
.nl-hero-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: brightness(.32) saturate(.5);
  transform: scale(1.04);
  animation: nlZoom 10s var(--ease) forwards;
}
@keyframes nlZoom { to { transform: scale(1); } }

.nl-hero-slash {
  position: absolute;
  right: -60px; top: 0; bottom: 0;
  width: 36vw;
  background: var(--orange);
  opacity: .07;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}
.nl-hero-grain {
  position: absolute;
  inset: 0;
  opacity: .04;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  pointer-events: none;
}
.nl-hero-inner {
  position: relative;
  z-index: 2;
  padding: 88px 0 78px;
}
.nl-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--orange);
  margin-bottom: 18px;
  opacity: 0;
  animation: nlFadeUp .65s var(--ease) .1s forwards;
}
.nl-eyebrow::before { content:''; display:block; width:28px; height:2px; background:var(--orange); }

.nl-hero h1 {
  font-size: clamp(2.2rem, 5vw, 4rem);
  font-weight: 900;
  line-height: 1.1;
  color: #fff;
  max-width: 700px;
  opacity: 0;
  animation: nlFadeUp .8s var(--ease) .22s forwards;
}
.nl-hero-sub {
  margin-top: 18px;
  font-size: 1.05rem;
  color: rgba(255,255,255,.7);
  max-width: 520px;
  line-height: 1.72;
  opacity: 0;
  animation: nlFadeUp .8s var(--ease) .36s forwards;
}

/* -- CONTENT SECTION ----------------------------------------- */
.nl-section { padding: 76px 0 100px; }

/* -- EMPTY STATE --------------------------------------------- */
.nl-empty {
  text-align: center;
  padding: 80px 20px;
  color: var(--muted);
}
.nl-empty i { font-size: 56px; color: #d1d5db; display: block; margin-bottom: 20px; }
.nl-empty h3 { font-size: 1.4rem; font-weight: 900; color: var(--dark); margin-bottom: 10px; }
.nl-empty p  { font-size: .98rem; }

/* -- GRID ---------------------------------------------------- */
.nl-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}

/* -- Card -- */
.nl-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0,0,0,.06);
  display: flex;
  flex-direction: column;
  position: relative;
  transition: transform .25s var(--ease), box-shadow .25s var(--ease);

  opacity: 0;
  transform: translateY(20px);
}
.nl-card.in-view {
  opacity: 1;
  transform: translateY(0);
  transition: opacity .55s var(--ease), transform .55s var(--ease),
              box-shadow .25s var(--ease);
}
.nl-card:hover { transform: translateY(-6px); box-shadow: 0 18px 44px rgba(0,0,0,.12); }
.nl-card.in-view:hover { transform: translateY(-6px); }

/* Orange top-rule sweep */
.nl-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--orange);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform .45s var(--ease);
  z-index: 1;
}
.nl-card.in-view::before,
.nl-card:hover::before { transform: scaleX(1); }

/* Cover */
.nl-cover {
  height: 260px;
  overflow: hidden;
  flex-shrink: 0;
  background: var(--charcoal);
}
.nl-cover img {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
  transition: transform .4s var(--ease);
}
.nl-card:hover .nl-cover img { transform: scale(1.06); }

.nl-cover-placeholder {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 12px;
  background: linear-gradient(135deg, var(--charcoal) 0%, #2d2d2d 100%);
}
.nl-cover-placeholder i { font-size: 48px; color: var(--orange); opacity: .8; }
.nl-cover-placeholder span {
  font-size: 1rem;
  font-weight: 700;
  color: rgba(255,255,255,.75);
  text-align: center;
  padding: 0 16px;
}

/* Body */
.nl-card-body {
  padding: 22px 20px 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.nl-date-badge {
  display: inline-block;
  background: rgba(248,101,5,.1);
  color: var(--orange);
  padding: 3px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .06em;
  margin-bottom: 10px;
}
.nl-card-title {
  font-size: 1rem;
  font-weight: 900;
  color: var(--dark);
  line-height: 1.3;
  margin-bottom: 8px;
}
.nl-card-desc {
  font-size: .84rem;
  line-height: 1.62;
  color: var(--mid);
  flex: 1;
  margin-bottom: 14px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Meta */
.nl-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 0;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  margin-bottom: 16px;
  font-size: .78rem;
  color: var(--muted);
}
.nl-meta span { display: flex; align-items: center; gap: 5px; }
.nl-meta i { font-size: 11px; }

/* Actions */
.nl-actions { display: flex; gap: 10px; }

.nl-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 11px 10px;
  border-radius: 6px;
  text-decoration: none;
  font-family: var(--ff);
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  transition: background .2s, transform .18s;
}
.nl-btn:hover { transform: translateY(-1px); }
.nl-btn-view { background: var(--orange); color: #fff; }
.nl-btn-view:hover { background: var(--orange-lt); }
.nl-btn-dl { background: var(--dark); color: #fff; }
.nl-btn-dl:hover { background: var(--mid); }

/* -- Animations ---------------------------------------------- */
@keyframes nlFadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* -- Responsive ---------------------------------------------- */
@media (max-width: 1200px) { .nl-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px)  { .nl-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px)  { .nl-hero-inner { padding: 70px 0 60px; } }
@media (max-width: 560px)  { .nl-grid { grid-template-columns: 1fr; } }
</style>

<!-- -- HERO -------------------------------------------------- -->
<section class="nl-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="nl-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="nl-hero-slash" aria-hidden="true"></div>
  <div class="nl-hero-grain"  aria-hidden="true"></div>

  <div class="nl-wrap">
    <div class="nl-hero-inner">
      <p class="nl-eyebrow">Newsletters</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'Newsletters'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="nl-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="nl-hero-sub">Stay informed with our latest updates and insights.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- -- NEWSLETTERS -------------------------------------------- -->
<section class="nl-section">
  <div class="nl-wrap">

    <?php if (empty($newsletters)): ?>
      <div class="nl-empty">
        <i class="fas fa-newspaper" aria-hidden="true"></i>
        <h3>No Newsletters Available</h3>
        <p>Check back soon for our latest newsletters.</p>
      </div>
    <?php else: ?>
      <div class="nl-grid" id="nlGrid">
        <?php foreach ($newsletters as $idx => $nl):
          $id       = (int)$nl['id'];
          $pdfUrl   = 'uploads/newsletters/' . ($nl['pdf_file'] ?? '');
          $coverUrl = !empty($nl['cover_image'])
              ? 'uploads/newsletters/covers/' . $nl['cover_image']
              : '';
          $delay = ($idx % 4) * 80;
        ?>
          <div class="nl-card" style="transition-delay:<?php echo $delay; ?>ms">

            <!-- Cover -->
            <div class="nl-cover">
              <?php if ($coverUrl): ?>
                <img src="<?php echo htmlspecialchars($coverUrl); ?>"
                     alt="<?php echo htmlspecialchars($nl['title'] ?? ''); ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="nl-cover-placeholder">
                  <i class="fas fa-newspaper" aria-hidden="true"></i>
                  <span><?php echo htmlspecialchars($nl['published_date'] ?? ''); ?></span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="nl-card-body">
              <span class="nl-date-badge">
                <?php echo htmlspecialchars($nl['published_date'] ?? ''); ?>
              </span>

              <h3 class="nl-card-title">
                <?php echo htmlspecialchars($nl['title'] ?? ''); ?>
              </h3>

              <?php if (!empty($nl['description'])): ?>
                <p class="nl-card-desc">
                  <?php echo htmlspecialchars(truncate($nl['description'], 110)); ?>
                </p>
              <?php endif; ?>

              <!-- Meta -->
              <div class="nl-meta">
                <span>
                  <i class="fas fa-file-pdf" style="color:#ef4444;" aria-hidden="true"></i>
                  PDF
                </span>
                <span>
                  <i class="fas fa-database" aria-hidden="true"></i>
                  <?php echo htmlspecialchars(formatFileSize($nl['file_size'] ?? 0)); ?>
                </span>
                <span>
                  <i class="fas fa-download" aria-hidden="true"></i>
                  <span class="dl-count" data-newsletter-id="<?php echo $id; ?>">
                    <?php echo number_format((int)($nl['download_count'] ?? 0)); ?>
                  </span>
                </span>
              </div>

              <!-- Actions -->
              <div class="nl-actions">
                <a class="nl-btn nl-btn-view"
                   href="<?php echo htmlspecialchars($pdfUrl); ?>"
                   target="_blank" rel="noopener" title="Open PDF">
                  <i class="fas fa-external-link-alt" aria-hidden="true"></i> View
                </a>
                <a class="nl-btn nl-btn-dl track-download"
                   href="download.php?type=newsletter&id=<?php echo $id; ?>"
                   data-newsletter-id="<?php echo $id; ?>"
                   title="Download PDF">
                  <i class="fas fa-download" aria-hidden="true"></i> Download
                </a>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<script>
/* -- Scroll-reveal ------------------------------------------- */
(function () {
  var cards = document.querySelectorAll('.nl-card');
  if (!cards.length || !('IntersectionObserver' in window)) {
    cards.forEach(function (c) { c.classList.add('in-view'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, { threshold: 0.1 });
  cards.forEach(function (c) { io.observe(c); });
})();

/* -- Download-count refresh ---------------------------------- */
function fmtNum(n) {
  return (n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

async function fetchNlCount(id) {
  try {
    var res  = await fetch('newsletter_stats.php?id=' + encodeURIComponent(id), { cache: 'no-store' });
    var data = await res.json();
    return data.success ? data.download_count : null;
  } catch (e) { return null; }
}

async function refreshAll() {
  var nodes = document.querySelectorAll('.dl-count[data-newsletter-id]');
  var ids   = [...new Set(Array.from(nodes).map(function (n) { return n.dataset.newsletterId; }))];
  for (var i = 0; i < ids.length; i++) {
    var count = await fetchNlCount(ids[i]);
    if (count === null) continue;
    document.querySelectorAll('.dl-count[data-newsletter-id="' + ids[i] + '"]')
      .forEach(function (el) { el.textContent = fmtNum(count); });
  }
}

document.addEventListener('click', function (e) {
  var a = e.target.closest('a.track-download');
  if (!a) return;
  var id = a.dataset.newsletterId;
  if (!id) return;
  setTimeout(async function () {
    var count = await fetchNlCount(id);
    if (count === null) return;
    document.querySelectorAll('.dl-count[data-newsletter-id="' + id + '"]')
      .forEach(function (el) { el.textContent = fmtNum(count); });
  }, 1200);
});

setInterval(refreshAll, 20000);
document.addEventListener('DOMContentLoaded', refreshAll);
</script>

<?php include 'footer.php'; ?>