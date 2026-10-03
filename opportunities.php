<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// Main page settings
$opportunity = null;
$res = $conn->query("SELECT * FROM opportunities WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($res) $opportunity = $res->fetch_assoc();

$default = [
  'headline'        => "If you're ready to live a life set on your own terms.\nWITU is ready to give you the tools.\nLet's make it happen.",
  'intro'           => '',
  'framework_intro' => 'Your opportunity through WITU is supported by the following focus areas:',
  'featured_image'  => ''
];
$opportunity = $opportunity ? array_merge($default, $opportunity) : $default;

// Sections
$sections = [];
$res = $conn->query("SELECT * FROM opportunity_sections WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if ($res) while ($row = $res->fetch_assoc()) $sections[] = $row;

$page_title = 'Opportunities';
include 'header.php';

$heroBg = '';
if (!empty($opportunity['featured_image'])) {
  $heroBg = 'uploads/opportunities/' . $opportunity['featured_image'];
}
?>



<style>
/* -- Tokens ------------------------------------------------ */
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
  --ff-head  : Arial, Helvetica, sans-serif;
  --ff-body  : Arial, Helvetica, sans-serif;
  --ease     : cubic-bezier(.22,.61,.36,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--ff-body); color: var(--dark); background: #fff; -webkit-font-smoothing: antialiased; }

.opp-container { width: min(1180px, 92vw); margin-inline: auto; }

/* -- HERO ---------------------------------------------------- */
.opp-hero {
  position: relative;
  min-height: 92vh;
  display: flex;
  align-items: center;
  background: var(--charcoal);
  overflow: hidden;
}

.opp-hero.has-bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: var(--hero-bg);
  background-size: cover;
  background-position: center;
  filter: brightness(.35) saturate(.55);
  transform: scale(1.05);
  animation: heroZoom 10s var(--ease) forwards;
}
@keyframes heroZoom {
  to { transform: scale(1.0); }
}

/* Diagonal accent slash */
.opp-hero::after {
  content: '';
  position: absolute;
  right: -60px; top: 0; bottom: 0;
  width: 38vw;
  background: var(--orange);
  opacity: .06;
  clip-path: polygon(28% 0, 100% 0, 100% 100%, 0% 100%);
  pointer-events: none;
}

/* Noise grain */
.opp-grain {
  position: absolute;
  inset: 0;
  opacity: .04;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  pointer-events: none;
}

.opp-hero-inner {
  position: relative;
  z-index: 2;
  padding: 120px 0 110px;
}

.opp-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--orange);
  margin-bottom: 28px;
  opacity: 0;
  animation: fadeUp .7s var(--ease) .1s forwards;
}
.opp-eyebrow::before {
  content: '';
  display: block;
  width: 30px;
  height: 2px;
  background: var(--orange);
}

.opp-hero h1 {
  font-family: var(--ff-head);
  font-size: clamp(2.4rem, 5.5vw, 4.8rem);
  font-weight: 900;
  line-height: 1.1;
  color: #fff;
  max-width: 840px;
  opacity: 0;
  animation: fadeUp .9s var(--ease) .25s forwards;
}
.opp-hero h1 em { font-style: normal; color: var(--orange); }

.opp-hero-intro {
  margin-top: 28px;
  font-size: 1.12rem;
  font-weight: 300;
  line-height: 1.72;
  color: rgba(255,255,255,.7);
  max-width: 560px;
  opacity: 0;
  animation: fadeUp .9s var(--ease) .42s forwards;
}

.opp-hero-cta {
  margin-top: 46px;
  opacity: 0;
  animation: fadeUp .9s var(--ease) .58s forwards;
}
.opp-hero-cta a {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--orange);
  color: #fff;
  font-size: .9rem;
  font-weight: 500;
  letter-spacing: .05em;
  text-decoration: none;
  padding: 16px 36px;
  border-radius: 4px;
  transition: background .22s, transform .22s;
}
.opp-hero-cta a:hover { background: var(--orange-lt); transform: translateY(-2px); }
.opp-hero-cta a svg { transition: transform .22s; }
.opp-hero-cta a:hover svg { transform: translateX(5px); }

