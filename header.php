<?php

// Only resume an existing session: a new one for every anonymous visitor adds a
// cookie and no-store headers that keep pages out of the back/forward cache.
if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
    session_start();
}

if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
    header('Cache-Control: private, no-cache');
}

if (!isset($conn)) {
    require_once __DIR__ . '/config/db.php';
}
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/core/helpers.php';
}

$site_name       = getSetting($conn, 'site_name', '');
$site_tagline    = getSetting($conn, 'site_tagline', '');
$site_logo       = getSetting($conn, 'site_logo', '');
$site_favicon    = getSetting($conn, 'site_favicon', '');
$primary_color   = getSetting($conn, 'primary_color', '#663201');
$secondary_color = getSetting($conn, 'secondary_color', '#fd6501');

$page_title         = $page_title ?? 'Page';
$meta_description   = $meta_description ?? getSetting($conn, 'site_description', '');
$body_class         = $body_class ?? '';
$additional_head    = $additional_head ?? '';
$additional_scripts = $additional_scripts ?? '';

$main_pages = [];
$res = $conn->query("
    SELECT id, title, slug, menu_order
    FROM pages
    WHERE show_in_menu = 1
      AND status = 'published'
      AND (parent_id IS NULL OR parent_id = 0)
    ORDER BY menu_order ASC, id ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $main_pages[] = $row;
    }
}

$path          = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$current_file  = basename($path);
$current_slug  = $_GET['slug'] ?? '';

function isActiveFile(string $file, string $current_file): string {
    return $current_file === $file ? 'active' : '';
}

