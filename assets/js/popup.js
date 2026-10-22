/* Shows the admin-managed site popup rendered by core/popup.php. */
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

  var lastFocus = null;

  function open() {
    lastFocus = document.activeElement;
    popup.hidden = false;
    document.body.classList.add('site-popup-open');
    markSeen();
    var close = popup.querySelector('.site-popup__close');
    if (close) close.focus();
  }

  function close() {
    popup.hidden = true;
    document.body.classList.remove('site-popup-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  popup.addEventListener('click', function (e) {
    if (e.target.closest('[data-popup-close]')) close();
    else if (e.target.closest('[data-popup-cta]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (popup.hidden) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key !== 'Tab') return;
    // Keep keyboard focus inside the dialog while it is open.
    var items = popup.querySelectorAll('a[href], button');
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  setTimeout(open, delay * 1000);
})();
