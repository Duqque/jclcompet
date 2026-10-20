/* Écran TV — lecture seule. Interroge le serveur (polling) et anime le chronomètre localement
   à partir des horodatages serveur : un rechargement ne réinitialise jamais le temps. */
(function () {
  'use strict';
  const body = document.body;
  const api = body.dataset.api;
  const $ = (s, root = document) => root.querySelector(s);
  const rows = { a: $('[data-row="a"]'), b: $('[data-row="b"]') };
  let state = JSON.parse(document.getElementById('tv-initial').textContent);
  let offset = state.server_now_ms - Date.now();
  let refresh = (state.display && state.display.refresh_ms) || 1000;
  let failures = 0;
  let lastKey = '';

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const now = () => Date.now() + offset;
  const fmt = (ms) => { const t = Math.max(0, Math.ceil(ms / 1000)); return Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0'); };
  const initials = (s) => (s || '').split(/[\s-]+/).filter((w) => w.length > 2).slice(0, 3).map((w) => w[0]).join('').toUpperCase() || '?';

  function elapsed(clock) {
    let e = clock.elapsed_ms;
    if (clock.running && clock.started_at_ms) e += Math.max(0, now() - clock.started_at_ms);
    return e;
  }

  function renderFighter(side, f, cur) {
    const row = rows[side];
    const name = $('[data-name]', row);
    const club = $('[data-club]', row);
    const logo = $('[data-logo-slot]', row);
    if (!f) {
      name.innerHTML = '<span class="waiting-msg">' + (side === 'a' ? 'En attente' : '&nbsp;') + '</span>';
      club.innerHTML = '&nbsp;'; logo.innerHTML = ''; return;
    }
    name.innerHTML = '<span>' + esc(f.first_name) + '</span><span>' + esc(f.last_name) + '</span>';
    club.textContent = f.club || '';
    const key = f.club_logo || initials(f.club);
    if (logo.dataset.key !== key) {
      logo.dataset.key = key;
      logo.innerHTML = f.club_logo ? '<img src="' + esc(f.club_logo) + '" alt="">' : '<span class="mono">' + esc(initials(f.club)) + '</span>';
    }
    const digits = { ippon: f.ippon, wazaari: f.wazaari, yuko: f.yuko };
    ['ippon', 'wazaari', 'yuko'].forEach((k) => { const d = $('[data-d="' + k + '"]', row); d.textContent = digits[k]; d.classList.remove('blink'); d.dataset.base = digits[k]; });
    const max = (state.display && state.display.shido_max) || 3;
    const sh = $('[data-shidos]', row);
    if (sh.children.length !== max) sh.innerHTML = '<i></i>'.repeat(max);
    [...sh.children].forEach((el, i) => el.classList.toggle('on', i < f.shido));
    sh.classList.toggle('hansoku', !!f.hansoku);
    const win = cur && cur.winner_side === side && (cur.status === 'finished' || cur.status === 'validated');
    row.classList.toggle('winner', !!win);
    $('[data-victory]', row).hidden = !win;
  }

  function render() {
    const cur = state.current;
    body.classList.toggle('blue', state.display && state.display.fighter_color === 'blue');
    body.classList.toggle('waiting', !cur);
    const cat = $('[data-cat]'), sub = $('[data-sub]');
    if (cur) {
      cat.textContent = cur.category;
      const parts = [];
      if (cur.weight_label) parts.push(cur.weight_label + ' kg');
      else parts.push(cur.pool_label.replace(/^.*· /, ''));
      parts.push((cur.phase === 'final' ? 'Barrage ' : 'Combat ') + cur.pool_position + '/' + cur.pool_bouts);
      if (state.mode === 'next') parts.push('Prochain combat');
      sub.textContent = parts.join(' | ');
    } else {
      cat.textContent = 'Tapis ' + state.tatami.number;
      sub.textContent = state.competition.name;
    }
    renderFighter('a', cur ? cur.a : null, cur);
    renderFighter('b', cur ? cur.b : null, cur);
    const next = $('[data-next]');
    const list = (state.next || []).filter((n) => !cur || n.id !== cur.id).slice(0, 2);
    next.innerHTML = list.length
      ? '<strong>À suivre</strong>' + list.map((n) => '<span class="item">' + esc(n.a.first_name + ' ' + n.a.last_name) + ' / ' + esc(n.b.first_name + ' ' + n.b.last_name) + ' <span class="cat">· ' + esc(n.category) + (n.weight_label ? ' ' + esc(n.weight_label) + ' kg' : '') + '</span></span>').join('')
      : '<strong>Tapis ' + state.tatami.number + '</strong><span class="item">' + esc(state.competition.name) + '</span>';
  }

  // Animation : chronomètre + osaekomi (20 s, seuils configurables), chiffre concerné clignotant.
  function tick() {
    const cur = state.current;
    const clock = $('[data-clock]'), label = $('[data-clock-label]');
    if (!cur) { clock.textContent = ''; label.textContent = ''; ['a', 'b'].forEach((s) => { $('[data-osae]', rows[s]).hidden = true; rows[s].classList.remove('osae-on'); }); requestAnimationFrame(tick); return; }
    const c = cur.clock;
    let text, lab = '';
    if (cur.status === 'finished' || cur.status === 'validated') {
      text = c.golden_score ? fmt(c.elapsed_ms) : fmt(Math.max(0, c.duration_ms - (c.final_elapsed_ms || c.elapsed_ms)));
      lab = c.golden_score ? 'Golden score' : '';
    } else if (c.golden_score) {
      const e = elapsed(c);
      text = c.golden_score_mode === 'limited' && c.golden_score_ms ? fmt(c.golden_score_ms - e) : fmt(e);
      lab = 'Golden score';
    } else {
      text = fmt(c.duration_ms - elapsed(c));
      if (state.mode === 'paused' && c.elapsed_ms > 0) lab = 'Matte';
    }
    clock.textContent = text;
    clock.classList.toggle('gs', !!c.golden_score);
    clock.classList.toggle('matte', state.mode === 'paused');
    label.textContent = lab;

    const th = (state.display && state.display.osaekomi) || { yuko: 5, wazaari: 10, ippon: 20 };
    const useYuko = !state.display || state.display.use_yuko !== false;
    ['a', 'b'].forEach((s) => {
      const box = $('[data-osae]', rows[s]);
      const o = cur.osaekomi;
      ['ippon', 'wazaari', 'yuko'].forEach((k) => { const d = $('[data-d="' + k + '"]', rows[s]); d.classList.remove('blink'); d.textContent = d.dataset.base; });
      if (!o || o.side !== s || cur.status !== 'in_progress') { box.hidden = true; rows[s].classList.remove('osae-on'); return; }
      box.hidden = false;
      rows[s].classList.add('osae-on');
      const held = Math.max(0, now() - o.started_at_ms);
      const sec = held / 1000;
      $('[data-osae-sec]', box).textContent = Math.floor(sec) + ' s';
      $('[data-osae-fill]', box).style.width = Math.min(100, (sec / th.ippon) * 100) + '%';
      [['yuko', th.yuko], ['wazaari', th.wazaari], ['ippon', th.ippon]].forEach(([k, v]) => {
        const t = $('[data-tick="' + k + '"]', box);
        if (k !== 'ippon') t.style.left = (v / th.ippon) * 100 + '%'; else t.style.left = '100%';
        t.classList.toggle('passed', sec >= v);
        t.hidden = k === 'yuko' && !useYuko;
      });
      // Le chiffre qui va changer clignote dès que son seuil est franchi.
      let k = null;
      if (sec >= th.ippon) k = 'ippon'; else if (sec >= th.wazaari) k = 'wazaari'; else if (useYuko && sec >= th.yuko) k = 'yuko';
      if (k) {
        const d = $('[data-d="' + k + '"]', rows[s]);
        d.textContent = parseInt(d.dataset.base, 10) + 1;
        d.classList.add('blink');
      }
    });
    requestAnimationFrame(tick);
  }

  async function poll() {
    const t0 = Date.now();
    try {
      const res = await fetch(api, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'omit' });
      if (res.status === 403) {
        const d = await res.json().catch(() => ({}));
        document.querySelector('.tv-screen').innerHTML = '<div class="tv-denied" style="grid-row:1/-1"><h1>Tapis ' + esc(state.tatami.number) + '</h1><p>' + esc(d.error || 'Affichage désactivé.') + '</p></div>';
        setTimeout(() => location.reload(), 30000);
        return;
      }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const rtt = Date.now() - t0;
      offset = data.server_now_ms + rtt / 2 - Date.now();
      state = data;
      refresh = (data.display && data.display.refresh_ms) || 1000;
      const key = JSON.stringify([data.mode, data.current, data.next.map((n) => n.id)]);
      if (key !== lastKey) { lastKey = key; render(); }
      failures = 0;
      $('[data-status]').hidden = true;
    } catch (e) {
      failures++;
      if (failures >= 3) { const s = $('[data-status]'); s.hidden = false; s.textContent = 'Connexion perdue — nouvel essai…'; }
    }
    setTimeout(poll, failures ? Math.min(5000, refresh * (1 + failures)) : refresh);
  }

  // Plein écran, curseur masqué, écran maintenu allumé.
  const fsBtn = $('[data-fullscreen]');
  let uiTimer = null;
  document.addEventListener('mousemove', () => {
    body.classList.add('show-ui'); body.classList.remove('idle-cursor');
    clearTimeout(uiTimer);
    uiTimer = setTimeout(() => { body.classList.remove('show-ui'); body.classList.add('idle-cursor'); }, 2500);
  });
  const toggleFs = () => { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen && document.documentElement.requestFullscreen().catch(() => {}); };
  fsBtn.addEventListener('click', toggleFs);
  document.addEventListener('dblclick', toggleFs);
  async function wake() { try { if ('wakeLock' in navigator) await navigator.wakeLock.request('screen'); } catch (e) { /* non disponible */ } }
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') wake(); });
  wake();

  render();
  requestAnimationFrame(tick);
  setTimeout(poll, refresh);
})();
