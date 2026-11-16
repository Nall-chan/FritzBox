/* FritzBox DSL-Schnittstelle – Kachel: Synchronisationsrate gegen maximal mögliche Rate (Balken), ab compact zusätzlich
   Störabstand und Dämpfung, ab wide die Fehlerzähler. Werte als formatierter Text der Statusvariablen. */
(function () {
  'use strict';
  const { T, esc } = FB;

  function rate(label, cur, max) {
    if (!cur) return '';
    const pct = max && max.value > 0 ? Math.min(100, cur.value * 100 / max.value) : 0;
    return '<div class="head" style="padding-right:0"><span style="width:70px">' + esc(T(label)) + '</span><span class="bar"><i style="width:' + pct.toFixed(1) + '%"></i></span>' +
      '<span style="text-align:right">' + esc(cur.text) + (max ? ' <span class="muted">' + esc(T('of')) + ' ' + esc(max.text) + '</span>' : '') + '</span></div>';
  }
  // Wertepaar Download/Upload als Zeile
  function pair(label, down, up) {
    if (!down && !up) return '';
    return '<li class="row"><span class="main"><span class="l1">' + esc(T(label)) + '</span></span><span class="side">' +
      (down ? '↓ ' + esc(down.text) : '') + (up ? '&nbsp;&nbsp;↑ ' + esc(up.text) : '') + '</span></li>';
  }
  const value = v => v ? '<li class="row"><span class="main"><span class="l1">' + esc(v.name) + '</span></span><span class="side">' + esc(v.text) + '</span></li>' : '';

  FB.render = (d, size) => {
    if (!d.downRate && !d.upRate) return '<div class="empty">' + esc(T('No data')) + '</div>';
    const head = '<div class="head">' + FB.badge(d.status) + '</div>';
    const rates = rate('Download', d.downRate, d.downMax) + rate('Upload', d.upRate, d.upMax);
    if (size === 'mini') return head + rates;
    let list = pair('Noise margin', d.downMargin, d.upMargin) + pair('Attenuation', d.downAtt, d.upAtt);
    if (size !== 'compact') list += value(d.fec) + value(d.crc) + value(d.erroredSecs) + value(d.severeSecs);
    return head + rates + '<ul class="list">' + list + '</ul>';
  };
})();
