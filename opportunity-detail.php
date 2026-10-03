<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// -- Validate ID ----------------------------------------------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: opportunities.php");
    exit;
}

// -- Fetch this section ---------------------------------------
$section = null;
$st = $conn->prepare("SELECT * FROM opportunity_sections WHERE id = ? AND is_active = 1 LIMIT 1");
$st->bind_param("i", $id);
$st->execute();
$section = $st->get_result()->fetch_assoc();
$st->close();

if (!$section) {
    header("Location: opportunities.php");
    exit;
}

// -- Prev / Next for navigation -------------------------------
$prev = $next = null;

$rp = $conn->prepare("SELECT id, title FROM opportunity_sections WHERE is_active=1 AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC LIMIT 1");
$rp->bind_param("iii", $section['sort_order'], $section['sort_order'], $id);
$rp->execute();
$prev = $rp->get_result()->fetch_assoc();
$rp->close();

$rn = $conn->prepare("SELECT id, title FROM opportunity_sections WHERE is_active=1 AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order ASC, id ASC LIMIT 1");
$rn->bind_param("iii", $section['sort_order'], $section['sort_order'], $id);
$rn->execute();
$next = $rn->get_result()->fetch_assoc();
$rn->close();

// -- Resolve content ------------------------------------------
$content = '';
if (!empty(trim($section['content']   ?? ''))) $content = $section['content'];
elseif (!empty(trim($section['subtext_1'] ?? ''))) {
    $content = $section['subtext_1'];
    if (!empty(trim($section['subtext_2'] ?? ''))) $content .= "\n\n" . $section['subtext_2'];
} elseif (!empty(trim($section['subtext'] ?? ''))) {
    $content = $section['subtext'];
}

$page_title = htmlspecialchars($section['title']) . ' - Opportunities';
include 'header.php';

$imgSrc = !empty($section['section_image'])
    ? 'uploads/opportunities/' . $section['section_image']
    : '';
?>


<!-- -- Hero ----------------------------------------------------- -->
<section class="od-hero">
  <div class="od-container">
    <div class="od-hero-inner">
      <p class="od-hero-kicker">Opportunity Area <?php echo str_pad((int)($section['sort_order'] + 1), 2, '0', STR_PAD_LEFT); ?></p>
      <h1><?php echo htmlspecialchars($section['title']); ?></h1>
    </div>
  </div>
</section>

<!-- -- Body ----------------------------------------------------- -->
<div class="od-body">
  <div class="od-container">

    <div class="od-layout <?php echo $imgSrc ? 'has-image' : ''; ?>">

      <!-- Content -->
      <div class="od-content-area">
        <span class="od-section-label">Overview</span>
        <div class="od-content">
          <?php
            // Split on double newlines = paragraphs; single newlines = <br>
            $paragraphs = preg_split('/\n{2,}/', trim($content));
            foreach ($paragraphs as $para):
              $para = trim($para);
              if ($para === '') continue;
          ?>
            <p><?php echo nl2br(htmlspecialchars($para)); ?></p>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Image (right column, sticky) -->
      <?php if ($imgSrc): ?>
        <div class="od-image-wrap">
          <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES); ?>"
               alt="<?php echo htmlspecialchars($section['title']); ?>">
        </div>
      <?php endif; ?>

    </div><!-- /.od-layout -->

    <div class="od-divider"></div>

    <!-- Prev / Next -->
    <?php if ($prev || $next): ?>
      <nav class="od-nav" aria-label="Section navigation">

        <?php if ($prev): ?>
          <a class="od-nav-card prev" href="opportunity-detail.php?id=<?php echo (int)$prev['id']; ?>">
            <span class="od-nav-dir">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
              Previous
            </span>
            <span class="od-nav-title"><?php echo htmlspecialchars($prev['title']); ?></span>
          </a>
        <?php else: ?>
          <div></div><!-- empty cell keeps grid aligned -->
        <?php endif; ?>

        <?php if ($next): ?>
          <a class="od-nav-card next" href="opportunity-detail.php?id=<?php echo (int)$next['id']; ?>">
            <span class="od-nav-dir">
              Next
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </span>
            <span class="od-nav-title"><?php echo htmlspecialchars($next['title']); ?></span>
          </a>
        <?php endif; ?>

      </nav>
    <?php endif; ?>

    <!-- Back -->
    <div class="od-back-wrap">
      <a class="od-back" href="opportunities.php">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to All Opportunities
      </a>
    </div>

  </div>
</div>

<?php include 'footer.php'; ?>