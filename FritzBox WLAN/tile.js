/* FritzBox WLAN – Kachel.
   mini = WLAN an/aus und Anzahl der Geräte, compact = zusätzlich Geräteliste, wide/full = Übersicht mit QR-Code/Passwort links.
   QR-Code und Passwort nur, wenn sie in der Instanz aktiviert sind. Klick auf den Signalbalken öffnet das Diagramm,
   wenn die Variable der Signalstärke geloggt wird. */
(function () {
  'use strict';
  const { T, esc, icon } = FB;
  let showPassword = false;

  function row(h, size) {
    const l2 = [h.ip, size === 'full' ? h.mac : '', h.speed ? h.speed + ' Mbit/s' : ''].filter(x => x).map(esc).join(' · ');
    const name = h.var ? '<button class="l1" data-on="open" data-id="' + h.var + '" title="' + esc(T('Open variable')) + '" style="text-align:left">' + esc(h.name) + '</button>'
      : '<span class="l1">' + esc(h.name) + '</span>';
    const bar = '<i style="width:' + Math.max(0, Math.min(100, h.signal)) + '%"></i>';
    const signal = h.chart ? '<button class="bar" data-on="open" data-id="' + h.chart + '" title="' + esc(T('Show chart')) + ': ' + h.signal + ' %">' + bar + '</button>'
      : '<span class="bar" title="' + esc(T('Signal')) + ': ' + h.signal + ' %">' + bar + '</span>';
    return '<li class="row"><span class="ico">' + icon('wifi') + '</span>' +
      '<span class="main">' + name + '<span class="l2">' + l2 + '</span></span>' +
      '<span class="side" style="width:' + (size === 'compact' ? 60 : 110) + 'px">' + signal + '</span></li>';
  }

  function hostList(d, size) {
    const hosts = d.hosts || [];
    const def = size === 'compact' ? 5 : 25;
    const items = FB.slice('hosts', hosts, def);
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Devices')) + '</span>' + FB.limitSelect('hosts', def, hosts.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(h => row(h, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No devices')) + '</div>') + '</div>';
  }

  // QR-Code (PNG der Instanz) und Passwort, nur wenn in der Instanz aktiviert
  function access(d) {
    let html = '';
    if (d.qrcode) {
      html += '<img src="data:image/png;base64,' + d.qrcode + '" alt="QR" title="' + esc(T('Scan to connect')) +
        '" style="max-width:100%;max-height:180px;align-self:center;border-radius:8px;background:#fff;padding:6px">';
    }
    if (d.password) {
      html += '<div class="head" style="padding-right:0"><span class="muted">' + esc(T('Password')) + '</span><span class="title" style="font-family:monospace">' +
        (showPassword ? esc(d.password.value) : '••••••••') + '</span><button class="btn" data-on="pw" title="' + esc(T('Show password')) + '">' +
        icon(showPassword ? 'eye-slash' : 'eye') + '</button></div>';
    }
    return html;
  }

  FB.render = (d, size) => {
    const count = d.count ? Number(d.count.value) : (d.hosts || []).length;
    const ssid = d.ssid ? d.ssid.value : '';
    const head = '<div class="head">' + FB.toggle(d.enable, ssid || T('WLAN')) + (d.timeRemain && d.enable && d.enable.value ? FB.badge(d.timeRemain) : '') + '</div>';
    const kpi = '<div class="kpi"><div class="big">' + count + '</div><div class="sub">' + esc(T('Devices')) + '</div></div>';
    if (size === 'mini') return head + kpi;
    if (size === 'compact') return head + hostList(d, size);
    return '<div class="cols"><div class="col side">' + head + (d.qrcode || d.password ? access(d) : kpi) + '</div><div class="col main">' + hostList(d, size) + '</div></div>';
  };

  FB.handlers.open = el => FB.open(Number(el.dataset.id));
  FB.handlers.pw = () => {
    showPassword = !showPassword;
    FB.draw();
  };
})();
