/* Shows the admin-managed site popup rendered by core/popup.php, centred over
   the hero section. It doesn't block the page: no scroll lock or focus trap. */
(function () {
  var popup = document.getElementById('sitePopup');
  if (!popup) return;

  var version   = popup.getAttribute('data-popup-version');
  var frequency = popup.getAttribute('data-popup-frequency');
  var delay     = parseInt(popup.getAttribute('data-popup-delay'), 10) || 0;
  var key       = 'witu-popup-' + version;
  var DAY       = 24 * 60 * 60 * 1000;

  function store(type) {
    try { return window[type]; } catch (e) { return null; }
  }

  function alreadySeen() {
    if (frequency === 'every_visit') return false;
    if (frequency === 'once_per_session') {
      var s = store('sessionStorage');
      return !!(s && s.getItem(key));
    }
    var l = store('localStorage');
    var seen = l && parseInt(l.getItem(key), 10);
    if (!seen) return false;
    return frequency === 'once' || Date.now() - seen < DAY;
  }

  function markSeen() {
    var s = frequency === 'once_per_session' ? store('sessionStorage') : store('localStorage');
    try { if (s) s.setItem(key, String(Date.now())); } catch (e) {}
  }

  if (alreadySeen()) return;

  // Every page names its hero differently (witu-hero, about-hero, pg-hero...).
  var hero = document.querySelector(
    'main section[class*="hero"], main header[class*="hero"], .witu-hero'
  );
  var GAP = 16;

  // Centre over the hero in page coordinates, so it scrolls away with the hero.
  function place() {
    if (popup.hidden) return;
    if (!hero) { popup.classList.add('site-popup--no-hero'); return; }

    var rect = hero.getBoundingClientRect();
    var top  = rect.top + window.pageYOffset;
    var left = rect.left + window.pageXOffset;

    // Fit inside the hero; short heroes still get a usable card that scrolls inside.
    popup.style.setProperty('--site-popup-max-h', Math.max(rect.height - GAP * 2, 320) + 'px');

    var w = popup.offsetWidth, h = popup.offsetHeight;
    var x = left + (rect.width - w) / 2;
    var y = top + Math.max((rect.height - h) / 2, GAP);
    var maxX = document.documentElement.clientWidth - w - GAP;

    popup.style.left = Math.max(GAP, Math.min(x, maxX)) + 'px';
    popup.style.top  = y + 'px';
  }

  function open() {
    popup.hidden = false;
    place();
    markSeen();
    window.addEventListener('resize', place);
    if (hero && window.ResizeObserver) new ResizeObserver(place).observe(hero);
    // The image changes the card's height once it loads.
    var img = popup.querySelector('img');
    if (img && !img.complete) img.addEventListener('load', place);
  }

  function close() {
    var hadFocus = popup.contains(document.activeElement);
    popup.hidden = true;
    // Don't strand keyboard users on a hidden element.
    if (hadFocus && document.body.focus) {
      document.body.setAttribute('tabindex', '-1');
      document.body.focus({ preventScroll: true });
      document.body.removeAttribute('tabindex');
    }
  }

  popup.addEventListener('click', function (e) {
    if (e.target.closest('[data-popup-close], [data-popup-cta]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (!popup.hidden && e.key === 'Escape') close();
  });

  setTimeout(open, delay * 1000);
})();