function isActiveContains(string $needle, string $path): string {
    return strpos($path, $needle) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($site_name) ?></title>
  <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta name="author" content="<?= htmlspecialchars($site_name) ?>">

  <meta property="og:title" content="<?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($site_name) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= function_exists('getCurrentURL') ? getCurrentURL() : '' ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($site_name) ?>">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">

  <?php if (!empty($site_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($site_favicon) ?>">
  <?php endif; ?>

  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
  <link rel="stylesheet" href="<?= htmlspecialchars(versionedAsset('assets/css/site.css')) ?>">

  <style>
    :root {
      --site-primary-color: <?= htmlspecialchars($primary_color, ENT_QUOTES) ?>;
      --site-secondary-color: <?= htmlspecialchars($secondary_color, ENT_QUOTES) ?>;
    }

    
  </style>

  <?= $additional_head; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(versionedAsset('assets/css/frontend-refinements.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versionedAsset('assets/css/accessibility.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versionedAsset('assets/css/responsive.css')) ?>">
</head>

<body class="<?= htmlspecialchars($body_class) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>

<header class="site-header">
  <div class="container">
    <div class="header-content">

      <a href="index.php" class="logo" aria-label="<?= htmlspecialchars($site_name ?: 'WITU', ENT_QUOTES) ?> home">
        <?php $logo_src = siteLogoUrl($conn); $logo_dims = imgDimensions($logo_src); ?>
        <img src="<?= htmlspecialchars($logo_src) ?>" alt="<?= htmlspecialchars($site_name ?: 'WITU') ?>"<?php if ($logo_dims): ?> width="<?= $logo_dims[0] ?>" height="<?= $logo_dims[1] ?>"<?php endif; ?>>
      </a>

      <nav class="main-nav" aria-label="Primary Navigation">
        <button type="button"
                class="mobile-menu-toggle"
                id="mobileMenuBtn"
                aria-label="Toggle Menu"
                aria-controls="navMenu"
                aria-expanded="false">
          <i class="fas fa-bars" aria-hidden="true"></i>
        </button>

        <ul class="nav-menu" id="navMenu">
          <li class="nav-mobile-close">
            <button type="button" class="mobile-menu-close" id="mobileCloseBtn" aria-label="Close Menu">
              <i class="fas fa-times" aria-hidden="true"></i>
            </button>
          </li>

          <li class="dropdown">
            <button type="button" class="dropdown-toggle" onclick="toggleDropdown(this)">About</button>
            <div class="dropdown-menu">
              <a href="about-us.php">About Us</a>
              <a href="opportunities.php">Opportunities</a>
            </div>
          </li>

          <li class="dropdown">
            <button type="button" class="dropdown-toggle" onclick="toggleDropdown(this)">Impact</button>
            <div class="dropdown-menu">
              <a href="our-impact.php">Overview</a>
              <a href="annual-reports.php">Annual Reports</a>
            </div>
          </li>

          <?php foreach ($main_pages as $page): ?>
            <li>
              <a href="page.php?slug=<?= urlencode($page['slug']) ?>"
                 class="<?= ($current_slug === $page['slug']) ? 'active' : '' ?>">
                <?= htmlspecialchars($page['title']) ?>
              </a>
            </li>
          <?php endforeach; ?>

          <li><a href="programmes.php" class="<?= isActiveContains('programme', $path) ?>">Programmes</a></li>

          <li>
            <a href="https://www.elevateher360.org/"
               class="<?= isActiveContains('learning.php', $path) ?>">
              Learning
            </a>
          </li>

          <li class="dropdown">
            <button type="button" class="dropdown-toggle" onclick="toggleDropdown(this)">Get Involved</button>
            <div class="dropdown-menu">
             <!-- <a href="donate.php">Donate</a> -->
              <a href="volunteer.php">Volunteer</a>
              <a href="partner.php">Partner</a>
              <a href="hire-graduate.php">Hire Graduate</a>
            </div>
          </li>

         

          <li>
            <a href="contact.php" class="<?= isActiveContains('contact.php', $path) ?>">
              Contact
            </a>
          </li>
          
           <li class="support-cause-item">
            <a href="donate.php" class="support-cause-link">
         
              Support Our Cause
            </a>
          </li>
        </ul>
      </nav>

    </div>
  </div>
</header>

<main class="site-main" id="main-content" tabindex="-1">

<script>
(function () {
  'use strict';

  const navMenu  = document.getElementById('navMenu');
  const overlay  = document.getElementById('mobileOverlay');
  const openBtn  = document.getElementById('mobileMenuBtn');
  const closeBtn = document.getElementById('mobileCloseBtn');

  if (!navMenu) return;
  document.querySelectorAll('.dropdown').forEach(function (dropdown, index) {
    const button = dropdown.querySelector('.dropdown-toggle');
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!button || !menu) return;
    menu.id = 'nav-dropdown-' + index;
    button.setAttribute('aria-controls', menu.id);
    button.setAttribute('aria-expanded', 'false');
  });

  function lockBody(lock) {
    document.body.style.overflow = lock ? 'hidden' : 'auto';
  }

  function closeAll() {
    document.querySelectorAll('.dropdown.active').forEach(function (d) {
      d.classList.remove('active');
      const button = d.querySelector('.dropdown-toggle');
      if (button) button.setAttribute('aria-expanded', 'false');
    });

    document.querySelectorAll('.mega-menu-item.active').forEach(function (m) {
      m.classList.remove('active');
    });
  }

  function openMobile() {
    navMenu.classList.add('active');

    if (overlay) {
      overlay.classList.add('active');
    }

    lockBody(true);
    if (closeBtn) closeBtn.focus();

    if (openBtn) {
      openBtn.setAttribute('aria-expanded', 'true');
    }
  }

  function closeMobile(restoreFocus) {
    navMenu.classList.remove('active');

    if (overlay) {
      overlay.classList.remove('active');
    }

    lockBody(false);

    if (openBtn) {
      openBtn.setAttribute('aria-expanded', 'false');
    }

    closeAll();
    if (restoreFocus && openBtn) openBtn.focus();
  }

  function toggleMobile() {
    navMenu.classList.contains('active') ? closeMobile() : openMobile();
  }

  window.closeMobileMenu = closeMobile;
  window.toggleMobileMenu = toggleMobile;

  window.toggleDropdown = function (btn) {
    const dropdown = btn && btn.closest('.dropdown');

    if (!dropdown) return;

    if (window.innerWidth <= 968) {
      document.querySelectorAll('.dropdown').forEach(function (d) {
        if (d !== dropdown) {
          d.classList.remove('active');
          const button = d.querySelector('.dropdown-toggle');
          if (button) button.setAttribute('aria-expanded', 'false');
        }
      });
    }

    dropdown.classList.toggle('active');
    btn.setAttribute('aria-expanded', dropdown.classList.contains('active') ? 'true' : 'false');
  };

  function bindMegaMenu() {
    document.querySelectorAll('.mega-menu-item > .nav-link, .mega-menu-item > button').forEach(function (link) {
      if (link.dataset.bound === '1') return;

      link.dataset.bound = '1';

      link.addEventListener('click', function (e) {
        if (window.innerWidth > 968) return;

        e.preventDefault();

        const li = this.closest('.mega-menu-item');

        if (!li) return;

        document.querySelectorAll('.mega-menu-item').forEach(function (x) {
          if (x !== li) {
            x.classList.remove('active');
          }
        });

        li.classList.toggle('active');
      });
    });
  }

  if (openBtn) {
    openBtn.addEventListener('click', toggleMobile);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', function () { closeMobile(true); });
  }

  if (overlay) {
    overlay.addEventListener('click', function () { closeMobile(true); });
  }

  document.addEventListener('click', function (e) {
    if (window.innerWidth <= 968) return;

    if (!e.target.closest('.dropdown') && !e.target.closest('.mega-menu-item')) {
      closeAll();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      const activeDropdown = document.querySelector('.dropdown.active');
      if (activeDropdown) {
        const button = activeDropdown.querySelector('.dropdown-toggle');
        closeAll();
        if (button) button.focus();
      } else if (navMenu.classList.contains('active')) {
        closeMobile(true);
      }
    }
    if (event.key !== 'Tab' || window.innerWidth > 968 || !navMenu.classList.contains('active')) return;
    const focusable = Array.from(navMenu.querySelectorAll('a, button')).filter(function (element) {
      return element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
    });
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  window.addEventListener('resize', function () {
    bindMegaMenu();

    if (window.innerWidth > 968) {
      closeMobile();
    }
  });

  document.querySelectorAll('#navMenu a').forEach(function (a) {
    a.addEventListener('click', function () {
      if (window.innerWidth <= 968) {
        closeMobile();
      }
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    bindMegaMenu();
  });

})();
</script>

<?= $additional_scripts; ?>
