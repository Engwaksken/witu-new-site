<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// Hero image
$hero = getHeroImage($conn, 'team');

// Site name
$site_name = 'WITU';
$res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_name' LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $site_name = !empty($row['setting_value']) ? $row['setting_value'] : $site_name;
}

// Team members: only leadership, no staff
$team = [];

$sql = "
    SELECT *
    FROM team
    WHERE status = 'active'
      AND level IN ('board_directors', 'management_team')
    ORDER BY
      CASE level
        WHEN 'board_directors' THEN 1
        WHEN 'management_team' THEN 2
        ELSE 3
      END,
      display_order ASC,
      name ASC
";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $team[] = $row;
    }
}

// Group by level
$grouped = [
    'board_directors' => [],
    'management_team' => []
];

foreach ($team as $m) {
    $lvl = $m['level'] ?? '';

    if (isset($grouped[$lvl])) {
        $grouped[$lvl][] = $m;
    }
}

$levelTitles = [
    'board_directors' => 'Board of Directors',
    'management_team' => 'Management Team'
];

$page_title = 'Our Leadership - ' . $site_name;
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
.tm-wrap { width: min(1180px, 92vw); margin-inline: auto; }

/* HERO */
.tm-hero {
  position: relative;
  min-height: 56vh;
  display: flex;
  align-items: center;
  background: var(--charcoal);
  overflow: hidden;
}
.tm-hero-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: brightness(.32) saturate(.5);
  transform: scale(1.04);
  animation: tmZoom 10s var(--ease) forwards;
}
@keyframes tmZoom { to { transform: scale(1); } }

.tm-hero-slash {
  position: absolute;
  right: -60px; top: 0; bottom: 0;
  width: 36vw;
  background: var(--orange);
  opacity: .07;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}
.tm-hero-grain {
  position: absolute;
  inset: 0;
  opacity: .04;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  pointer-events: none;
}
.tm-hero-inner {
  position: relative;
  z-index: 2;
  padding: 90px 0 80px;
  text-align: center;
}
.tm-eyebrow {
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
  animation: tmFadeUp .65s var(--ease) .1s forwards;
}
.tm-eyebrow::before { content:''; display:block; width:28px; height:2px; background:var(--orange); }

.tm-hero h1 {
  font-size: clamp(2rem, 5vw, 3.8rem);
  font-weight: 900;
  line-height: 1.1;
  color: #fff;
  max-width: 720px;
  margin: 0 auto 18px;
  opacity: 0;
  animation: tmFadeUp .8s var(--ease) .22s forwards;
}
.tm-hero-sub {
  font-size: 1.05rem;
  color: rgba(255,255,255,.7);
  max-width: 540px;
  margin: 0 auto;
  line-height: 1.72;
  opacity: 0;
  animation: tmFadeUp .8s var(--ease) .36s forwards;
}

/* SECTIONS  */
.tm-content { padding: 80px 0 100px; }

.tm-section { margin-bottom: 72px; }
.tm-section:last-child { margin-bottom: 0; }

.tm-section-head {
  margin-bottom: 36px;
  text-align: center;
}
.tm-section-kicker {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--orange);
  margin-bottom: 14px;
}
.tm-section-kicker::before { content:''; display:block; width:26px; height:2px; background:var(--orange); }

.tm-section-head h2 {
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  font-weight: 900;
  color: var(--dark);
  margin-bottom: 8px;
}
.tm-section-head p {
  font-size: .95rem;
  color: var(--muted);
  max-width: 520px;
  margin: 0 auto;
  line-height: 1.65;
}

/* GRID  */
.tm-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 26px;
}
/* -- Card -- */
.tm-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0,0,0,.05);
  transition: transform .25s var(--ease), box-shadow .25s var(--ease);
  display: flex;
  flex-direction: column;
  position: relative;

  opacity: 0;
  transform: translateY(22px);
}
.tm-card.in-view {
  opacity: 1;
  transform: translateY(0);
  transition: opacity .55s var(--ease), transform .55s var(--ease),
              box-shadow .25s var(--ease);
}
.tm-card:hover { transform: translateY(-5px); box-shadow: 0 18px 44px rgba(0,0,0,.1); }
.tm-card.in-view:hover { transform: translateY(-5px); }

