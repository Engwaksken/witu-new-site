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
  var header = document.querySelector('.site-header');
  var GAP = 16;

  // Fixed to the screen, so it stays in view while the page scrolls. It opens
  // centred over the visible part of the hero, or the screen when the hero has
  // scrolled away. Only re-placed on resize, never on scroll.
  function place() {
    if (popup.hidden) return;

    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;
    var hb = header ? header.getBoundingClientRect().bottom : 0;
    var topBound = Math.max(hb, 0) + GAP;

    popup.style.setProperty('--site-popup-max-h', Math.max(vh - topBound - GAP, 240) + 'px');

    var w = popup.offsetWidth, h = popup.offsetHeight;
    var areaTop = topBound, areaBottom = vh - GAP, areaLeft = 0, areaWidth = vw;

    if (hero) {
      var r = hero.getBoundingClientRect();
      var visTop = Math.max(r.top, topBound), visBottom = Math.min(r.bottom, vh - GAP);
      if (visBottom - visTop >= h) {
        areaTop = visTop; areaBottom = visBottom;
        areaLeft = r.left; areaWidth = r.width;
      }
    }

    var x = areaLeft + (areaWidth - w) / 2;
    var y = areaTop + Math.max((areaBottom - areaTop - h) / 2, 0);

    popup.style.left = Math.max(GAP, Math.min(x, vw - w - GAP)) + 'px';
    popup.style.top  = Math.max(topBound, Math.min(y, vh - h - GAP)) + 'px';
  }

  function open() {
    popup.hidden = false;
    place();
    markSeen();
    window.addEventListener('resize', place);
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
