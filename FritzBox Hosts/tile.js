/* FritzBox Netzwerkgeräte – Kachel.
   mini = Geräte online/gesamt, compact = Liste mit Filter, wide/full = zusätzlich Suche, IP/MAC und Verbindung.
   Wake-On-LAN für Geräte mit MAC-Adresse, die offline sind; Klick auf den Namen öffnet die Variable des Gerätes (falls angelegt). */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;
  const FILTERS = [['all', 'All'], ['on', 'Online'], ['off', 'Offline'], ['lan', 'LAN'], ['wlan', 'WLAN']];
  let filter = 'all';
  let search = '';

  const isWlan = h => /802\.11/.test(h.iface);
  const match = h => {
    if (filter === 'on' && !h.active) return false;
    if (filter === 'off' && h.active) return false;
    if (filter === 'lan' && (h.iface !== 'Ethernet')) return false;
    if (filter === 'wlan' && !isWlan(h)) return false;
    if (search) {
      const s = search.toLowerCase();
      return [h.name, h.ip, h.mac].some(x => String(x || '').toLowerCase().includes(s));
    }
    return true;
  };
  const ifaceIcon = h => h.iface === 'Ethernet' ? 'ethernet' : isWlan(h) ? 'wifi' : 'network-wired';

  function row(h, size) {
    const l2 = [h.ip, size === 'full' ? h.mac : '', size !== 'compact' && h.speed ? h.speed + ' Mbit/s' : ''].filter(x => x).map(esc).join(' · ');
    const name = h.var ? '<button class="l1" data-on="open" data-id="' + h.var + '" title="' + esc(T('Open variable')) + '" style="text-align:left">' + esc(h.name) + '</button>'
      : '<span class="l1">' + esc(h.name) + '</span>';
    return '<li class="row' + (h.active ? '' : ' off') + '">' +
      '<span class="dot' + (h.active ? ' on' : '') + '"></span>' +
      '<span class="ico" title="' + esc(h.iface) + '">' + icon(ifaceIcon(h)) + '</span>' +
      '<span class="main">' + name + '<span class="l2">' + l2 + '</span></span>' +
      '<span class="side">' + (h.guest ? '<span class="badge">' + esc(T('Guest')) + '</span>' : '') +
      (h.mac && !h.active ? '<button class="btn" data-act="TileWOL" data-value="' + val(h.mac) + '" data-confirm="' + esc(T('Wake up %s?').replace('%s', h.name)) +
        '" title="' + esc(T('Wake on LAN')) + '">' + icon('power-off') + '</button>' : '') +
      '</span></li>';
  }

  FB.render = (d, size) => {
    const hosts = d.hosts || [];
    const online = d.online ? Number(d.online.value) : hosts.filter(h => h.active).length;
    const total = d.total ? Number(d.total.value) : hosts.length;
    const kpi = '<div class="kpi"><div class="big">' + online + '<span class="muted" style="font-size:.5em"> / ' + total + '</span></div><div class="sub">' +
      esc(T('Network devices')) + ' ' + esc(T('online')) + '</div></div>';
    if (size === 'mini') return kpi;
    const list = hosts.filter(match);
    const def = size === 'compact' ? 10 : 50;
    const items = FB.slice('hosts', list, def);
    const seg = '<span class="seg">' + FILTERS.map(f => '<button data-on="filter" data-filter="' + f[0] + '"' + (f[0] === filter ? ' class="on"' : '') + '>' + esc(T(f[1])) + '</button>').join('') + '</span>';
    const tools = size === 'compact' ? '' : '<input type="search" data-search placeholder="' + esc(T('Search')) + '" value="' + esc(search) + '" style="width:140px">';
    const group = '<div class="group grow"><div class="ghead"><span>' + esc(T('Network devices')) + '</span>' + seg + tools + FB.limitSelect('hosts', def, list.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(h => row(h, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No devices')) + '</div>') + '</div>';
    if (size === 'compact') return group;
    return '<div class="cols"><div class="col side">' + '<div class="head"></div>' + kpi + '</div><div class="col main">' + group + '</div></div>';
  };

  FB.handlers.filter = el => {
    filter = el.dataset.filter;
    FB.draw();
  };
  FB.handlers.open = el => FB.open(Number(el.dataset.id));
  // Suche: neu zeichnen und den Fokus im Suchfeld behalten
  FB.afterRender = root => {
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
