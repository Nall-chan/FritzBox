/* FritzBox Anrufmonitor – Kachel: aktive Gespräche mit Zustand (klingelt, wählt, verbunden) und laufender Dauer.
   mini = klingelnder bzw. erster Anruf, sonst „Keine aktiven Gespräche“.
   Vollbild bei Anruf (Eigenschaft OpenTileOnRing): Bei einem eingehenden Anruf sendet die Instanz ein Ereignis an die Kachel;
   jede gerade angezeigte Kachel öffnet sich daraufhin selbst maximiert (openObject). Die maximierte Kachel zeigt den Anrufer
   groß und zentriert mit Zustand und Dauer. Sobald der Anruf nicht mehr klingelt (angenommen oder beendet), schließt sie sich
   wieder (Zurück im Browserverlauf). Verkleinert der Nutzer die Kachel vorher, zeigt sie wieder die Liste. */
(function () {
  'use strict';
  const { T, esc, icon } = FB;
  // Zeitversatz zwischen Server und Browser, damit die laufende Dauer stimmt
  let offset = 0;
  const now = () => Math.floor(Date.now() / 1000) - offset;
  const clock = s => {
    s = Math.max(0, s);
    const h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, x = s % 60;
    return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0');
  };
  // Großanzeige: Eine neu geöffnete (maximierte) Kachel übernimmt sie aus ihren ersten Daten, wenn sie frisch ist
  // (POPUP_AGE Sekunden); spätere Daten ändern daran nichts. Sie endet, wenn der Anruf nicht mehr klingelt oder die
  // Kachel verkleinert wird (Größenstufe sinkt).
  const POPUP_AGE = 15;
  const RANK = { mini: 0, compact: 1, wide: 2, full: 3 };
  let first = true;
  let popup = null;
  let popupRank = 0;
  // Hash der Visu beim Öffnen per openObject; nur dann wird per Zurück geschlossen
  let closeHash = null;
  const topHash = () => {
    try { return window.top.location.hash; } catch (e) { return null; }
  };
  const showPopup = (p, size) => {
    popup = p;
    popupRank = RANK[size] || 0;
  };
  // Anruf klingelt nicht mehr (angenommen, beendet): Großanzeige beenden, per Popup geöffnete Kachel schließen
  const endPopup = calls => {
    if (!popup) return;
    const c = calls.find(x => x.id === popup.id);
    if (c && c.status === 'RING') return;
    popup = null;
    if (closeHash !== null && topHash() === closeHash) {
      closeHash = null;
      history.back();
    }
  };

  const state = c => c.status === 'CONNECT' ? { text: 'Connected', icon: 'phone-volume', cls: 'accent' }
    : c.in ? { text: 'Ringing', icon: 'phone-intercom', cls: 'error' } : { text: 'Dialing', icon: 'phone-arrow-up-right', cls: '' };

  function row(c, size) {
    const s = state(c);
    const l2 = [c.name ? c.remote : '', c.local ? (c.in ? T('on') : T('via')) + ' ' + c.local : '', size !== 'compact' && c.device ? c.device : '']
      .filter(x => x).map(esc).join(' · ');
    return '<li class="row' + (c.status === 'RING' ? ' new' : '') + '">' +
      '<span class="ico ' + s.cls + '" title="' + esc(T(c.in ? 'Incoming' : 'Outgoing')) + '">' + icon(s.icon) + '</span>' +
      '<span class="main"><span class="l1">' + esc(c.name || c.remote) + (c.found ? ' <span class="muted" title="' + esc(T('Found by reverse search')) + '">' + icon('magnifying-glass') + '</span>' : '') +
      '</span><span class="l2">' + l2 + '</span></span>' +
      '<span class="side"><span class="badge ' + s.cls + '">' + esc(T(s.text)) + '</span>' +
      '<span class="time" data-since="' + (c.since || 0) + '">' + (c.since ? clock(now() - c.since) : '') + '</span></span></li>';
  }

  // Großanzeige des klingelnden Anrufs: Anrufer, Rufnummer, eigene Rufnummer, Zustand und Dauer
  function bigView(c) {
    const s = state(c);
    return '<div class="kpi popup alert">' +
      '<div class="big">' + icon(s.icon) + '</div>' +
      '<div class="ptitle">' + esc(c.name || c.remote) + '</div>' +
      ((c.name && c.remote) || c.local ? '<div class="ptext">' + [c.name ? c.remote : '', c.local ? T('on') + ' ' + c.local : '']
        .filter(x => x).map(esc).join(' · ') + '</div>' : '') +
      '<div class="pstate"><span class="badge ' + s.cls + '">' + esc(T(s.text)) + '</span>' +
      '<span class="time" data-since="' + (c.since || 0) + '">' + (c.since ? clock(now() - c.since) : '') + '</span></div></div>';
  }

  FB.render = (d, size) => {
    offset = d.now ? Math.floor(Date.now() / 1000) - d.now : 0;
    const calls = d.calls || [];
    // Nur die ersten Daten einer per openObject geöffneten Kachel zählen: Die Visu zeigt dann die Instanz selbst
    // (Adresse endet auf /<InstanzID>). Beim Schließen baut die Visu die Kachel im Raster neu auf; die darf die noch
    // frische Anzeige nicht übernehmen, sonst ginge sie gleich noch einen Schritt zurück.
    if (first) {
      first = false;
      const p = d.popup;
      const hash = topHash();
      const opened = hash !== null && hash.endsWith('/' + FB.cfg.instance);
      const ringing = p && calls.some(x => x.id === p.id && x.status === 'RING');
      if (p && d.now && (d.now - p.time) <= POPUP_AGE && opened && ringing) {
        showPopup(p, size);
        closeHash = hash;
      }
    }
    endPopup(calls);
    const rank = RANK[size] || 0;
    if (popup && rank < popupRank) popup = null; else if (popup) popupRank = rank;
    const ringing = popup ? calls.find(x => x.id === popup.id) : null;
    if (ringing && size !== 'mini') return bigView(ringing);
    if (size === 'mini') {
      const c = calls.find(x => x.status === 'RING') || calls[0];
      if (!c) return '<div class="kpi"><div class="big">' + icon('phone') + '</div><div class="sub">' + esc(T('No active calls')) + '</div></div>';
      const s = state(c);
      return '<div class="kpi' + (c.status === 'RING' ? ' alert' : '') + '"><div class="big">' + icon(s.icon) + '</div><div>' + esc(c.name || c.remote) +
        '</div><div class="sub">' + esc(T(s.text)) + '</div></div>';
    }
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Active calls')) + '</span></div>' +
      (calls.length ? '<ul class="list">' + calls.map(c => row(c, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No active calls')) + '</div>') + '</div>';
  };

  // Ereignis „eingehender Anruf“: schon maximiert -> Großanzeige hier (ohne späteres Zurück), sonst die Kachel maximiert
  // öffnen (die neue Kachel übernimmt die Anzeige aus ihren ersten Daten, die Kachel im Raster bleibt bei der Liste)
  FB.onMessage = msg => {
    if (msg.type !== 'popup' || !msg.data) return;
    if (FB.size === 'full') {
      showPopup(msg.data, FB.size);
      FB.draw();
    } else {
      FB.open(Number(FB.cfg.instance));
    }
  };

  // Laufende Dauer jede Sekunde nachführen, ohne neu zu zeichnen
  setInterval(() => {
    document.querySelectorAll('[data-since]').forEach(el => {
      const since = Number(el.dataset.since);
      if (since > 0) el.textContent = clock(now() - since);
    });
  }, 1000);
})();
