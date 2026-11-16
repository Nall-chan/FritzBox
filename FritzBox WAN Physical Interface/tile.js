/* FritzBox WAN Physical Interface – Kachel: aktuelle Auslastung Download/Upload als Balken und, ab compact,
   der Verlauf der letzten Messpunkte als Diagramm. Die Zugangsart wird nur angezeigt (Umschalten unterbricht die Verbindung).
   Annahme: Messpunkte in Byte/s (siehe Modul), Anzeige in Mbit/s. */
(function () {
  'use strict';
  const { T, esc } = FB;
  const mbit = bytes => (bytes * 8 / 1e6);
  const fmt = v => v >= 100 ? v.toFixed(0) : v >= 10 ? v.toFixed(1) : v.toFixed(2);

  // Flächendiagramm (SVG) einer Messreihe, Skala = Spitzenwert der Messreihe (bei normaler Last sonst nur eine flache Linie)
  function chart(points, color, title) {
    if (!points || !points.length) return '';
    const max = Math.max(...points, 1);
    const n = points.length;
    const xy = points.map((p, i) => [(n === 1 ? 100 : i * 100 / (n - 1)), 40 - (p / max) * 38]);
    const line = xy.map(p => p[0].toFixed(2) + ',' + p[1].toFixed(2)).join(' ');
    return '<div class="group" style="flex:1;min-height:70px"><div class="ghead"><span>' + esc(title) + '</span><span>' + esc(T('max.')) + ' ' + fmt(mbit(max)) + ' Mbit/s</span></div>' +
      '<svg viewBox="0 0 100 40" preserveAspectRatio="none" style="width:100%;flex:1;min-height:30px">' +
      '<polygon points="0,40 ' + line + ' 100,40" fill="' + color + '" fill-opacity=".25"/>' +
      '<polyline points="' + line + '" fill="none" stroke="' + color + '" stroke-width="1.2" vector-effect="non-scaling-stroke"/></svg></div>';
  }
  // Balken der aktuellen Auslastung im Verhältnis zur maximalen Bitrate
  function meter(label, bytes, maxBit) {
    const pct = maxBit > 0 ? Math.min(100, bytes * 8 * 100 / maxBit) : 0;
    return '<div class="head" style="padding-right:0"><span style="width:70px">' + esc(T(label)) + '</span><span class="bar"><i style="width:' + pct.toFixed(1) + '%"></i></span>' +
      '<span style="width:90px;text-align:right">' + fmt(mbit(bytes)) + ' Mbit/s</span></div>';
  }

  FB.render = (d, size) => {
    const m = d.monitor || {};
    // Die FritzBox liefert den neuesten Messpunkt zuerst
    const down = m.down || [], up = m.up || [];
    const head = '<div class="head">' + FB.badge(d.accessType) + FB.badge(d.linkStatus) + '</div>';
    const meters = meter('Download', down[0] || 0, m.maxDown || 0) + meter('Upload', up[0] || 0, m.maxUp || 0);
    if (size === 'mini') return head + meters;
    if (!down.length && !up.length) return head + meters + '<div class="empty">' + esc(T('No data')) + '</div>';
    const rev = a => a.slice().reverse();
    return head + meters + chart(rev(down), 'var(--chart)', T('Download')) +
      chart(rev(up), 'color-mix(in srgb, var(--chart) 55%, var(--text))', T('Upload'));
  };
})();
