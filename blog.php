<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

// -- Hero image ------------------------------------------------
$hero = getHeroImage($conn, 'blog');

// -- Filters ---------------------------------------------------
$category_filter = trim($_GET['category'] ?? 'all');
$search          = trim($_GET['search']   ?? '');

// -- Build query -----------------------------------------------
$where  = ["status = 'published'"];
$params = [];
$types  = '';

if ($category_filter !== 'all') {
    $where[]  = "category = ?";
    $params[] = $category_filter;
    $types   .= 's';
}
if (!empty($search)) {
    $where[]  = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $s         = '%' . $search . '%';
    $params    = array_merge($params, [$s, $s, $s]);
    $types    .= 'sss';
}

$sql = "SELECT * FROM news_blog WHERE " . implode(' AND ', $where)
     . " ORDER BY published_at DESC, created_at DESC";

$posts = [];
if ($params) {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $posts[] = $r;
    $st->close();
} else {
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $posts[] = $r;
}

// -- Categories ------------------------------------------------
$categories = [];
$cr = $conn->query("SELECT DISTINCT category FROM news_blog WHERE status='published' AND category IS NOT NULL ORDER BY category ASC");
if ($cr) while ($row = $cr->fetch_assoc()) $categories[] = $row['category'];

$page_title       = 'News & Blog';
$meta_description = 'Stay updated with our latest news, stories, and insights.';
$body_class       = 'blog-page';

include 'header.php';
?>



<!--  HERO -->
<section class="bl-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="bl-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="bl-hero-slash" aria-hidden="true"></div>
  <div class="bl-hero-grain"  aria-hidden="true"></div>

  <div class="bl-wrap">
    <div class="bl-hero-inner">
      <p class="bl-eyebrow">News</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'News &amp; Blog'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="bl-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="bl-hero-sub">
          Stay updated with our latest news, stories, and insights.
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FILTER BAR  -->
<nav class="bl-filters" aria-label="Blog filters">
  <div class="bl-wrap">
    <form method="GET" class="bl-filters-inner">

      <!-- Search -->
      <div class="bl-search">
        <i class="fas fa-search bl-search-icon" aria-hidden="true"></i>
        <input type="text" name="search"
               placeholder="Search posts..."
               value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <button type="submit" class="bl-search-btn">
        <i class="fas fa-search" aria-hidden="true"></i> Search
      </button>

      <!-- Category chips -->
      <div class="bl-chips">
        <a href="?" class="bl-chip <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
          All
        </a>
        <?php foreach ($categories as $cat): ?>
          <a href="?category=<?php echo urlencode($cat); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
             class="bl-chip <?php echo $category_filter === $cat ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cat); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($search) || $category_filter !== 'all'): ?>
        <a href="blog.php" class="bl-chip clear">
          <i class="fas fa-times" style="font-size:10px;margin-right:3px;" aria-hidden="true"></i>Clear
        </a>
      <?php endif; ?>

    </form>
  </div>
</nav>

<!-- POSTS  -->
<section class="bl-section">
  <div class="bl-wrap">

    <div class="bl-section-head">
      <h2>
        <?php if (!empty($search)): ?>
          Results for "<?php echo htmlspecialchars($search); ?>"
        <?php elseif ($category_filter !== 'all'): ?>
          <?php echo htmlspecialchars($category_filter); ?>
        <?php else: ?>
          Latest Posts
        <?php endif; ?>
      </h2>
      <span class="bl-count">
        <?php echo count($posts); ?> post<?php echo count($posts) !== 1 ? 's' : ''; ?>
      </span>
    </div>

    <?php if (empty($posts)): ?>
      <div class="bl-empty">
        <i class="fas fa-newspaper" aria-hidden="true"></i>
        <h3>No Posts Found</h3>
        <p>
          <?php if (!empty($search)): ?>
            No posts match your search. <a href="blog.php">View all posts</a>
          <?php else: ?>
            Check back soon for new posts!
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <div class="bl-grid" id="blGrid">
        <?php foreach ($posts as $idx => $post):
          $delay = ($idx % 3) * 80;
        ?>
          <article class="bl-card" style="transition-delay:<?php echo $delay; ?>ms">

            <!-- Thumb -->
            <a class="bl-thumb"
               href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>"
               aria-label="<?php echo htmlspecialchars($post['title']); ?>">
              <?php if (!empty($post['featured_image'])): ?>
                <img src="uploads/blog/<?php echo htmlspecialchars($post['featured_image']); ?>"
                     alt="<?php echo htmlspecialchars($post['title']); ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="bl-thumb-placeholder">
                  <i class="fas fa-newspaper" aria-hidden="true"></i>
                </div>
              <?php endif; ?>

              <?php if (!empty($post['category'])): ?>
                <span class="bl-cat-pill">
                  <?php echo htmlspecialchars($post['category']); ?>
                </span>
              <?php endif; ?>
            </a>

            <!-- Body -->
            <div class="bl-card-body">
              <div class="bl-card-date">
                <i class="far fa-calendar" aria-hidden="true"></i>
                <?php echo formatDate($post['published_at'] ?: $post['created_at']); ?>
              </div>

              <h3 class="bl-card-title">
                <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>">
                  <?php echo htmlspecialchars($post['title']); ?>
                </a>
              </h3>

              <p class="bl-card-excerpt">
                <?php
                  $excerpt = !empty($post['excerpt'])
                      ? $post['excerpt']
                      : strip_tags($post['content'] ?? '');
                  echo htmlspecialchars(truncate($excerpt, 130));
                ?>
              </p>

              <a class="bl-read-more"
                 href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>">
                Read More
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
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

<script>
/* Scroll-reveal */
(function () {
  var cards = document.querySelectorAll('.bl-card');
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