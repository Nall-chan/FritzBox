/* FritzBox DECT und Powerline – gemeinsame Kachel.
   mini = Geräte aktiv/gesamt, sonst Gerätekarten mit Zustand, Modell und Kennzeichen „Update verfügbar“.
   Modell und Update erscheinen nur, wenn die Variablen in der Instanz angelegt sind; „Update starten“ nur bei verfügbarem Update. */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;

  function card(x) {
    const on = !!x.device.value;
    const update = x.update && x.update.value;
    return '<li class="row' + (on ? '' : ' off') + '"><span class="dot' + (on ? ' on' : '') + '" title="' + esc(T(on ? 'active' : 'inactive')) + '"></span>' +
      (x.device.icon ? '<span class="ico">' + icon(x.device.icon) + '</span>' : '') +
      '<span class="main"><button class="l1" data-on="open" data-id="' + x.device.id + '" style="text-align:left">' + esc(x.device.name) + '</button>' +
      (x.model ? '<span class="l2">' + esc(x.model.text) + '</span>' : '') + '</span>' +
      '<span class="side">' + (update ? '<span class="badge accent">' + esc(T('Update available')) + '</span>' +
        '<button class="btn" data-act="TileDeviceUpdate" data-value="' + val(x.key) + '" data-confirm="' + esc(T('Start the update of %s?').replace('%s', x.device.name)) +
        '" title="' + esc(T('Start update')) + '">' + icon('download') + '</button>' : '') + '</span></li>';
  }

  FB.render = (d, size) => {
    const devices = d.devices || [];
    const active = devices.filter(x => x.device.value).length;
    const updates = devices.filter(x => x.update && x.update.value).length;
    if (size === 'mini') {
      return '<div class="kpi"><div class="big">' + active + '<span class="muted" style="font-size:.5em"> / ' + devices.length + '</span></div><div class="sub">' +
        esc(T('Devices')) + (updates ? ' · <span class="badge accent">' + updates + ' ' + esc(T('Update available')) + '</span>' : '') + '</div></div>';
    }
    const def = size === 'compact' ? 10 : 50;
    const items = FB.slice('devices', devices, def);
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Devices')) + '</span>' + FB.limitSelect('devices', def, devices.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(card).join('') + '</ul>' : '<div class="empty">' + esc(T('No devices')) + '</div>') + '</div>';
  };

  FB.handlers.open = el => FB.open(Number(el.dataset.id));
})();
