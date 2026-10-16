(() => {
  'use strict';
  const key = 'witu-accessibility-v1';
  const defaults = { size: 100, spacing: false, contrast: 'default', links: false, headings: false, motion: false, images: false, font: false, guide: false };
  let settings = { ...defaults };
  try { const saved = JSON.parse(localStorage.getItem(key)); Object.keys(defaults).forEach(k => { if (saved && typeof saved[k] === typeof defaults[k]) settings[k] = saved[k]; }); } catch (_) {}
  settings.size = [100,110,120,130,140,150].includes(settings.size) ? settings.size : 100;
  settings.contrast = ['default','dark','light'].includes(settings.contrast) ? settings.contrast : 'default';
  const launcher = document.createElement('button');
  launcher.type = 'button'; launcher.className = 'a11y-launcher'; launcher.textContent = 'Accessibility';
  launcher.setAttribute('aria-haspopup', 'dialog'); launcher.setAttribute('aria-controls', 'a11y-panel');
  const panel = document.createElement('dialog'); panel.id = 'a11y-panel'; panel.className = 'a11y-panel';
  panel.setAttribute('aria-labelledby', 'a11y-title');
  panel.innerHTML = `<div class="a11y-top"><h2 id="a11y-title">Accessibility settings</h2><button type="button" data-close aria-label="Close accessibility settings">Close</button></div>
    <p>Choose how you read and navigate. Settings are saved in this browser when available.</p>
    <label for="a11y-size">Text size</label><select id="a11y-size" data-setting="size">${[100,110,120,130,140,150].map(n => `<option value="${n}">${n}%</option>`).join('')}</select>
    <label for="a11y-contrast">Colour contrast</label><select id="a11y-contrast" data-setting="contrast"><option value="default">Site colours</option><option value="dark">Light text on dark</option><option value="light">Dark text on light</option></select>
    <div class="a11y-options">${[['spacing','Increase text spacing'],['font','Use a simple font'],['links','Highlight and underline links'],['headings','Highlight headings'],['motion','Reduce motion'],['images','Hide decorative and content images'],['guide','Show reading guide']].map(([id,label]) => `<label><input type="checkbox" data-setting="${id}"> ${label}</label>`).join('')}</div>
    <div class="a11y-actions"><button type="button" data-read>Read main content aloud</button><button type="button" data-pause>Pause reading</button><button type="button" data-resume>Resume reading</button><button type="button" data-stop>Stop reading</button><button type="button" data-main>Skip to main content</button><button type="button" data-reset>Reset settings</button></div>
    <p class="a11y-help">Keyboard: Tab moves between controls, Enter activates a link, and Escape closes this panel. The reading guide follows the pointer or focused control. Read aloud depends on your browser's available voices.</p>
    <p role="status" aria-live="polite" id="a11y-status"></p>`;
  const guide = document.createElement('div'); guide.className = 'a11y-guide'; guide.setAttribute('aria-hidden','true');
  document.body.append(launcher, panel, guide);
  const status = message => { panel.querySelector('#a11y-status').textContent = message; };
  const textElements = Array.from(document.querySelectorAll('main :is(h1,h2,h3,h4,h5,h6,p,li,a,button,label,input,select,textarea,blockquote,td,th,span), .site-header :is(a,button,p), .site-footer :is(a,p,h3)'));
  const original = new Map(textElements.map(el => [el, { size: parseFloat(getComputedStyle(el).fontSize), inline: el.style.fontSize }]));
  function apply() {
    textElements.forEach(el => { const baseline = original.get(el); el.style.fontSize = settings.size === 100 ? baseline.inline : `${baseline.size * settings.size / 100}px`; });
    Object.keys(defaults).filter(k => typeof defaults[k] === 'boolean').forEach(k => document.body.classList.toggle(`a11y-${k}`, settings[k]));
    document.body.dataset.a11yContrast = settings.contrast;
    panel.querySelectorAll('[data-setting]').forEach(control => { const k = control.dataset.setting; if (control.type === 'checkbox') control.checked = settings[k]; else control.value = settings[k]; });
    guide.hidden = !settings.guide;
    try { localStorage.setItem(key, JSON.stringify(settings)); } catch (_) {}
  }
  launcher.addEventListener('click', () => { panel.showModal(); panel.querySelector('[data-close]').focus(); });
  panel.addEventListener('close', () => launcher.focus());
  panel.querySelector('[data-close]').addEventListener('click', () => panel.close());
  panel.addEventListener('change', event => { const k = event.target.dataset.setting; if (!k) return; settings[k] = k === 'size' ? Number(event.target.value) : event.target.type === 'checkbox' ? event.target.checked : event.target.value; apply(); status('Settings updated.'); });
  panel.querySelector('[data-reset]').addEventListener('click', () => { settings = {...defaults}; if ('speechSynthesis' in window) speechSynthesis.cancel(); apply(); status('Settings reset.'); });
  panel.querySelector('[data-main]').addEventListener('click', () => { panel.close(); const main = document.getElementById('main-content'); if (main) { main.focus(); main.scrollIntoView({behavior:'auto'}); } });
  // Large screens scale the page with CSS zoom; viewport coordinates are unscaled, so divide it back out.
  const zoom = () => parseFloat(getComputedStyle(document.documentElement).zoom) || 1;
  document.addEventListener('pointermove', e => { if (settings.guide) guide.style.top = `${(e.clientY + 18) / zoom()}px`; }, {passive:true});
  document.addEventListener('focusin', e => { if (settings.guide && !panel.contains(e.target)) guide.style.top = `${Math.min(innerHeight - 8, e.target.getBoundingClientRect().bottom + 8) / zoom()}px`; });
  const read = panel.querySelector('[data-read]'); const stop = panel.querySelector('[data-stop]');
  if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) { read.disabled = stop.disabled = true; panel.querySelector('[data-pause]').disabled = panel.querySelector('[data-resume]').disabled = true; status('Read aloud is unavailable in this browser.'); }
  else {
    let queue = [];
    let generation = 0;
    const cancel = () => { generation++; queue = []; speechSynthesis.cancel(); };
    read.addEventListener('click', () => {
      cancel(); const main = document.getElementById('main-content'); const text = main ? main.innerText.trim() : '';
      if (!text) { status('There is no readable main content on this page.'); return; }
      queue = text.match(/[\s\S]{1,220}(?:\s|$)|[\s\S]{1,220}/g) || [];
      const current = generation;
      const next = () => { if (current !== generation) return; if (!queue.length) { status('Reading finished.'); return; } const utterance = new SpeechSynthesisUtterance(queue.shift()); utterance.lang = document.documentElement.lang || 'en'; utterance.onend = next; utterance.onerror = () => { cancel(); status('Reading stopped. Please try your browser or screen reader reading tools.'); }; speechSynthesis.speak(utterance); };
      status('Reading main content. Use Stop reading to stop.'); next();
    });
    panel.querySelector('[data-pause]').addEventListener('click', () => { if (speechSynthesis.speaking) { speechSynthesis.pause(); status('Reading paused.'); } });
    panel.querySelector('[data-resume]').addEventListener('click', () => { if (speechSynthesis.paused) { speechSynthesis.resume(); status('Reading resumed.'); } });
    stop.addEventListener('click', () => { cancel(); status('Reading stopped.'); });
    window.addEventListener('pagehide', cancel);
  }
  apply();
})();