.opp-scroll-hint {
  position: absolute;
  bottom: 34px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: rgba(255,255,255,.3);
  font-size: 9.5px;
  letter-spacing: .18em;
  text-transform: uppercase;
  z-index: 3;
  animation: floatY 2.8s ease-in-out infinite;
}
.opp-scroll-hint i {
  display: block;
  width: 1px;
  height: 46px;
  background: linear-gradient(to bottom, rgba(255,255,255,.3), transparent);
}

/* -- ORANGE BAND --------------------------------------------- */
.opp-band {
  background: var(--orange);
  overflow: hidden;
}
.opp-band-inner {
  display: flex;
  align-items: stretch;
}
.opp-band-text {
  padding: 54px 48px 54px 0;
  flex: 1;
}
.opp-band-kicker {
  font-size: 10px;
  font-weight: 500;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: rgba(255,255,255,.6);
  margin-bottom: 14px;
}
.opp-band-text h2 {
  font-family: var(--ff-head);
  font-size: clamp(1.5rem, 2.6vw, 2.4rem);
  font-weight: 700;
  color: #fff;
  line-height: 1.2;
}
.opp-band-text p {
  margin-top: 14px;
  font-size: .98rem;
  font-weight: 300;
  color: rgba(255,255,255,.82);
  line-height: 1.68;
  max-width: 540px;
}
.opp-band-icon {
  flex-shrink: 0;
  width: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,.08);
}
.opp-band-icon svg { opacity: .22; }

/* -- FRAMEWORK ----------------------------------------------- */
.opp-framework {
  background: var(--cream);
  padding: 100px 0 116px;
  position: relative;
  overflow: hidden;
}
.opp-fw-bignumber {
  position: absolute;
  top: 10px;
  right: 3vw;
  font-family: var(--ff-head);
  font-size: clamp(110px, 20vw, 260px);
  font-weight: 900;
  color: rgba(0,0,0,.028);
  line-height: 1;
  pointer-events: none;
  user-select: none;
}

.opp-section-kicker {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--orange);
  margin-bottom: 18px;
}
.opp-section-kicker::before {
  content: '';
  display: block;
  width: 28px;
  height: 2px;
  background: var(--orange);
}

.opp-fw-head {
  margin-bottom: 68px;
}
.opp-fw-head h2 {
  font-family: var(--ff-head);
  font-size: clamp(2rem, 3.8vw, 3.3rem);
  font-weight: 900;
  color: var(--dark);
  line-height: 1.1;
  max-width: 580px;
  margin-bottom: 16px;
}
.opp-fw-head p {
  font-size: 1.02rem;
  font-weight: 300;
  color: var(--muted);
  max-width: 500px;
  line-height: 1.72;
}

/* Grid */
.opp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
  gap: 22px;
}

.opp-card {
  background: var(--white);
  border-radius: 6px;
  border: 1px solid var(--border);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;

  /* Entrance */
  opacity: 0;
  transform: translateY(26px);
  transition: opacity .65s var(--ease), transform .65s var(--ease), box-shadow .28s var(--ease);
}
.opp-card.in-view {
  opacity: 1;
  transform: translateY(0);
}
.opp-card:hover {
  box-shadow: 0 22px 54px rgba(0,0,0,.09);
  transform: translateY(-5px);
}
.opp-card.in-view:hover { transform: translateY(-5px); }

/* Orange top line */
.opp-card-line {
  height: 3px;
  background: var(--orange);
  transform-origin: left;
  transform: scaleX(0);
  transition: transform .5s var(--ease);
  flex-shrink: 0;
}
.opp-card.in-view .opp-card-line,
.opp-card:hover .opp-card-line { transform: scaleX(1); }

/* Section image */
.opp-card-img {
  width: 100%;
  aspect-ratio: 16/9;
  object-fit: cover;
  display: block;
  filter: grayscale(.15);
  transition: filter .4s;
}
.opp-card:hover .opp-card-img { filter: grayscale(0); }

.opp-card-body {
  padding: 28px 28px 34px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.opp-card-num {
  font-family: var(--ff-head);
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .2em;
  color: var(--orange);
  text-transform: uppercase;
  margin-bottom: 10px;
}

.opp-card-body h3 {
  font-family: var(--ff-head);
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--dark);
  line-height: 1.25;
  margin-bottom: 12px;
}

