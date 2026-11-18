/* FritzBox Host Filter – Kachel.
   mini = Anzahl gesperrter Geräte, compact = Geräteliste mit Sperre, wide/full = zusätzlich Suche.
   Details je Gerät (Zugangsprofil, Online-Zeit, Ticket) auf Klick: Die Instanz lädt sie einzeln von der FritzBox. */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;
  let search = '';
  let openIp = '';     // Gerät mit geöffneten Details
  let entry = null;    // Details des Gerätes (Nachricht hostEntry)

  const minutes = s => Math.round((s || 0) / 60) + ' ' + T('min');
  // Rest in Minuten (Annahme, deckt sich mit der FritzBox-Oberfläche): verbleibendes Budget + Restminuten des laufenden
  // Tickets + Tickets im Voraus × 45 min. Ohne Budget und ohne Ticket keine Angabe.
  const remaining = e => (!e.timeMax && !e.ticketValid && !e.tickets) ? null
    : Math.max(0, Math.round(((e.timeMax || 0) - (e.timeUsed || 0)) / 60)) + (e.ticketValid || 0) + (e.tickets || 0) * 45;
  // Heute freigegebene Zeit (die FritzBox liefert sie nicht): Budget + Tickets × 45 min. Anzahl der Tickets aus der
  // Ticket-Zeit, die über das Budget hinaus verbraucht ist, plus Restminuten des laufenden Tickets (TimeUsed zählt die
  // Ticket-Zeit mit, live geprüft), gerundet auf ganze Tickets, plus Tickets im Voraus.
  const ticketCount = e => Math.round((Math.max(0, (e.timeUsed || 0) - (e.timeMax || 0)) / 60 + (e.ticketValid || 0)) / 45) + (e.tickets || 0);
  const granted = e => Math.round((e.timeMax || 0) / 60) + ticketCount(e) * 45;

  function details() {
    // Details in derselben Karte wie das Gerät (unter Name/IP, eingerückt); data-on="stay": Klicks hier klappen nicht zu
    if (!entry || entry.ip !== openIp) return '<div class="rowdetails" data-on="stay"><span class="muted">' + esc(T('Loading…')) + '</span></div>';
    const select = '<select data-profile="' + esc(entry.ip) + '">' + entry.profiles.map(p => '<option value="' + esc(p.id) + '"' + (p.id === entry.profile ? ' selected' : '') + '>' +
      esc(p.name) + '</option>').join('') + '</select>';
    return '<div class="rowdetails" data-on="stay">' +
      '<label class="muted">' + esc(T('Access profile')) + ' ' + select + '</label>' +
      '<span class="muted"' + (entry.timeMax && ticketCount(entry) ? ' title="' + esc(T('Budget') + ' ' + minutes(entry.timeMax) + ' + ' + ticketCount(entry) + ' × 45 ' + T('min') + ' ' + T('Ticket')) + '"' : '') + '>' +
      esc(T('Online time today')) + ': ' + esc(minutes(entry.timeUsed)) + (entry.timeMax ? ' / ' + esc(granted(entry) + ' ' + T('min')) : '') +
      (remaining(entry) !== null ? ' · ' + esc(T('Remaining')) + ': ' + esc(remaining(entry) + ' ' + T('min')) : '') + '</span>' +
      // Online-Ticket (zusätzliche Online-Zeit) nur, wenn die FritzBox es für das Gerät freigibt (sonst lehnt sie ab)
      (entry.ticket ? '<button class="tool" data-act="TileTicket" data-value="' + val(entry.ip) + '">' + icon('ticket') + ' ' + esc(T('Add ticket')) + '</button>' : '') + '</div>';
  }

  function row(h) {
    const toggle = '<button class="toggle' + (h.disallow ? ' on' : '') + '" style="--sw:var(--error)" data-act="TileDisallow" data-value="' + val(JSON.stringify({ ip: h.ip, value: !h.disallow })) +
      '" title="' + esc(T('Internet access')) + '"><span class="knob"></span><span>' + esc(T(h.disallow ? 'Blocked' : 'Allowed')) + '</span></button>';
    // Klick in die Zeile öffnet/schließt die Details (der Schalter hat Vorrang, data-act wird zuerst ausgewertet)
    return '<li class="row' + (h.active ? '' : ' off') + (openIp === h.ip ? ' open' : '') + '" data-on="details" data-ip="' + esc(h.ip) + '" style="cursor:pointer"><span class="dot' + (h.active ? ' on' : '') + '"></span>' +
      '<span class="main"><span class="l1">' + esc(h.name) + '</span><span class="l2">' + esc(h.ip) + '</span></span>' +
      '<span class="side">' + toggle + '<button class="btn" data-on="details" data-ip="' + esc(h.ip) + '" title="' + esc(T('Details')) + '">' +
      icon(openIp === h.ip ? 'chevron-up' : 'chevron-down') + '</button></span>' + (openIp === h.ip ? details() : '') + '</li>';
  }

  FB.render = (d, size) => {
    const hosts = d.hosts || [];
    const blocked = hosts.filter(h => h.disallow).length;
    const kpi = '<div class="kpi' + (blocked ? ' alert' : '') + '"><div class="big">' + blocked + '</div><div class="sub">' + esc(T('Blocked devices')) + '</div></div>';
    if (size === 'mini') return kpi;
    const s = search.toLowerCase();
    const list = hosts.filter(h => !s || h.name.toLowerCase().includes(s) || h.ip.includes(s));
    const def = size === 'compact' ? 10 : 50;
    const items = FB.slice('hosts', list, def);
    const tools = size === 'compact' ? '' : '<input type="search" data-search placeholder="' + esc(T('Search')) + '" value="' + esc(search) + '" style="width:140px">';
    const group = '<div class="group grow"><div class="ghead"><span>' + esc(T('Internet access')) + '</span>' + tools + FB.limitSelect('hosts', def, list.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(row).join('') + '</ul>' : '<div class="empty">' + esc(T('No devices')) + '</div>') + '</div>';
    if (size === 'compact') return group;
    return '<div class="cols"><div class="col side"><div class="head"></div>' + kpi + '</div><div class="col main">' + group + '</div></div>';
  };

  FB.handlers.details = el => {
    const ip = el.dataset.ip;
    openIp = openIp === ip ? '' : ip;
    entry = null;
    if (openIp) FB.act('TileHostEntry', openIp);
    FB.draw();
  };
  FB.onMessage = msg => {
    if (msg.type === 'hostEntry') {
      entry = msg;
      FB.draw();
    }
  };
  FB.afterRender = root => {
    const sel = root.querySelector('select[data-profile]');
    if (sel) sel.addEventListener('change', () => FB.act('TileFilterProfile', JSON.stringify({ ip: sel.dataset.profile, profile: sel.value })));
    const input = root.querySelector('[data-search]');
    if (!input) return;
    input.addEventListener('input', () => {
      search = input.value;
      const pos = input.selectionStart;
      FB.draw();
      const again = document.querySelector('[data-search]');
      if (again) {
        again.focus();
        again.setSelectionRange(pos, pos);
      }
    });
  };
})();
