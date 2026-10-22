<?php
/**
 * Site popups: admin-managed announcements shown as a modal on the public site.
 * The table is created on first use so no manual migration is needed.
 */

const POPUP_FREQUENCIES = [
    'every_visit'      => 'Every page view',
    'once_per_session' => 'Once per browser session',
    'once_per_day'     => 'Once per day',
    'once'             => 'Only once per visitor',
];

// Public pages a popup can be limited to (file => label).
const POPUP_PAGES = [
    'index.php'         => 'Home',
    'about-us.php'      => 'About Us',
    'programmes.php'    => 'Programmes',
    'focus-areas.php'   => 'Focus Areas',
    'our-impact.php'    => 'Our Impact',
    'opportunities.php' => 'Opportunities',
    'jobs.php'          => 'Jobs',
    'learning.php'      => 'Learning',
    'blog.php'          => 'News & Blog',
    'newsletters.php'   => 'Newsletters',
    'annual-reports.php'=> 'Annual Reports',
    'volunteer.php'     => 'Volunteer',
    'partner.php'       => 'Partner',
    'hire-graduate.php' => 'Hire Graduate',
    'donate.php'        => 'Donate',
    'contact.php'       => 'Contact',
];

const POPUP_ALLOWED_TAGS = '<p><br><strong><b><em><i><u><a><ul><ol><li><h3><h4>';

function ensurePopupTable(mysqli $conn): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS site_popups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NULL,
        image VARCHAR(255) NOT NULL DEFAULT '',
        button_text VARCHAR(100) NOT NULL DEFAULT '',
        button_url VARCHAR(500) NOT NULL DEFAULT '',
        button_new_tab TINYINT(1) NOT NULL DEFAULT 0,
        pages TEXT NULL,
        delay_seconds INT NOT NULL DEFAULT 3,
        frequency VARCHAR(30) NOT NULL DEFAULT 'once_per_session',
        start_at DATETIME NULL,
        end_at DATETIME NULL,
        priority INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function popupSanitizeContent(string $html): string
{
    $html = strip_tags($html, POPUP_ALLOWED_TAGS);
    // Drop inline event handlers, styles and javascript: links left on allowed tags.
    $html = preg_replace('/\s+(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    return preg_replace('/href\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\1/i', 'href="#"', $html);
}

/** Highest-priority active, in-schedule popup targeting this page, or null. */
function getActivePopup(mysqli $conn, string $page_file): ?array
{
    ensurePopupTable($conn);

    $res = $conn->query("SELECT * FROM site_popups
        WHERE is_active = 1
          AND (start_at IS NULL OR start_at <= NOW())
          AND (end_at IS NULL OR end_at >= NOW())
        ORDER BY priority DESC, updated_at DESC");
    if (!$res) return null;

    $page_file = $page_file === '' ? 'index.php' : $page_file;
    while ($row = $res->fetch_assoc()) {
        $pages = array_filter(array_map('trim', explode(',', (string)$row['pages'])));
        if (!$pages || in_array($page_file, $pages, true)) {
            return $row;
        }
    }
    return null;
}

function renderSitePopup(mysqli $conn, string $page_file): void
{
    $popup = getActivePopup($conn, $page_file);
    if (!$popup) return;

    $id        = (int)$popup['id'];
    $version   = $id . '-' . strtotime((string)$popup['updated_at']);
    $frequency = array_key_exists($popup['frequency'], POPUP_FREQUENCIES) ? $popup['frequency'] : 'once_per_session';
    $image     = $popup['image'] !== '' ? 'uploads/popups/' . $popup['image'] : '';
    ?>
<link rel="stylesheet" href="<?= htmlspecialchars(versionedAsset('assets/css/popup.css')) ?>">
<div class="site-popup" id="sitePopup" hidden
     role="dialog" aria-modal="true" aria-labelledby="sitePopupTitle"
     data-popup-version="<?= htmlspecialchars($version) ?>"
     data-popup-frequency="<?= htmlspecialchars($frequency) ?>"
     data-popup-delay="<?= max(0, (int)$popup['delay_seconds']) ?>">
    <div class="site-popup__backdrop" data-popup-close></div>
    <div class="site-popup__dialog<?= $image ? ' site-popup__dialog--image' : '' ?>">
        <button type="button" class="site-popup__close" data-popup-close aria-label="Close">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <?php if ($image): ?>
            <div class="site-popup__media">
                <img src="<?= htmlspecialchars($image) ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="site-popup__body">
            <h2 class="site-popup__title" id="sitePopupTitle"><?= htmlspecialchars($popup['title']) ?></h2>
            <?php if (trim((string)$popup['content']) !== ''): ?>
                <div class="site-popup__content"><?= popupSanitizeContent((string)$popup['content']) ?></div>
            <?php endif; ?>
            <?php if ($popup['button_text'] !== '' && $popup['button_url'] !== ''): ?>
                <a class="site-popup__button" href="<?= htmlspecialchars($popup['button_url']) ?>"
                   <?= $popup['button_new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                   data-popup-cta><?= htmlspecialchars($popup['button_text']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars(versionedAsset('assets/js/popup.js')) ?>" defer></script>
    <?php
}
