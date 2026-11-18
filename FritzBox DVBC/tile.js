/* FritzBox DVB-C – Kachel: Senderlisten der FritzBox (TV HD, TV SD, Radio) mit Suche und Link kopieren (RTSP-Adresse).
   mini = Anzahl der Sender. Später: Abspielen-Button vor dem Kopieren (Link je Endgerät). */
(function () {
  'use strict';
  const { T, esc, icon } = FB;

  const LISTS = [['tvhd', 'TV HD', 'tv'], ['tvsd', 'TV SD', 'tv-retro'], ['radio', 'Radio', 'radio']];
  let list = '';
  let search = '';
  try { list = localStorage.getItem('fb-dvbc-list-' + FB.cfg.instance) || ''; } catch (e) { /* ohne Speicher */ }
  const match = s => !search || s.name.toLowerCase().includes(search.toLowerCase());

  function row(s, ico) {
    return '<li class="row"><span class="ico">' + icon(ico) + '</span>' +
      '<span class="main"><span class="l1">' + esc(s.name) + '</span></span><span class="side">' +
      '<button class="btn" data-on="copy" data-url="' + esc(s.url) + '" title="' + esc(T('Copy link')) + '">' + icon('copy') + '</button></span></li>';
  }

  FB.render = (d, size) => {
    const lists = d.lists || {};
    const total = LISTS.reduce((n, l) => n + (lists[l[0]] || []).length, 0);
    if (size === 'mini') {
      return '<div class="kpi"><div class="big">' + total + '</div><div class="sub">' + esc(T('Stations')) + '</div></div>';
    }
    if (!LISTS.some(l => l[0] === list)) list = (LISTS.find(l => (lists[l[0]] || []).length) || LISTS[0])[0];
    const cur = LISTS.find(l => l[0] === list);
    const all = (lists[list] || []).filter(s => size === 'compact' || match(s));
    const def = size === 'compact' ? 5 : 25;
    const items = FB.slice('stations', all, def);
    const seg = '<span class="seg">' + LISTS.map(l => '<button data-on="list" data-list="' + l[0] + '"' + (l[0] === list ? ' class="on"' : '') + '>' + esc(T(l[1])) + '</button>').join('') + '</span>';
    const tools = (size === 'compact' ? '' : '<input type="search" data-search placeholder="' + esc(T('Search')) + '" value="' + esc(search) + '" style="width:140px">') +
      '<button class="btn" data-act="TileStations" title="' + esc(T('Reload station list')) + '">' + icon('arrows-rotate') + '</button>';
    const empty = d.loaded === false ? T('Station list not available.') : T('No stations');
    return '<div class="group grow"><div class="ghead">' + seg + tools + FB.limitSelect('stations', def, all.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(s => row(s, cur[2])).join('') + '</ul>' : '<div class="empty">' + esc(empty) + '</div>') + '</div>';
  };

  FB.handlers.list = el => {
    list = el.dataset.list;
    try { localStorage.setItem('fb-dvbc-list-' + FB.cfg.instance, list); } catch (e) { /* ohne Speicher */ }
    FB.draw();
  };
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
