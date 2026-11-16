/* FritzBox – gemeinsame Bausteine der Instanz-Kacheln (HTML-SDK).
   Das Skript des Moduls setzt FB.render(data, size) und liefert damit das HTML der Kachel; die Kachel wird bei neuen Daten
   und beim Wechsel der Größenstufe neu gezeichnet. Bedient wird über Attribute im HTML:
   data-act="Ident" data-value="JSON"  -> requestAction(Ident, Wert), optional mit data-confirm="Text" (Sicherheitsabfrage)
   data-on="Name"                      -> FB.handlers[Name](Element, Ereignis) des Modulskripts
   select[data-limit="Liste"]          -> Anzahl der angezeigten Einträge einer Liste (im Browser gemerkt) */
(function () {
  'use strict';
  const cfg = window.FB_CONFIG || { data: {}, texts: {} };
  const app = document.getElementById('app');
  const FB = window.FB = {
    cfg: cfg,
    data: cfg.data || {},
    size: 'compact',
    handlers: {},
    render: () => '',
    afterRender: null,
    onMessage: null
  };

  // Übersetzung in der Sprache des Browsers über translate() der Visu (locale.json des Moduls).
  // Die Texte der Instanz gelten nur ohne Visu (z.B. Vorschau).
  FB.T = key => {
    if (typeof window.translate === 'function') {
      try {
        const text = window.translate(key);
        if (typeof text === 'string' && text !== '') {
          return text;
        }
      } catch (e) { /* ohne Visu */ }
    }
    return (cfg.texts || {})[key] || key;
  };
  FB.esc = s => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // Font-Awesome-Symbol (icons.js der Visu), Stil Light
  FB.icon = name => name ? '<i class="fa-light fa-' + FB.esc(name) + '" aria-hidden="true"></i>' : '';
  FB.act = (ident, value) => {
    try { requestAction(ident, value); } catch (e) { /* außerhalb von Symcon */ }
  };
  // Farbe einer Darstellung (RGB als Zahl, -1 = keine) als CSS-Farbe
  FB.color = c => (typeof c === 'number' && c >= 0) ? '#' + c.toString(16).padStart(6, '0') : '';
  // Schrift auf einer Fläche in der Farbe c: hell -> --on-light-card-color, dunkel -> --on-dark-card-color
  FB.textOn = c => {
    if (typeof c !== 'number' || c < 0) return '';
    const r = (c >> 16) & 255, g = (c >> 8) & 255, b = c & 255;
    return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? 'var(--on-light)' : 'var(--on-dark)';
  };
  // Wert als Attribut data-value
  FB.val = v => FB.esc(JSON.stringify(v));

  // Schalter für eine Statusvariable (Beschreibung aus TileVariable der Instanz). Ohne Variable kein Schalter.
  FB.toggle = (v, label) => {
    if (!v) return '';
    const on = !!v.value;
    const color = on ? FB.color(v.color) : '';
    return '<button class="toggle' + (on ? ' on' : '') + '"' + (v.action ? '' : ' disabled') +
      ' data-act="' + FB.esc(v.ident) + '" data-value="' + FB.val(!on) + '"' + (color ? ' style="--sw:' + color + '"' : '') +
      ' title="' + FB.esc(v.name) + ': ' + FB.esc(v.text) + '"><span class="knob"></span>' + (v.icon ? FB.icon(v.icon) : '') +
      '<span>' + FB.esc(label === undefined ? v.name : label) + '</span></button>';
  };
  // Kennzeichen mit dem formatierten Wert einer Statusvariable, Farbe laut Darstellung
  FB.badge = (v, cls) => {
    if (!v) return '';
    const color = FB.color(v.color);
    return '<span class="badge ' + (cls || '') + '"' + (color ? ' style="background:' + color + ';color:' + FB.textOn(v.color) + '"' : '') +
      ' title="' + FB.esc(v.name) + '">' + (v.icon ? FB.icon(v.icon) : '') + FB.esc(v.text) + '</span>';
  };

  // Anzahl der angezeigten Einträge je Liste, im Browser gemerkt (je Instanz)
  const limitKey = list => 'fb.' + cfg.instance + '.' + list;
  FB.limit = (list, def) => {
    try {
      const v = localStorage.getItem(limitKey(list));
      if (v !== null) return Number(v);
    } catch (e) { /* ohne Speicher */ }
    return def;
  };
  FB.limitSelect = (list, def, total) => {
    const cur = FB.limit(list, def);
    const opts = [5, 10, 25, 50, 100, 0].filter(n => n === 0 || n < total || n === cur);
    if (total <= 5 && cur !== 0 && cur >= total) return '';
    return '<label class="muted">' + FB.esc(FB.T('Entries')) + ' <select data-limit="' + FB.esc(list) + '">' +
      opts.map(n => '<option value="' + n + '"' + (n === cur ? ' selected' : '') + '>' + (n === 0 ? FB.esc(FB.T('All')) : n) + '</option>').join('') +
      '</select></label>';
  };
  FB.slice = (list, items, def) => {
    const n = FB.limit(list, def);
    return n > 0 ? items.slice(0, n) : items;
  };

  // Sicherheitsabfrage in der Kachel
  FB.confirm = (text, onOk) => {
    const d = document.createElement('div');
    d.className = 'dialog';
    d.innerHTML = '<div><div>' + FB.esc(text) + '</div><div class="btns"><button class="tool" data-x="0">' + FB.esc(FB.T('Cancel')) +
      '</button><button class="tool danger" data-x="1">' + FB.esc(FB.T('OK')) + '</button></div></div>';
    d.addEventListener('click', e => {
      const b = e.target.closest('[data-x]');
      if (!b && e.target !== d) return;
      d.remove();
      if (b && b.dataset.x === '1') onOk();
    });
    app.appendChild(d);
  };
  // Hinweis in der Kachel (fehlende Voraussetzung)
  let noticeTimer = 0;
  FB.notice = text => {
    let n = app.querySelector('.notice');
    if (!n) {
      n = document.createElement('div');
      n.className = 'notice';
      app.appendChild(n);
    }
    n.textContent = text;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => n.remove(), 6000);
  };
  // Objekt in der Visu öffnen (z.B. Diagramm einer geloggten Variable), ab Symcon 8.2
  FB.open = id => {
    try { openObject(id); } catch (e) { /* ältere Visu */ }
  };

  // Überschrift der Kachel: Die Visu übergibt margintop (mit Titel > 15) und legt den Titel über die Kachel
  try {
    const top = Number(new URLSearchParams(location.search).get('margintop'));
    if (top > 15) {
      document.documentElement.style.setProperty('--title-space', (top - 15) + 'px');
      document.documentElement.style.setProperty('--corner', '0px');
    }
  } catch (e) { /* ohne Angabe: kein Titel */ }

  FB.draw = () => {
    app.className = 'app size-' + FB.size;
    // Dialog und Hinweis bleiben beim Neuzeichnen erhalten
    const keep = Array.from(app.querySelectorAll('.dialog, .notice'));
    let html = '';
    try {
      html = FB.render(FB.data, FB.size);
    } catch (e) {
      html = '<div class="empty">' + FB.esc(e.message) + '</div>';
    }
    app.innerHTML = html;
    keep.forEach(k => app.appendChild(k));
    if (FB.afterRender) FB.afterRender(app);
  };

  // Größenstufe nach der Größe der Kachel; neu gezeichnet wird nur beim Wechsel der Stufe
  const stage = (w, h) => (w < 250 || h < 160) ? 'mini' : (w >= 880 && h >= 520) ? 'full' : (w >= 560 && h >= 300) ? 'wide' : 'compact';
  new ResizeObserver(() => {
    const s = stage(window.innerWidth, window.innerHeight);
    if (s !== FB.size) {
      FB.size = s;
      FB.draw();
    }
  }).observe(document.documentElement);

  app.addEventListener('click', e => {
    const a = e.target.closest('[data-act]');
    if (a && !a.disabled) {
      const run = () => FB.act(a.dataset.act, a.dataset.value === undefined ? true : JSON.parse(a.dataset.value));
      if (a.dataset.confirm) FB.confirm(a.dataset.confirm, run); else run();
      return;
    }
    const h = e.target.closest('[data-on]');
    if (h && !h.disabled && FB.handlers[h.dataset.on]) {
      FB.handlers[h.dataset.on](h, e);
    }
  });
  app.addEventListener('change', e => {
    const s = e.target.closest('select[data-limit]');
    if (!s) return;
    try { localStorage.setItem(limitKey(s.dataset.limit), s.value); } catch (x) { /* ohne Speicher */ }
    FB.draw();
  });

  // Nachrichten der Instanz (UpdateVisualizationValue): state = neue Daten, notice = Hinweis
  window.handleMessage = raw => {
    let msg;
    try { msg = typeof raw === 'string' ? JSON.parse(raw) : raw; } catch (e) { return; }
    if (!msg) return;
    if (msg.type === 'state') {
      FB.data = msg.data || {};
      FB.draw();
    } else if (msg.type === 'notice') {
      FB.notice(msg.text);
    } else if (FB.onMessage) {
      FB.onMessage(msg);
    }
  };
  // Erstes Zeichnen, nachdem das Modulskript FB.render gesetzt hat
  setTimeout(() => {
    FB.size = stage(window.innerWidth, window.innerHeight);
    FB.draw();
  }, 0);
})();
