/* FritzBox WLAN – Kachel.
   mini = WLAN an/aus und Anzahl der Geräte, compact = zusätzlich Geräteliste, wide/full = Übersicht mit QR-Code/Passwort links.
   QR-Code und Passwort nur, wenn sie in der Instanz aktiviert sind. Klick auf den Signalbalken öffnet das Diagramm,
   wenn die Variable der Signalstärke geloggt wird; Klick auf den Namen das Diagramm des Zustands (verbunden), wenn geloggt,
   sonst das der Signalstärke. Ab wide Suche nach Name, IP- und MAC-Adresse. */
(function () {
  'use strict';
  const { T, esc, icon } = FB;
  let showPassword = false;
  let search = '';
  const match = h => !search || [h.name, h.ip, h.mac].some(x => String(x || '').toLowerCase().includes(search.toLowerCase()));

  function row(h, size) {
    const l2 = [h.ip, size === 'full' ? h.mac : '', h.speed ? h.speed + ' Mbit/s' : ''].filter(x => x).map(esc).join(' · ');
    const name = h.open ? '<button class="l1" data-on="open" data-id="' + h.open + '" title="' + esc(T('Show chart')) + '" style="text-align:left">' + esc(h.name) + '</button>'
      : '<span class="l1">' + esc(h.name) + '</span>';
    const bar = '<i style="width:' + Math.max(0, Math.min(100, h.signal)) + '%"></i>';
    // Signalstärke als Balken mit Wert in %; Tooltip auf der ganzen Fläche (Balken allein ist nur 6 px hoch)
    const title = (h.chart ? T('Show chart') : T('Signal')) + ': ' + h.signal + ' %';
    const inner = '<span class="bar">' + bar + '</span><span class="muted" style="min-width:38px;text-align:right">' + esc(h.signal) + ' %</span>';
    const signal = h.chart ? '<button data-on="open" data-id="' + h.chart + '" title="' + esc(title) + '" style="display:flex;align-items:center;gap:6px;width:100%">' + inner + '</button>'
      : '<span title="' + esc(title) + '" style="display:flex;align-items:center;gap:6px;width:100%">' + inner + '</span>';
    return '<li class="row"><span class="ico">' + icon('wifi') + '</span>' +
      '<span class="main">' + name + '<span class="l2">' + l2 + '</span></span>' +
      '<span class="side" style="width:' + (size === 'compact' ? 100 : 150) + 'px">' + signal + '</span></li>';
  }

  function hostList(d, size) {
    const hosts = (d.hosts || []).filter(h => size === 'compact' || match(h));
    const def = size === 'compact' ? 5 : 25;
    const items = FB.slice('hosts', hosts, def);
    const tools = size === 'compact' ? '' : '<input type="search" data-search placeholder="' + esc(T('Search')) + '" value="' + esc(search) + '" style="width:140px">';
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Devices')) + '</span>' + tools + FB.limitSelect('hosts', def, hosts.length) + '</div>' +
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
