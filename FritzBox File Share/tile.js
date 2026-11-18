/* FritzBox Dateifreigaben – Kachel: Liste der Freigaben mit Zugriffen und Ablauf, Link öffnen, Link kopieren und Löschen.
   mini = Anzahl der Freigaben. */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;

  const expires = ts => ts ? new Date(ts * 1000).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : T('Never');

  function row(s, size) {
    const l2 = [size !== 'compact' ? s.path : '', T('Accesses') + ' ' + s.count + (s.limit ? '/' + s.limit : ''), T('Expires') + ' ' + expires(s.expires)]
      .filter(x => x).map(esc).join(' · ');
    return '<li class="row' + (s.valid ? '' : ' off') + '"><span class="ico">' + icon(s.dir ? 'folder' : 'file') + '</span>' +
      '<span class="main"><span class="l1">' + esc(s.name || s.path) + (s.valid ? '' : ' <span class="badge error">' + esc(T('Invalid')) + '</span>') + '</span>' +
      '<span class="l2">' + l2 + '</span></span><span class="side">' +
      // Öffnen als echter Link (neues Fenster), das lässt der Browser auch aus dem iframe der Visu zu
      (s.url ? '<a class="btn" data-on="opened" href="' + esc(s.url) + '" target="_blank" rel="noopener noreferrer" title="' + esc(T('Open link')) + '">' + icon('arrow-up-right-from-square') + '</a>' : '') +
      (s.url ? '<button class="btn" data-on="copy" data-url="' + esc(s.url) + '" title="' + esc(T('Copy link')) + '">' + icon('copy') + '</button>' : '') +
      '<button class="btn danger" data-act="TileDeleteShare" data-value="' + val(s.id) + '" data-confirm="' + esc(T('Delete the file share %s?').replace('%s', s.name || s.path)) +
      '" title="' + esc(T('Delete')) + '">' + icon('trash') + '</button></span></li>';
  }

  FB.render = (d, size) => {
    const shares = d.shares || [];
    if (size === 'mini') {
      return '<div class="kpi"><div class="big">' + shares.length + '</div><div class="sub">' + esc(T('File shares')) + '</div></div>';
    }
    const def = size === 'compact' ? 5 : 25;
    const items = FB.slice('shares', shares, def);
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('File shares')) + '</span>' + FB.limitSelect('shares', def, shares.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(s => row(s, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No file shares')) + '</div>') + '</div>';
  };
  // Nach dem Öffnen (neues Fenster, Standardverhalten des Links) die Freigaben neu laden, damit der Zugriffszähler stimmt;
  // verzögert, die FritzBox zählt den Zugriff erst beim Abruf im neuen Fenster
  FB.handlers.opened = () => setTimeout(() => FB.act('RefreshState', true), 5000);
  // Link kopieren: gemeinsamer Baustein FB.handlers.copy (libs/tile/tile.js)
})();
