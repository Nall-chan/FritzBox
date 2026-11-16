/* FritzBox Dateifreigaben – Kachel: Liste der Freigaben mit Zugriffen und Ablauf, Link kopieren und Löschen.
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

  // Link in die Zwischenablage (im iframe der Visu ggf. nicht erlaubt, dann über ein Textfeld)
  FB.handlers.copy = el => {
    const url = el.dataset.url;
    const done = () => FB.notice(T('Link copied'));
    const fallback = () => {
      const t = document.createElement('textarea');
      t.value = url;
      document.body.appendChild(t);
      t.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* nicht möglich */ }
      t.remove();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, fallback); else fallback();
  };
})();
