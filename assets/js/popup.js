/* Shows the admin-managed site popup rendered by core/popup.php. It is a
   non-blocking corner card: no scroll lock and no focus trap. */
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

  function open() {
    popup.hidden = false;
    markSeen();
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
