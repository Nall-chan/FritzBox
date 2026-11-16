/* FritzBox Anrufmonitor – Kachel: aktive Gespräche mit Zustand (klingelt, wählt, verbunden) und laufender Dauer.
   mini = klingelnder bzw. erster Anruf, sonst „Keine aktiven Gespräche“. */
(function () {
  'use strict';
  const { T, esc, icon } = FB;
  // Zeitversatz zwischen Server und Browser, damit die laufende Dauer stimmt
  let offset = 0;
  const now = () => Math.floor(Date.now() / 1000) - offset;
  const clock = s => {
    s = Math.max(0, s);
    const h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, x = s % 60;
    return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0');
  };
  const state = c => c.status === 'CONNECT' ? { text: 'Connected', icon: 'phone-volume', cls: 'accent' }
    : c.in ? { text: 'Ringing', icon: 'phone-intercom', cls: 'error' } : { text: 'Dialing', icon: 'phone-arrow-up-right', cls: '' };

  function row(c, size) {
    const s = state(c);
    const l2 = [c.name ? c.remote : '', c.local ? (c.in ? T('on') : T('via')) + ' ' + c.local : '', size !== 'compact' && c.device ? c.device : '']
      .filter(x => x).map(esc).join(' · ');
    return '<li class="row' + (c.status === 'RING' ? ' new' : '') + '">' +
      '<span class="ico ' + s.cls + '" title="' + esc(T(c.in ? 'Incoming' : 'Outgoing')) + '">' + icon(s.icon) + '</span>' +
      '<span class="main"><span class="l1">' + esc(c.name || c.remote) + (c.found ? ' <span class="muted" title="' + esc(T('Found by reverse search')) + '">' + icon('magnifying-glass') + '</span>' : '') +
      '</span><span class="l2">' + l2 + '</span></span>' +
      '<span class="side"><span class="badge ' + s.cls + '">' + esc(T(s.text)) + '</span>' +
      '<span class="time" data-since="' + (c.since || 0) + '">' + (c.since ? clock(now() - c.since) : '') + '</span></span></li>';
  }

  FB.render = (d, size) => {
    offset = d.now ? Math.floor(Date.now() / 1000) - d.now : 0;
    const calls = d.calls || [];
    if (size === 'mini') {
      const c = calls.find(x => x.status === 'RING') || calls[0];
      if (!c) return '<div class="kpi"><div class="big">' + icon('phone') + '</div><div class="sub">' + esc(T('No active calls')) + '</div></div>';
      const s = state(c);
      return '<div class="kpi' + (c.status === 'RING' ? ' alert' : '') + '"><div class="big">' + icon(s.icon) + '</div><div>' + esc(c.name || c.remote) +
        '</div><div class="sub">' + esc(T(s.text)) + '</div></div>';
    }
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Active calls')) + '</span></div>' +
      (calls.length ? '<ul class="list">' + calls.map(c => row(c, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No active calls')) + '</div>') + '</div>';
  };

  // Laufende Dauer jede Sekunde nachführen, ohne neu zu zeichnen
  setInterval(() => {
    document.querySelectorAll('[data-since]').forEach(el => {
      const since = Number(el.dataset.since);
      if (since > 0) el.textContent = clock(now() - since);
    });
  }, 1000);
})();
