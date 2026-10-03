<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';


// Hero image
$hero = getHeroImage($conn, 'learning');

// Filters
$filter_level    = trim($_GET['level']    ?? '');
$filter_category = trim($_GET['category'] ?? '');
$search          = trim($_GET['search']   ?? '');

// Build query
$where  = ["l.status = 'published'"];
$params = [];
$types  = '';

if ($filter_level) {
    $where[]  = "l.level = ?";
    $params[] = $filter_level;
    $types   .= 's';
}
if ($filter_category) {
    $where[]  = "l.category = ?";
    $params[] = $filter_category;
    $types   .= 's';
}
if ($search) {
    $where[]  = "(l.title LIKE ? OR l.description LIKE ? OR l.instructor LIKE ?)";
    $s         = '%' . $search . '%';
    $params    = array_merge($params, [$s, $s, $s]);
    $types    .= 'sss';
}

$sql = "SELECT * FROM learning l WHERE " . implode(' AND ', $where)
     . " ORDER BY l.display_order ASC, l.created_at DESC";

$courses = [];
if ($params) {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $courses[] = $r;
    $st->close();
} else {
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $courses[] = $r;
}

// Distinct categories for filter
$categories = [];
$cr = $conn->query("SELECT DISTINCT category FROM learning WHERE status='published' AND category != '' ORDER BY category ASC");
if ($cr) while ($row = $cr->fetch_assoc()) $categories[] = $row['category'];

$page_title = 'Learning';
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
body { font-family: var(--ff); color: var(--dark); background: #ff98001a; -webkit-font-smoothing: antialiased; }
.lrn-container { width: min(1180px, 92vw); margin-inline: auto; }

/* -- HERO ---------------------------------------------------- */
.lrn-hero {
  position: relative;
  min-height: 62vh;
  display: flex;
  align-items: center;
  background: var(--charcoal);
  overflow: hidden;
}
.lrn-hero-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: brightness(.32) saturate(.5);
  transform: scale(1.04);
  animation: lrnZoom 10s var(--ease) forwards;
}
@keyframes lrnZoom { to { transform: scale(1); } }
.lrn-hero-slash {
  position: absolute;
  right: -60px; top: 0; bottom: 0;
  width: 36vw;
  background: var(--orange);
  opacity: .07;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}
.lrn-hero-grain {
  position: absolute;
  inset: 0;
  opacity: .04;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  pointer-events: none;
}
.lrn-hero-inner { position: relative; z-index: 2; padding: 100px 0 90px; }
.lrn-hero-eyebrow {
  display: inline-flex; align-items: center; gap: 10px;
  font-size: 10px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
  color: var(--orange); margin-bottom: 20px;
  opacity: 0; animation: lrnFadeUp .65s var(--ease) .1s forwards;
}
.lrn-hero-eyebrow::before { content:''; display:block; width:28px; height:2px; background:var(--orange); }
.lrn-hero h1 {
  font-size: clamp(2.2rem, 5vw, 4.4rem); font-weight: 900;
  color: #fff; line-height: 1.1; max-width: 740px;
  opacity: 0; animation: lrnFadeUp .8s var(--ease) .22s forwards;
}
.lrn-hero-sub {
  margin-top: 22px; font-size: 1.05rem; color: rgba(255,255,255,.68);
  max-width: 520px; line-height: 1.7;
  opacity: 0; animation: lrnFadeUp .8s var(--ease) .38s forwards;
}

/* -- FILTER BAR ---------------------------------------------- */
.lrn-filters {
  background: var(--cream);
  border-bottom: 1px solid var(--border);
  padding: 16px 0;
  position: sticky; top: 0; z-index: 40;
}
.lrn-filters-inner { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.lrn-search { flex: 1; min-width: 200px; position: relative; }
.lrn-search input {
  width: 100%; padding: 10px 14px 10px 38px;
  border: 1px solid var(--border); border-radius: 6px;
  font-family: var(--ff); font-size: .9rem; background: #fff; outline: none;
  transition: border-color .2s;
}
.lrn-search input:focus { border-color: var(--orange); }
.lrn-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
.lrn-filter-select {
  padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px;
  font-family: var(--ff); font-size: .88rem; background: #fff; cursor: pointer; outline: none;
  transition: border-color .2s;
}
.lrn-filter-select:focus { border-color: var(--orange); }
.lrn-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.lrn-chip {
  display: inline-block; padding: 6px 14px; border-radius: 999px;
  font-size: .78rem; font-weight: 700;
  border: 1.5px solid var(--border); background: #fff; color: var(--mid);
  text-decoration: none; transition: border-color .2s, color .2s, background .2s;
}
.lrn-chip:hover, .lrn-chip.active { border-color: var(--orange); color: var(--orange); background: rgba(248,101,5,.06); }
.lrn-chip.clear { border-color: #ef4444; color: #ef4444; }
.lrn-chip.clear:hover { background: rgba(239,68,68,.06); }

/* -- GRID SECTION -------------------------------------------- */
.lrn-grid-section { padding: 72px 0 100px; }
.lrn-grid-head {
  display: flex; align-items: baseline; justify-content: space-between;
  gap: 16px; margin-bottom: 40px; flex-wrap: wrap;
}
.lrn-grid-head h2 { font-size: 1.5rem; font-weight: 900; color: var(--dark); }
.lrn-count { font-size: .85rem; color: var(--muted); }

.lrn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }

/* -- Card -- */
.lrn-card {
  border: 1px solid var(--border); border-radius: 8px;
  overflow: hidden; background: #fff;
  display: flex; flex-direction: column;
  opacity: 0; transform: translateY(22px);
  transition: opacity .6s var(--ease), transform .6s var(--ease), box-shadow .28s var(--ease);
}
.lrn-card.in-view { opacity: 1; transform: translateY(0); }
.lrn-card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(0,0,0,.1); }
.lrn-card.in-view:hover { transform: translateY(-5px); }

