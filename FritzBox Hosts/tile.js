/* FritzBox Netzwerkgeräte – Kachel.
   mini = Geräte online/gesamt, compact = Liste mit Filter, wide/full = zusätzlich Suche, IP/MAC und Verbindung.
   Aufwecken (Aktion der Statusvariable, z. B. Wake-On-LAN) für Geräte, die offline sind und deren Statusvariable eine Aktion hat; Klick auf den Namen öffnet die Variable des Gerätes (falls angelegt). */
(function () {
  'use strict';
  // Filterleiste und Umschalter brauchen Platz: unter ca. 4 Spalten Breite nur die Geräteanzahl
  FB.miniWidth = 360;
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
      (h.action && !h.active ? '<button class="btn" data-act="TileWOL" data-value="' + val(h.var) + '" data-confirm="' + esc(T('Wake up %s?').replace('%s', h.name)) +
        '" title="' + esc(T('Wake on LAN')) + '">' + icon('power-off') + '</button>' : '') +
      '</span></li>';
  }

  /* Mesh-Übersicht: Baum ab dem Mesh-Master (FritzBox\Mesh::Build), nur auf Anforderung geladen */
  const VIEW_KEY = 'fb.' + FB.cfg.instance + '.view';
  let view = 'list';
  try { view = localStorage.getItem(VIEW_KEY) === 'mesh' ? 'mesh' : 'list'; } catch (e) { /* ohne Speicher */ }
  let mesh = null;
  let meshLoading = false;
  const closed = new Set();
  const loadMesh = () => {
    meshLoading = true;
    FB.act('TileMesh', true);
  };
  // Datenrate in kbit/s als Mbit/s bzw. Gbit/s
  const rate = k => {
    if (!k) return '';
    const m = k / 1000;
    return m >= 1000 ? (m / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' Gbit/s' : Math.round(m).toLocaleString() + ' Mbit/s';
  };
  // Schnittstelle: LAN:1 -> LAN 1, AP:5G:0 -> WLAN 5 GHz, sonst der Typ
  const ifaceLabel = i => {
    const n = String(i.name || '');
    const w = n.match(/^(?:AP|STA)[:\s]?(\d)G/i);
    if (w) return 'WLAN ' + (w[1] === '2' ? (2.4).toLocaleString() : w[1]) + ' GHz';
    if (/^LAN:\d+$/.test(n)) return n.replace(':', ' ');
    return n || i.type || '?';
  };
  const linkIcon = t => t === 'WLAN' ? 'wifi' : t === 'PLC' ? 'plug' : 'ethernet';
  const count = n => n.ifaces.reduce((s, i) => s + i.links.reduce((a, l) => a + 1 + count(l.node), 0), 0);

  function meshNode(n, link, key) {
    const sub = n.ifaces.length > 0;
    const role = n.role === 'master' ? T('Mesh master') : (n.role === 'slave' && n.meshed) ? T('Mesh repeater') : '';
    const l2 = [n.model !== n.name ? n.model : '', n.ip].filter(x => x).map(esc).join(' · ');
    const speed = link ? [link.rx ? '↓ ' + rate(link.rx) : '', link.tx ? '↑ ' + rate(link.tx) : ''].filter(x => x).join(' ') : '';
    const head = '<div class="row">' + (sub ? '<span class="caret">' + icon('chevron-right') + '</span>' : '') +
      '<span class="ico' + (role ? ' accent' : '') + '">' + icon(link ? linkIcon(link.type) : 'router') + '</span>' +
      '<span class="main"><span class="l1">' + esc(n.name || n.mac || '?') + '</span>' + (l2 ? '<span class="l2">' + l2 + '</span>' : '') + '</span>' +
      '<span class="side">' + (role ? '<span class="badge accent">' + esc(role) + '</span>' : '') +
      (sub && link ? '<span class="badge">' + esc(T('%d devices').replace('%d', count(n))) + '</span>' : '') +
      (speed ? '<span title="' + esc(link.type) + '">' + esc(speed) + '</span>' : '') + '</span></div>';
    if (!sub) return '<li>' + head + '</li>';
    const body = n.ifaces.map((i, x) => '<li><div class="ifhead">' + icon(linkIcon(i.type)) + esc(ifaceLabel(i)) +
      '<span class="muted">(' + i.links.length + ')</span></div><ul class="tree">' +
      i.links.map((l, y) => meshNode(l.node, l, key + '.' + x + '.' + y)).join('') + '</ul></li>').join('');
    return '<li><details data-key="' + esc(key) + '"' + (closed.has(key) ? '' : ' open') + '><summary>' + head + '</summary><ul class="tree">' + body + '</ul></details></li>';
  }

  function meshView() {
    if (!mesh) {
      if (!meshLoading) loadMesh();
      return '<div class="empty">' + esc(T('Loading…')) + '</div>';
    }
    if (mesh.error) return '<div class="empty">' + esc(T('Mesh overview not available.')) + '</div>';
    if (!mesh.root) return '<div class="empty">' + esc(T('No devices')) + '</div>';
    return '<div class="scroll"><ul class="tree">' + meshNode(mesh.root, null, 'r') + '</ul>' +
      (mesh.unlinked ? '<div class="empty">' + esc(T('%d devices without connection').replace('%d', mesh.unlinked)) + '</div>' : '') + '</div>';
  }

  const viewSeg = () => '<span class="seg">' + [['list', 'Devices'], ['mesh', 'Mesh']].map(v => '<button data-on="view" data-view="' + v[0] + '"' +
    (v[0] === view ? ' class="on"' : '') + '>' + esc(T(v[1])) + '</button>').join('') + '</span>';

  FB.render = (d, size) => {
    const hosts = d.hosts || [];
    const online = d.online ? Number(d.online.value) : hosts.filter(h => h.active).length;
    const total = d.total ? Number(d.total.value) : hosts.length;
    const kpi = '<div class="kpi"><div class="big">' + online + '<span class="muted" style="font-size:.5em"> / ' + total + '</span></div><div class="sub">' +
      esc(T('Network devices')) + ' ' + esc(T('online')) + '</div></div>';
    if (size === 'mini') return kpi;
    let group;
    if (view === 'mesh') {
      group = '<div class="group grow"><div class="ghead">' + viewSeg() +
        '<button class="btn" data-on="meshReload" title="' + esc(T('Refresh')) + '">' + icon('arrows-rotate') + '</button></div>' + meshView() + '</div>';
    } else {
      const list = hosts.filter(match);
      const def = size === 'compact' ? 10 : 50;
      const items = FB.slice('hosts', list, def);
      const seg = '<span class="seg">' + FILTERS.map(f => '<button data-on="filter" data-filter="' + f[0] + '"' + (f[0] === filter ? ' class="on"' : '') + '>' + esc(T(f[1])) + '</button>').join('') + '</span>';
      const tools = size === 'compact' ? '' : '<input type="search" data-search placeholder="' + esc(T('Search')) + '" value="' + esc(search) + '" style="width:140px">';
      group = '<div class="group grow"><div class="ghead">' + viewSeg() + seg + tools + FB.limitSelect('hosts', def, list.length) + '</div>' +
        (items.length ? '<ul class="list">' + items.map(h => row(h, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No devices')) + '</div>') + '</div>';
    }
    if (size === 'compact') return group;
    return '<div class="cols"><div class="col side">' + '<div class="head"></div>' + kpi + '</div><div class="col main">' + group + '</div></div>';
  };

  FB.handlers.view = el => {
    view = el.dataset.view;
    try { localStorage.setItem(VIEW_KEY, view); } catch (e) { /* ohne Speicher */ }
    FB.draw();
  };
  FB.handlers.meshReload = () => {
    loadMesh();
  };
  FB.onMessage = msg => {
    if (msg.type !== 'mesh') return;
    mesh = msg.data || { root: null, unlinked: 0 };
    meshLoading = false;
    if (view === 'mesh') FB.draw();
  };
  // Auf- und Zuklappen merken, damit es beim Neuzeichnen erhalten bleibt
  document.addEventListener('toggle', e => {
    const det = e.target;
    if (!det.matches || !det.matches('details[data-key]')) return;
    if (det.open) closed.delete(det.dataset.key); else closed.add(det.dataset.key);
  }, true);

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
