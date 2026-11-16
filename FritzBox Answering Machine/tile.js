/* FritzBox Anrufbeantworter – Kachel.
   mini = Anzahl neuer Nachrichten, compact = zusätzlich Schalter und Nachrichtenliste,
   wide/full = Übersicht links, Nachrichtenliste rechts.
   Wiedergabe im Browser über den signierten Webhook der Instanz (die Audiodatei läuft nicht über den Datenaustausch);
   eine neue Nachricht wird erst nach vollständigem Abspielen als gelesen markiert. */
(function () {
  'use strict';
  const { T, esc, icon, val } = FB;
  // Ein Player je Kachel; bleibt beim Neuzeichnen erhalten
  const player = { index: -1, audio: null };

  // Dauer wie die FritzBox: Stunden:Minuten, aufgerundet; 0:01 = „< 1 min“
  const duration = d => {
    const m = /^(\d+):(\d{2})$/.exec(d || '');
    if (!m) return d || '';
    const h = Number(m[1]), min = Number(m[2]);
    if (h > 0) return T('%d h %d min').replace('%d', h).replace('%d', min);
    return min <= 1 ? T('< 1 min') : T('%d min').replace('%d', min);
  };
  const clock = s => {
    s = Math.floor(s || 0);
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  };
  const caller = m => m.name || m.number || T('unknown');

  function row(m, size) {
    const playing = player.index === m.index;
    const paused = playing && player.audio && player.audio.paused;
    const l2 = [m.date, m.name ? m.number : ''].concat(size === 'full' && m.called ? [T('Called number') + ' ' + m.called] : [])
      .filter(x => x).map(esc).join(' · ');
    return '<li class="row' + (m.new ? ' new' : '') + '">' +
      '<span class="ico' + (m.new ? ' accent' : '') + '" title="' + esc(T(m.new ? 'New' : 'Played')) + '">' + icon(m.new ? 'voicemail' : 'check') + '</span>' +
      '<span class="main"><span class="l1">' + esc(caller(m)) + '</span><span class="l2">' + l2 + '</span></span>' +
      '<span class="side"><span class="time" data-time="' + m.index + '">' + (playing && player.audio ? clock(player.audio.currentTime) : esc(duration(m.duration))) + '</span>' +
      (m.play ? '<button class="btn" data-on="play" data-index="' + m.index + '" title="' + esc(T('Play')) + '">' + icon(playing && !paused ? 'pause' : 'play') + '</button>' : '') +
      (m.new ? '<button class="btn" data-act="TileMark" data-value="' + val(m.index) + '" title="' + esc(T('Mark as read')) + '">' + icon('check') + '</button>' : '') +
      '<button class="btn danger" data-act="TileDelete" data-value="' + val(m.index) + '" data-confirm="' + esc(T('Delete message?')) + '" title="' + esc(T('Delete')) + '">' + icon('trash') + '</button>' +
      '</span></li>';
  }

  function messages(d, size) {
    const all = d.messages || [];
    const items = FB.slice('messages', all, size === 'compact' ? 5 : 25);
    return '<div class="group grow"><div class="ghead"><span>' + esc(T('Messages')) + '</span>' + FB.limitSelect('messages', size === 'compact' ? 5 : 25, all.length) + '</div>' +
      (items.length ? '<ul class="list">' + items.map(m => row(m, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No messages')) + '</div>') + '</div>';
  }

  FB.render = (d, size) => {
    const newCount = d.newMessages ? Number(d.newMessages.value) : 0;
    const kpi = '<div class="kpi' + (newCount > 0 ? ' alert' : '') + '"><div class="big">' + newCount + '</div><div class="sub">' + esc(T('New messages')) +
      (d.totalMessages ? ' · ' + esc(d.totalMessages.value) + ' ' + esc(T('total')) : '') + '</div></div>';
    if (size === 'mini') {
      return kpi;
    }
    const head = '<div class="head">' + FB.toggle(d.enable, T('Answering machine')) +
      (size === 'compact' && newCount > 0 ? '<span class="badge accent">' + newCount + ' ' + esc(T('New')) + '</span>' : '') + '</div>';
    if (size === 'compact') {
      return head + messages(d, size);
    }
    return '<div class="cols"><div class="col side">' + head + kpi + '</div><div class="col main">' + messages(d, size) + '</div></div>';
  };

  // Wiedergabe: gleiche Nachricht = Pause/Fortsetzen, andere Nachricht = vorherige beenden
  FB.handlers.play = el => {
    const index = Number(el.dataset.index);
    const m = (FB.data.messages || []).find(x => x.index === index);
    if (!m) return;
    if (player.index === index && player.audio) {
      if (player.audio.paused) player.audio.play(); else player.audio.pause();
      FB.draw();
      return;
    }
    if (player.audio) player.audio.pause();
    const audio = new Audio(m.play);
    player.index = index;
    player.audio = audio;
    audio.ontimeupdate = () => {
      const t = document.querySelector('[data-time="' + index + '"]');
      if (t) t.textContent = clock(audio.currentTime) + (isFinite(audio.duration) ? ' / ' + clock(audio.duration) : '');
    };
    audio.onended = () => {
      player.index = -1;
      player.audio = null;
      if (m.new) FB.act('TileMark', index); else FB.draw();
    };
    audio.onerror = () => {
      player.index = -1;
      player.audio = null;
      FB.notice(T('Playback failed.'));
      FB.draw();
    };
    audio.play().catch(() => {});
    FB.draw();
  };
})();