.lrn-card-img-wrap {
  position: relative; aspect-ratio: 16/9;
  overflow: hidden; background: var(--charcoal); flex-shrink: 0;
}
.lrn-card-img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s var(--ease); }
.lrn-card:hover .lrn-card-img { transform: scale(1.04); }

.lrn-card-icon-wrap {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}
.lrn-card-icon-wrap i { font-size: 44px; color: var(--orange); opacity: .7; }

.lrn-level-pill {
  position: absolute; top: 12px; left: 12px;
  padding: 4px 12px; border-radius: 999px;
  font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
}
.lrn-level-pill.all          { background: rgba(99,102,241,.85); color:#fff; }
.lrn-level-pill.beginner     { background: rgba(16,185,129,.85);  color:#fff; }
.lrn-level-pill.intermediate { background: rgba(245,158,11,.9);   color:#fff; }
.lrn-level-pill.advanced     { background: rgba(239,68,68,.85);   color:#fff; }

.lrn-price-pill {
  position: absolute; bottom: 12px; right: 12px;
  padding: 5px 12px; border-radius: 6px;
  font-size: 12px; font-weight: 700;
  background: rgba(0,0,0,.72); color: #fff;
}
.lrn-price-pill.free { background: rgba(16,185,129,.85); }

.lrn-card-body { padding: 20px 20px 12px; flex: 1; display: flex; flex-direction: column; }
.lrn-card-meta {
  display: flex; align-items: center; gap: 14px;
  font-size: .78rem; color: var(--muted); margin-bottom: 10px; flex-wrap: wrap;
}
.lrn-card-meta span { display: flex; align-items: center; gap: 5px; }
.lrn-card-meta i { color: var(--orange); font-size: 11px; }
.lrn-card-body h3 { font-size: 1.06rem; font-weight: 900; color: var(--dark); line-height: 1.3; margin-bottom: 10px; }
.lrn-card-desc {
  font-size: .88rem; line-height: 1.68; color: var(--mid); flex: 1;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}

.lrn-card-footer {
  padding: 12px 20px 18px;
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  border-top: 1px solid var(--border); margin-top: 14px;
}
.lrn-card-instructor { font-size: .8rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }

.lrn-btn {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--orange); color: #fff;
  font-family: var(--ff); font-size: .78rem; font-weight: 700;
  letter-spacing: .07em; text-transform: uppercase; text-decoration: none;
  padding: 9px 18px; border-radius: 4px;
  transition: background .2s, transform .2s; flex-shrink: 0;
}
.lrn-btn:hover { background: var(--orange-lt); transform: translateY(-1px); }
.lrn-btn svg { transition: transform .2s; }
.lrn-btn:hover svg { transform: translateX(3px); }

/* -- Empty state --------------------------------------------- */
.lrn-empty { text-align: center; padding: 80px 20px; color: var(--muted); }
.lrn-empty i { font-size: 52px; color: #d1d5db; display: block; margin-bottom: 20px; }
.lrn-empty strong { display: block; font-size: 1.1rem; margin-bottom: 8px; color: var(--dark); }

/* -- Animations ---------------------------------------------- */
@keyframes lrnFadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .lrn-hero-inner { padding: 72px 0 64px; }
  .lrn-grid       { grid-template-columns: 1fr; }
}
</style>

<!-- -- HERO -------------------------------------------------- -->
<section class="lrn-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="lrn-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="lrn-hero-slash" aria-hidden="true"></div>
  <div class="lrn-hero-grain"  aria-hidden="true"></div>

  <div class="lrn-container">
    <div class="lrn-hero-inner">
      <p class="lrn-hero-eyebrow">WITU Learning</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'Grow Your Skills.<br>Build Your Future.'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="lrn-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="lrn-hero-sub">
          Explore courses and programmes designed to give you the tools,
          knowledge, and confidence to succeed on your own terms.
        </p>
      <?php endif; ?>

    </div>
  </div>
</section>

<!-- -- FILTER BAR -------------------------------------------- -->
<nav class="lrn-filters" aria-label="Course filters">
  <div class="lrn-container">
    <form method="GET" class="lrn-filters-inner">

      <div class="lrn-search">
        <i class="fas fa-search lrn-search-icon" aria-hidden="true"></i>
        <input type="search" name="search" aria-label="Search courses"
               placeholder="Search courses..."
               value="<?php echo htmlspecialchars($search); ?>"
               >
      </div>

      <?php if (!empty($categories)): ?>
        <select name="category" aria-label="Course category" class="lrn-filter-select" >
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>"
              <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Apply filters</button>
      <div class="lrn-chips">
        <?php
          $levels = ['all' => 'All Levels', 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'];
          foreach ($levels as $val => $label):
            $isActive = ($val === 'all') ? !$filter_level : ($filter_level === $val);
        ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['level' => $val === 'all' ? '' : $val])); ?>"
             class="lrn-chip <?php echo $isActive ? 'active' : ''; ?>">
            <?php echo $label; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($search || $filter_category || $filter_level): ?>
        <a href="learning.php" class="lrn-chip clear">
          <i class="fas fa-times" style="font-size:10px;margin-right:3px;" aria-hidden="true"></i>Clear
        </a>
      <?php endif; ?>

    </form>
  </div>
</nav>

<!-- -- GRID -------------------------------------------------- -->
<section class="lrn-grid-section">
  <div class="lrn-container">

    <div class="lrn-grid-head">
      <h2>
        <?php if ($search): ?>Results for "<?php echo htmlspecialchars($search); ?>"
        <?php elseif ($filter_category): ?><?php echo htmlspecialchars($filter_category); ?>
        <?php else: ?>All Courses<?php endif; ?>
      </h2>
      <span class="lrn-count">
        <?php echo count($courses); ?> course<?php echo count($courses) !== 1 ? 's' : ''; ?>
      </span>
    </div>

    <?php if (empty($courses)): ?>
      <div class="lrn-empty">
        <i class="fas fa-graduation-cap" aria-hidden="true"></i>
        <strong>No courses found</strong>
        <p>Try adjusting your search or filters.</p>
      </div>
    <?php else: ?>
      <div class="lrn-grid" id="lrnGrid">
        <?php foreach ($courses as $idx => $course):
          $delay = ($idx % 3) * 80;
        ?>
          <div class="lrn-card" style="transition-delay:<?php echo $delay; ?>ms">

            <!-- Thumbnail -->
            <div class="lrn-card-img-wrap">
              <?php if (!empty($course['featured_image'])): ?>
                <img class="lrn-card-img"
                     src="uploads/learning/<?php echo htmlspecialchars($course['featured_image']); ?>"
                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="lrn-card-icon-wrap">
                  <i class="<?php echo !empty($course['icon']) ? htmlspecialchars($course['icon']) : 'fas fa-graduation-cap'; ?>" aria-hidden="true"></i>
                </div>
              <?php endif; ?>

              <span class="lrn-level-pill <?php echo htmlspecialchars($course['level']); ?>">
                <?php echo $course['level'] === 'all' ? 'All Levels' : ucfirst($course['level']); ?>
              </span>

              <span class="lrn-price-pill <?php echo $course['price'] <= 0 ? 'free' : ''; ?>">
                <?php echo $course['price'] > 0 ? 'UGX ' . number_format($course['price'], 0) : 'FREE'; ?>
              </span>
            </div>

            <!-- Body -->
            <div class="lrn-card-body">
              <div class="lrn-card-meta">
                <?php if (!empty($course['duration'])): ?>
                  <span><i class="fas fa-clock" aria-hidden="true"></i><?php echo htmlspecialchars($course['duration']); ?></span>
                <?php endif; ?>
                <?php if (!empty($course['category'])): ?>
                  <span><i class="fas fa-tag" aria-hidden="true"></i><?php echo htmlspecialchars($course['category']); ?></span>
                <?php endif; ?>
              </div>
              <h3><?php echo htmlspecialchars($course['title']); ?></h3>
              <p class="lrn-card-desc"><?php echo htmlspecialchars($course['description']); ?></p>
            </div>

            <!-- Footer -->
            <div class="lrn-card-footer">
              <span class="lrn-card-instructor">
                <?php if (!empty($course['instructor'])): ?>
                  <i class="fas fa-user-tie" style="color:var(--orange);font-size:11px;" aria-hidden="true"></i>
                  <?php echo htmlspecialchars($course['instructor']); ?>
                <?php endif; ?>
              </span>
              <a class="lrn-btn"
                 href="learning-detail.php?slug=<?php echo urlencode($course['slug']); ?>">
                Learn More
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </a>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<script>
(function () {
  var cards = document.querySelectorAll('.lrn-card');
  if (!cards.length || !('IntersectionObserver' in window)) {
    cards.forEach(function(c){ c.classList.add('in-view'); });
    return;
  }
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if (e.isIntersecting){ e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, { threshold: 0.1 });
  cards.forEach(function(c){ io.observe(c); });
})();
</script>

<?php include 'footer.php'; ?>