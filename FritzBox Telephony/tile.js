/* FritzBox Telefonie – Kachel.
   mini = verpasste Anrufe heute, compact = Anrufliste mit Filter, wide/full = zusätzlich Rufumleitungen und Rufsperren
   (nur, wenn sie in der Instanz als Variable angelegt sind). Rückruf über die Wählhilfe der FritzBox. */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;
  // Anruftypen laut AVM (Telefonie: Call_*)
  const TYPES = {
    1: { icon: 'phone-arrow-down-left', text: 'Incoming', filter: 'in' },
    2: { icon: 'phone-missed', text: 'Missed', filter: 'missed', cls: 'error' },
    3: { icon: 'phone-arrow-up-right', text: 'Outgoing', filter: 'out' },
    7: { icon: 'fax', text: 'Fax', filter: 'in' },
    9: { icon: 'phone-volume', text: 'Active', filter: 'in', cls: 'accent' },
    10: { icon: 'phone-slash', text: 'Rejected', filter: 'missed', cls: 'error' },
    11: { icon: 'phone-volume', text: 'Active', filter: 'out', cls: 'accent' }
  };
  const FILTERS = [['all', 'All'], ['in', 'Incoming'], ['missed', 'Missed'], ['out', 'Outgoing']];
  let filter = 'all';

  // Datum der FritzBox (TT.MM.JJ HH:MM) mit heute/gestern
  const day = d => {
    const m = /^(\d{2})\.(\d{2})\.(\d{2})/.exec(d || '');
    return m ? new Date(2000 + Number(m[3]), Number(m[2]) - 1, Number(m[1])) : null;
  };
  const isToday = d => {
    const x = day(d);
    return !!x && x.toDateString() === new Date().toDateString();
  };
  const date = d => {
    const x = day(d);
    if (!x) return d || '';
    const time = (d.split(' ')[1] || '');
    const y = new Date();
    y.setDate(y.getDate() - 1);
    if (x.toDateString() === new Date().toDateString()) return T('today') + ' ' + time;
    if (x.toDateString() === y.toDateString()) return T('yesterday') + ' ' + time;
    return d;
  };

  function row(c, size) {
    const t = TYPES[c.type] || { icon: 'phone', text: '' };
    const l2 = [date(c.date), c.name !== c.number ? c.number : '', size === 'full' && c.device ? T('via') + ' ' + c.device : '']
      .filter(x => x).map(esc).join(' · ');
    return '<li class="row">' +
      '<span class="ico ' + (t.cls || '') + '" title="' + esc(T(t.text)) + '">' + icon(t.icon) + '</span>' +
      '<span class="main"><span class="l1">' + esc(c.name || c.number) + (c.found ? ' <span class="muted" title="' + esc(T('Found by reverse search')) + '">' + icon('magnifying-glass') + '</span>' : '') +
      '</span><span class="l2">' + l2 + '</span></span>' +
      '<span class="side">' + (c.duration && c.duration !== '0:00' ? esc(c.duration) : '') +
      (c.number ? '<button class="btn" data-act="TileDial" data-value="' + val(c.number) + '" data-confirm="' + esc(T('Call back %s?').replace('%s', c.name || c.number)) +
        '" title="' + esc(T('Call back')) + '">' + icon('phone') + '</button>' : '') +
      '</span></li>';
  }

  function callList(d, size) {
    const all = (d.calls || []).filter(c => filter === 'all' || (TYPES[c.type] || {}).filter === filter);
    const def = size === 'compact' ? 5 : 25;
    const items = FB.slice('calls', all, def);
    const seg = '<span class="seg">' + FILTERS.map(f => '<button data-on="filter" data-filter="' + f[0] + '"' + (f[0] === filter ? ' class="on"' : '') + '>' +
      esc(T(f[1])) + '</button>').join('') + '</span>';
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Call log')) + '</span>' + (size === 'compact' ? '' : seg) + FB.limitSelect('calls', def, all.length) +
      '<button class="btn" data-act="TileHangup" title="' + esc(T('Hang up')) + '">' + icon('phone-hangup') + '</button></div>' +
      (items.length ? '<ul class="list">' + items.map(c => row(c, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No calls')) + '</div>') + '</div>';
  }

  // Schalter je Rufumleitung bzw. Rufsperre (nur, wenn als Variable angelegt)
  const switches = (title, list) => (list && list.length)
    ? '<div class="group"><div class="ghead"><span>' + esc(T(title)) + '</span></div><div class="switches">' +
      list.filter(v => v).map(v => FB.toggle(v)).join('') + '</div></div>'
    : '';

  FB.render = (d, size) => {
    const missed = (d.calls || []).filter(c => (c.type === 2 || c.type === 10) && isToday(c.date)).length;
    const kpi = '<div class="kpi' + (missed > 0 ? ' alert' : '') + '"><div class="big">' + missed + '</div><div class="sub">' + esc(T('Missed calls')) + ' · ' + esc(T('today')) + '</div></div>';
    if (size === 'mini') {
      return kpi;
    }
    if (size === 'compact') {
      return '<div class="head">' + (missed > 0 ? '<span class="badge error">' + icon('phone-missed') + ' ' + missed + ' ' + esc(T('Missed')) + '</span>' : '') + '</div>' + callList(d, size);
    }
    const side = switches('Call deflections', d.deflections) + switches('Call barring', d.barrings);
    return '<div class="cols"><div class="col side" style="overflow:auto">' + '<div class="head"></div>' + kpi + side + '</div><div class="col main">' + callList(d, size) + '</div></div>';
  };

  FB.handlers.filter = el => {
    filter = el.dataset.filter;
    FB.draw();
  };
})();