.opp-card-body .subtext {
  font-size: .94rem;
  font-weight: 300;
  line-height: 1.76;
  color: var(--mid);
}
.opp-card-body .subtext + .subtext { margin-top: 10px; }

.opp-card-footer {
  padding: 0 28px 26px;
  flex-shrink: 0;
}
.opp-learn-more {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--orange);
  color: #fff;
  font-family: var(--ff-body);
  font-size: .82rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  text-decoration: none;
  padding: 10px 20px;
  border-radius: 4px;
  transition: background .2s, transform .2s;
}
.opp-learn-more:hover { background: var(--orange-lt); transform: translateY(-2px); }
.opp-learn-more svg { transition: transform .2s; }
.opp-learn-more:hover svg { transform: translateX(4px); }

/* -- CTA ----------------------------------------------------- */
.opp-cta {
  background: var(--dark);
  padding: 96px 0;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.opp-cta::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 65% 55% at 50% 130%, rgba(248,101,5,.18), transparent);
}
.opp-cta h2 {
  position: relative;
  font-family: var(--ff-head);
  font-size: clamp(2rem, 4vw, 3.4rem);
  font-weight: 900;
  color: #fff;
  line-height: 1.15;
  margin-bottom: 20px;
}
.opp-cta h2 em { font-style: normal; color: var(--orange); }
.opp-cta p {
  position: relative;
  font-size: 1.02rem;
  font-weight: 300;
  color: rgba(255,255,255,.58);
  max-width: 460px;
  margin: 0 auto 38px;
  line-height: 1.72;
}
.opp-cta-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border: 1.5px solid var(--orange);
  color: var(--orange);
  font-size: .9rem;
  font-weight: 500;
  letter-spacing: .06em;
  text-decoration: none;
  padding: 15px 38px;
  border-radius: 4px;
  transition: background .22s, color .22s, transform .22s;
}
.opp-cta-btn:hover { background: var(--orange); color: #fff; transform: translateY(-2px); }

/* -- Animations ---------------------------------------------- */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(22px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes floatY {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50%       { transform: translateX(-50%) translateY(9px); }
}

/* -- Responsive ---------------------------------------------- */
@media (max-width: 768px) {
  .opp-hero-inner  { padding: 88px 0 78px; }
  .opp-band-inner  { flex-direction: column; }
  .opp-band-text   { padding: 38px 0; }
  .opp-band-icon   { display: none; }
  .opp-grid        { grid-template-columns: 1fr; }
  .opp-framework   { padding: 64px 0 80px; }
  .opp-cta         { padding: 70px 0; }
}
</style>

<!-- -- HERO -------------------------------------------------- -->
<section
  class="opp-hero <?php echo $heroBg ? 'has-bg' : ''; ?>"
  <?php if ($heroBg): ?>style="--hero-bg: url('<?php echo htmlspecialchars($heroBg, ENT_QUOTES); ?>')"<?php endif; ?>
>
  <div class="opp-grain" aria-hidden="true"></div>

  <div class="opp-container">
    <div class="opp-hero-inner">

      <p class="opp-eyebrow">Your Future Starts Here</p>

      <h1><?php echo nl2br(htmlspecialchars($opportunity['headline'])); ?></h1>

      <?php if (!empty(trim($opportunity['intro']))): ?>
        <p class="opp-hero-intro"><?php echo nl2br(htmlspecialchars($opportunity['intro'])); ?></p>
      <?php endif; ?>

      <div class="opp-hero-cta">
        <a href="#framework">
          Explore Opportunities
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

    </div>
  </div>

  <div class="opp-scroll-hint" aria-hidden="true">
    <i></i>Scroll
  </div>
</section>

<!-- -- BAND -------------------------------------------------- -->
<div class="opp-band">
  <div class="opp-container">
    <div class="opp-band-inner">
      <div class="opp-band-text">
        <p class="opp-band-kicker">Opportunity Support Framework</p>
        <h2>Tools, Resources &amp; Community<br>Built Around You</h2>
        <?php if (!empty(trim($opportunity['framework_intro']))): ?>
          <p><?php echo htmlspecialchars($opportunity['framework_intro']); ?></p>
        <?php endif; ?>
      </div>
      <div class="opp-band-icon" aria-hidden="true">
        <svg width="88" height="88" viewBox="0 0 88 88" fill="none" stroke="white" stroke-width="1.5">
          <circle cx="44" cy="44" r="40"/>
          <path d="M44 16v56M16 44h56"/>
          <circle cx="44" cy="44" r="12" fill="white" opacity=".5"/>
        </svg>
      </div>
    </div>
  </div>
</div>

<!-- -- FRAMEWORK SECTIONS ------------------------------------ -->
<section class="opp-framework" id="framework">
  <div class="opp-fw-bignumber" aria-hidden="true"><?php echo count($sections) ?: ''; ?></div>

  <div class="opp-container">
    <div class="opp-fw-head">
      <span class="opp-section-kicker">Focus Areas</span>
      <h2>How WITU Supports<br>Your Journey</h2>
      <p>Each focus area is designed to give you a concrete advantage - whether you're just starting out or scaling up.</p>
    </div>

    <?php if (empty($sections)): ?>
      <div style="text-align:center;padding:60px 0;color:var(--muted);">
        <p>No opportunity sections found. Please add them in Admin.</p>
      </div>
    <?php else: ?>
      <div class="opp-grid" id="oppGrid">
        <?php foreach ($sections as $idx => $s): ?>
          <?php
            // Build raw text from whichever content field is populated
            $rawText = '';
            if (!empty(trim($s['subtext_1'] ?? '')))      $rawText = $s['subtext_1'];
            elseif (!empty(trim($s['subtext_2'] ?? '')))  $rawText = $s['subtext_2'];
            elseif (!empty(trim($s['subtext']  ?? '')))   $rawText = $s['subtext'];
            elseif (!empty(trim($s['content']  ?? '')))   $rawText = $s['content'];

            // Truncate to 12 words
            $words    = preg_split('/\s+/', strip_tags($rawText), -1, PREG_SPLIT_NO_EMPTY);
            $preview  = implode(' ', array_slice($words, 0, 12));
            if (count($words) > 12) $preview .= '...';

            $num   = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
            $delay = ($idx % 3) * 90;

            // Link to the detail page
            $learnUrl = 'opportunity-detail.php?id=' . (int)$s['id'];
          ?>
          <div class="opp-card" style="transition-delay:<?php echo $delay; ?>ms">
            <div class="opp-card-line" aria-hidden="true"></div>

            <?php if (!empty($s['section_image'])): ?>
              <img
                class="opp-card-img"
                src="uploads/opportunities/<?php echo htmlspecialchars($s['section_image']); ?>"
                alt="<?php echo htmlspecialchars($s['title'] ?? ''); ?>"
                loading="lazy"
              >
            <?php endif; ?>

            <div class="opp-card-body">
              <span class="opp-card-num">Area <?php echo $num; ?></span>
              <h3><?php echo htmlspecialchars($s['title'] ?? ''); ?></h3>
              <?php if ($preview !== ''): ?>
                <p class="subtext"><?php echo htmlspecialchars($preview); ?></p>
              <?php endif; ?>
            </div>

            <div class="opp-card-footer">
              <a class="opp-learn-more" href="<?php echo $learnUrl; ?>">
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

<!-- -- CTA ---------------------------------------------------- -->
<section class="opp-cta">
  <div class="opp-container">
    <h2>Ready to take the<br><em>next step?</em></h2>
    <p>Join the WITU community and access every resource built to move you forward.</p>
    <a href="contact.php" class="opp-cta-btn">
      Get In Touch
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    </a>
  </div>
</section>

<script>
/* Scroll-reveal cards */
(function () {
  var cards = document.querySelectorAll('.opp-card');
  if (!cards.length || !('IntersectionObserver' in window)) {
    cards.forEach(function(c){ c.classList.add('in-view'); });
    return;
  }
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, { threshold: 0.1 });
  cards.forEach(function(c){ io.observe(c); });
})();

/* Smooth anchor */
document.querySelectorAll('a[href="#framework"]').forEach(function(a){
  a.addEventListener('click', function(e){
    e.preventDefault();
    var el = document.getElementById('framework');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});
</script>

<?php include 'footer.php'; ?>