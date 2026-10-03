<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// Hero image 
$hero = getHeroImage($conn, 'annual-reports');

// Reports 
$reports = [];
$res = $conn->query("SELECT * FROM annual_reports WHERE status='published' ORDER BY year DESC");
if ($res) while ($row = $res->fetch_assoc()) $reports[] = $row;

$page_title       = 'Annual Reports';
$meta_description = 'View and download our annual reports showcasing our achievements, financial transparency, and impact.';
$body_class       = 'annual-reports-page';

include 'header.php';
?>


<!-- HERO  -->
<section class="ar-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="ar-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="ar-hero-slash" aria-hidden="true"></div>
  <div class="ar-hero-grain"  aria-hidden="true"></div>

  <div class="ar-wrap">
    <div class="ar-hero-inner">
      <p class="ar-eyebrow">Reports</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'Annual Reports'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="ar-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="ar-hero-sub">
          Transparency and accountability through comprehensive annual reporting.
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!--  REPORTS  -->
<section class="ar-section">
  <div class="ar-wrap">

    <?php if (empty($reports)): ?>
      <div class="ar-empty">
        <i class="fas fa-file-alt" aria-hidden="true"></i>
        <h3>No Reports Available</h3>
        <p>Check back soon for our annual reports.</p>
      </div>
    <?php else: ?>
      <div class="ar-grid" id="arGrid">
        <?php foreach ($reports as $idx => $report):
          $id       = (int)$report['id'];
          $pdfUrl   = 'uploads/annual-reports/' . ($report['pdf_file'] ?? '');
          $coverUrl = !empty($report['cover_image'])
              ? 'uploads/annual-reports/covers/' . $report['cover_image']
              : '';
          $delay = ($idx % 4) * 80;
        ?>
          <div class="ar-card" style="position:relative;transition-delay:<?php echo $delay; ?>ms">

            <!-- Cover -->
            <div class="ar-cover">
              <?php if ($coverUrl): ?>
                <img src="<?php echo htmlspecialchars($coverUrl); ?>"
                     alt="<?php echo htmlspecialchars($report['title']); ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="ar-cover-placeholder">
                  <i class="fas fa-file-pdf" aria-hidden="true"></i>
                  <span><?php echo htmlspecialchars($report['year']); ?></span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="ar-card-body">
              <span class="ar-year-badge"><?php echo htmlspecialchars($report['year']); ?></span>
              <h3 class="ar-card-title"><?php echo htmlspecialchars($report['title']); ?></h3>

              <?php if (!empty($report['description'])): ?>
                <p class="ar-card-desc">
                  <?php echo htmlspecialchars(truncate($report['description'], 110)); ?>
                </p>
              <?php endif; ?>

              <!-- Meta -->
              <div class="ar-meta">
                <span>
                  <i class="fas fa-file-pdf" style="color:#ef4444;" aria-hidden="true"></i>
                  PDF
                </span>
                <span>
                  <i class="fas fa-database" aria-hidden="true"></i>
                  <?php echo htmlspecialchars(formatFileSize($report['file_size'] ?? 0)); ?>
                </span>
                <span>
                  <i class="fas fa-download" aria-hidden="true"></i>
                  <span class="dl-count" data-report-id="<?php echo $id; ?>">
                    <?php echo number_format((int)($report['download_count'] ?? 0)); ?>
                  </span>
                </span>
              </div>

              <!-- Actions -->
              <div class="ar-actions">
                <a class="ar-btn ar-btn-view"
                   href="<?php echo htmlspecialchars($pdfUrl); ?>"
                   target="_blank" rel="noopener" title="Open PDF">
                  <i class="fas fa-external-link-alt" aria-hidden="true"></i> View
                </a>
                <a class="ar-btn ar-btn-dl track-download"
                   href="download.php?type=annual-report&id=<?php echo $id; ?>"
                   data-report-id="<?php echo $id; ?>"
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
/*  Scroll-reveal  */
(function () {
  var cards = document.querySelectorAll('.ar-card');
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

/*  Download-count refresh  */
function fmtNum(n) {
  return (n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

async function fetchCount(id) {
  try {
    var res  = await fetch('annual_report_stats.php?id=' + encodeURIComponent(id), { cache: 'no-store' });
    var data = await res.json();
    return data.success ? data.download_count : null;
  } catch (e) { return null; }
}

async function refreshAll() {
  var nodes = document.querySelectorAll('.dl-count[data-report-id]');
  var ids   = [...new Set(Array.from(nodes).map(function (n) { return n.dataset.reportId; }))];
  for (var i = 0; i < ids.length; i++) {
    var count = await fetchCount(ids[i]);
    if (count === null) continue;
    document.querySelectorAll('.dl-count[data-report-id="' + ids[i] + '"]')
      .forEach(function (el) { el.textContent = fmtNum(count); });
  }
}

// Refresh after download click
document.addEventListener('click', function (e) {
  var a = e.target.closest('a.track-download');
  if (!a) return;
  var id = a.dataset.reportId;
  if (!id) return;
  setTimeout(async function () {
    var count = await fetchCount(id);
    if (count === null) return;
    document.querySelectorAll('.dl-count[data-report-id="' + id + '"]')
      .forEach(function (el) { el.textContent = fmtNum(count); });
  }, 1200);
});

// Poll every 20 s + run on load
setInterval(refreshAll, 20000);
document.addEventListener('DOMContentLoaded', refreshAll);
</script>

<?php include 'footer.php'; ?>