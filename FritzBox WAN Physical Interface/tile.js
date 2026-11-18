/* FritzBox WAN Physical Interface – Kachel: aktuelle Auslastung Download/Upload als Balken und, ab compact,
   der Verlauf der letzten Messpunkte als Diagramm. Die Zugangsart wird nur angezeigt (Umschalten unterbricht die Verbindung).
   Daten aus dem Online-Monitor je Zugang (Synchronisationsgruppe); melden mehrere Zugänge Daten, lässt sich umschalten.
   Messpunkte und Maximalwerte in Byte/s (laut AVM, live bestätigt), Anzeige in Mbit/s.
   Angezeigt wird nach Auswahl der Variablen der Instanz: Rate (Text), Auslastung (Balken), Summen (Verlauf). */
(function () {
  'use strict';
  const { T, esc } = FB;
  const mbit = bytes => (bytes * 8 / 1e6);
  const fmt = v => v >= 100 ? v.toFixed(0) : v >= 10 ? v.toFixed(1) : v.toFixed(2);
  // Bezeichnung der Zugänge (Modus der Synchronisationsgruppe), unbekannte Modi unverändert
  const MODES = { CABLE: 'Cable', DSL: 'DSL', NETIF: 'LAN', LTE: 'LTE', UMTS: 'UMTS', FIBER: 'Fiber', MOBILE: 'Mobile' };
  const label = g => MODES[String(g.mode).toUpperCase()] ? T(MODES[String(g.mode).toUpperCase()]) : (g.mode || g.name);
  let chosen = -1;

  // Flächendiagramm (SVG) einer Messreihe, Skala = Spitzenwert der Messreihe (bei normaler Last sonst nur eine flache Linie)
  function chart(points, color, title) {
    if (!points || !points.length) return '';
    const max = Math.max(...points, 1);
    const n = points.length;
    const xy = points.map((p, i) => [(n === 1 ? 100 : i * 100 / (n - 1)), 40 - (p / max) * 38]);
    const line = xy.map(p => p[0].toFixed(2) + ',' + p[1].toFixed(2)).join(' ');
    return '<div class="group" style="flex:1 1 0;min-height:0"><div class="ghead"><span>' + esc(title) + '</span><span>' + esc(T('max.')) + ' ' + fmt(mbit(max)) + ' Mbit/s</span></div>' +
      '<svg viewBox="0 0 100 40" preserveAspectRatio="none" style="width:100%;flex:1 1 0;min-height:12px">' +
      '<polygon points="0,40 ' + line + ' 100,40" fill="' + color + '" fill-opacity=".25"/>' +
      '<polyline points="' + line + '" fill="none" stroke="' + color + '" stroke-width="1.2" vector-effect="non-scaling-stroke"/></svg></div>';
  }
  // Aktuelle Werte je Richtung nach Auswahl der Variablen: Auslastung = Balken (ohne Rate mit Prozent), Rate = Mbit/s
  function meter(text, bytes, max, show) {
    if (!show.rate && !show.load) return '';
    const pct = max > 0 ? Math.min(100, bytes * 100 / max) : 0;
    const bar = show.load ? '<span class="bar"><i style="width:' + pct.toFixed(1) + '%"></i></span>' : '<span style="flex:1"></span>';
    const value = show.rate ? fmt(mbit(bytes)) + ' Mbit/s' : pct.toFixed(1) + ' %';
    return '<div class="head" style="padding-right:0"><span style="width:70px">' + esc(T(text)) + '</span>' + bar +
      '<span style="width:90px;text-align:right">' + esc(value) + '</span></div>';
  }

  FB.render = (d, size) => {
    const m = d.monitor || {};
    const groups = m.groups || [];
    // Zugänge mit Daten (Maximalwert oder Verkehr); der aktive Zugang ist vorgewählt
    const used = groups.map((g, i) => [g, i]).filter(([g]) => g.maxDown > 0 || g.maxUp > 0 || g.down.some(v => v > 0) || g.up.some(v => v > 0));
    if (!used.some(([, i]) => i === chosen)) chosen = typeof m.active === 'number' ? m.active : 0;
    const g = groups[chosen] || { down: [], up: [], maxDown: 0, maxUp: 0 };
    const seg = used.length > 1 && size !== 'mini'
      ? '<span class="seg">' + used.map(([u, i]) => '<button data-on="group" data-group="' + i + '"' + (i === chosen ? ' class="on"' : '') + '>' + esc(label(u)) + '</button>').join('') + '</span>'
      : '';
    // Die FritzBox liefert den neuesten Messpunkt zuerst
    const down = g.down || [], up = g.up || [];
    const head = '<div class="head">' + FB.badge(d.accessType) + FB.badge(d.linkStatus) + seg + '</div>';
    const show = Object.assign({ rate: true, load: true, history: true }, d.show || {});
    const meters = meter('Download', down[0] || 0, g.maxDown, show) + meter('Upload', up[0] || 0, g.maxUp, show);
    if (size === 'mini' || !show.history) return head + meters;
    if (!down.length && !up.length) return head + meters + '<div class="empty">' + esc(T('No data')) + '</div>';
    const rev = a => a.slice().reverse();
    return head + meters + chart(rev(down), 'var(--chart)', T('Download')) +
      chart(rev(up), 'color-mix(in srgb, var(--chart) 55%, var(--text))', T('Upload'));
  };

  FB.handlers.group = el => {
    chosen = Number(el.dataset.group);
    FB.draw();
  };
})();