/* Orange top-rule */
.tm-card::before {
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
.tm-card.in-view::before,
.tm-card:hover::before { transform: scaleX(1); }

/* Photo */
.tm-photo-wrap {
  height: 260px;
  overflow: hidden;
  flex-shrink: 0;
  background: #f3f4f6;
}
.tm-photo {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
  filter: grayscale(.1);
  transition: filter .4s var(--ease), transform .4s var(--ease);
}
.tm-card:hover .tm-photo { filter: grayscale(0); transform: scale(1.04); }

.tm-photo-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #e5e7eb 0%, #f3f4f6 100%);
}
.tm-photo-placeholder i { font-size: 44px; color: #9ca3af; }

/* Body */
.tm-card-body {
  padding: 22px 20px 20px;
  text-align: center;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.tm-card-name {
  font-size: 1.05rem;
  font-weight: 900;
  color: var(--dark);
  line-height: 1.25;
  margin-bottom: 6px;
}
.tm-card-position {
  font-size: .88rem;
  font-weight: 700;
  color: var(--orange);
  margin-bottom: 16px;
  line-height: 1.4;
}

/* Social */
.tm-social {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: auto;
  padding-top: 8px;
}
.tm-social a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  border-radius: 50%;
  background: rgba(248,101,5,.08);
  color: var(--orange);
  font-size: 16px;
  text-decoration: none;
  transition: background .2s, transform .2s;
}
.tm-social a:hover { background: var(--orange); color: #fff; transform: scale(1.1); }

/* EMPTY STATE */
.tm-empty {
  text-align: center;
  padding: 80px 20px;
  color: var(--muted);
}
.tm-empty i { font-size: 54px; color: #d1d5db; display: block; margin-bottom: 20px; }
.tm-empty h3 { font-size: 1.4rem; font-weight: 900; color: var(--dark); margin-bottom: 10px; }
.tm-empty p  { font-size: .98rem; }

/*  Animations  */
@keyframes tmFadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Responsive  */
@media (max-width: 768px) { .tm-hero-inner { padding: 72px 0 64px; } }
@media (max-width: 560px) { .tm-grid { grid-template-columns: 1fr; } }

/* Tablet */
@media (max-width: 1024px) {
  .tm-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}


</style>

<!--  HERO  -->
<section class="tm-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="tm-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="tm-hero-slash" aria-hidden="true"></div>
  <div class="tm-hero-grain"  aria-hidden="true"></div>

  <div class="tm-wrap">
    <div class="tm-hero-inner">
      <p class="tm-eyebrow">Leadership</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'Our Leadership Team'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="tm-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="tm-hero-sub">
          Meet the dedicated professionals guiding our mission.
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- TEAM  -->
<section class="tm-content">
  <div class="tm-wrap">

    <?php
      $hasAny = !empty($grouped['board_directors']) ||
                !empty($grouped['management_team']) ||
                !empty($grouped['staff']);
    ?>

    <?php if (!$hasAny): ?>
      <div class="tm-empty">
        <i class="fas fa-users" aria-hidden="true"></i>
        <h3>Team Information Coming Soon</h3>
        <p>Check back to meet our leadership team.</p>
      </div>
    <?php else: ?>

      <?php foreach (['board_directors','management_team','staff'] as $levelKey): ?>
        <?php if (empty($grouped[$levelKey])) continue; ?>

        <div class="tm-section">
          <div class="tm-section-head">
            <span class="tm-section-kicker">
              <?php
                if ($levelKey === 'board_directors')      echo 'Governance';
                elseif ($levelKey === 'management_team')  echo 'Leadership';
                else                                      echo 'Team';
              ?>
            </span>

            <h2><?php echo htmlspecialchars($levelTitles[$levelKey]); ?></h2>

            <p>
              <?php if ($levelKey === 'board_directors'): ?>
                Strategic oversight and governance.
              <?php elseif ($levelKey === 'management_team'): ?>
                Operational leadership and programme delivery.
              <?php else: ?>
                The team supporting daily implementation.
              <?php endif; ?>
            </p>
          </div>

          <div class="tm-grid" id="tmGrid-<?php echo $levelKey; ?>">
            <?php foreach ($grouped[$levelKey] as $idx => $member):
              $delay = ($idx % 4) * 80;
            ?>
              <div class="tm-card" style="transition-delay:<?php echo $delay; ?>ms">

                <!-- Photo -->
                <div class="tm-photo-wrap">
                  <?php if (!empty($member['photo'])): ?>
                    <img class="tm-photo"
                         src="uploads/team/<?php echo htmlspecialchars($member['photo']); ?>"
                         alt="<?php echo htmlspecialchars($member['name']); ?>"
                         loading="lazy">
                  <?php else: ?>
                    <div class="tm-photo-placeholder">
                      <i class="fas fa-user" aria-hidden="true"></i>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="tm-card-body">
                  <h3 class="tm-card-name">
                    <?php echo htmlspecialchars($member['name']); ?>
                  </h3>
                  <p class="tm-card-position">
                    <?php echo htmlspecialchars($member['position']); ?>
                  </p>

                  <?php if (!empty($member['linkedin_url']) || !empty($member['twitter_url'])): ?>
                    <div class="tm-social">
                      <?php if (!empty($member['linkedin_url'])): ?>
                        <a href="<?php echo htmlspecialchars($member['linkedin_url'], ENT_QUOTES); ?>"
                           target="_blank" rel="noopener noreferrer"
                           aria-label="<?php echo htmlspecialchars($member['name']); ?> on LinkedIn">
                          <i class="fab fa-linkedin-in"></i>
                        </a>
                      <?php endif; ?>

                      <?php if (!empty($member['twitter_url'])): ?>
                        <a href="<?php echo htmlspecialchars($member['twitter_url'], ENT_QUOTES); ?>"
                           target="_blank" rel="noopener noreferrer"
                           aria-label="<?php echo htmlspecialchars($member['name']); ?> on Twitter">
                          <i class="fab fa-x-twitter"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>

              </div>
            <?php endforeach; ?>
          </div>
        </div>

      <?php endforeach; ?>

    <?php endif; ?>

  </div>
</section>

<script>
/* Scroll-reveal */
(function () {
  var cards = document.querySelectorAll('.tm-card');
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
</script>

<?php include 'footer.php'; ?>