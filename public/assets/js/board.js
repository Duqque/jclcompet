/* Classement public des clubs : mise à jour dès qu'un commissaire valide un résultat, annonce des podiums en direct,
   défilement automatique et plein écran. Aucune WebSocket : interrogation légère de l'empreinte des résultats. */
(function () {
  'use strict';
  const body = document.body;
  const url = body.dataset.url;
  const esc = JCL.esc;
  const STAMP_EVERY = 4000;          // vérification légère : un résultat validé apparaît en quelques secondes
  const FULL_EVERY = 120000;         // filet de sécurité
  const PODIUM_SECONDS = 14;
  let stamp = null;
  let known = null;                  // poules déjà annoncées (aucune annonce au premier affichage)
  const queue = [];
  let showing = false;
  let busy = false;

  const initials = (s) => String(s || '').split(/\s+/).filter((w) => w.length > 2).slice(0, 3).map((w) => w[0]).join('').toUpperCase();
  function logo(c) {
    return c.logo ? '<img src="' + esc(c.logo) + '" alt="" width="34" height="34" loading="lazy">' : '<span class="mono">' + esc(initials(c.club)) + '</span>';
  }
  function row(c, by) {
    const scored = c.points > 0 || c.gold + c.silver + c.bronze > 0;
    const rank = by === 'points' ? c.rank_points : c.rank_medals;
    return '<li class="' + (rank <= 3 && scored ? 'top top-' + rank : '') + (scored ? '' : ' unranked') + '"><span class="rank">' + (scored ? rank : '–') + '</span>' + logo(c) +
      '<span class="name"><strong>' + esc(c.club) + '</strong><small>' + c.participants + ' participant' + (c.participants > 1 ? 's' : '') + '</small></span>' +
      (by === 'points'
        ? '<span class="pts"><strong>' + c.points + '</strong><small>pts</small></span>'
        : '<span class="meds"><i class="g">' + c.gold + '</i><i class="s">' + c.silver + '</i><i class="b">' + c.bronze + '</i></span>') + '</li>';
  }
  function render(d) {
    ['points', 'medals'].forEach((by) => {
      const ol = document.querySelector('[data-list="' + by + '"]');
      ol.innerHTML = d[by].length ? d[by].map((c) => row(c, by)).join('') : '<li class="empty-row">Classement en attente des premières poules terminées.</li>';
    });
    const when = d.updated_at ? new Date(d.updated_at.replace(' ', 'T')) : new Date(d.generated_at.replace(' ', 'T'));
    document.querySelector('[data-updated]').textContent = 'Mis à jour à ' + when.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    stamp = d.stamp || stamp;
    const pods = d.podiums || [];
    if (known === null) {
      known = new Set(pods.map((p) => p.pool_id + '@' + p.at));
    } else {
      pods.forEach((p) => { const k = p.pool_id + '@' + p.at; if (!known.has(k)) { known.add(k); queue.push(p); } });
      nextPodium();
    }
  }

  async function refresh() {
    if (busy) return;
    busy = true;
    try { render(await JCL.api(url)); body.classList.remove('stale'); }
    catch (e) { body.classList.add('stale'); document.querySelector('[data-updated]').textContent = 'Hors ligne — nouvel essai…'; }
    busy = false;
  }
  async function checkStamp() {
    if (document.hidden) return;
    try {
      const s = await JCL.api(url + '/etat');
      body.classList.remove('stale');
      if (s.stamp !== stamp) refresh();
    } catch (e) { body.classList.add('stale'); }
  }

  // --- Annonce des podiums -------------------------------------------------------
  const dlg = document.querySelector('[data-podium]');
  const medalName = { gold: 'Or', silver: 'Argent', bronze: 'Bronze' };
  let closeTimer = null;
  function card(a) {
    const visual = a.photo
      ? '<img class="ph" src="' + esc(a.photo) + '" alt="">'
      : '<span class="crest">' + (a.logo ? '<img src="' + esc(a.logo) + '" alt="">' : '<span class="mono">' + esc(initials(a.club)) + '</span>') + '</span>';
    return '<article class="pod pod-' + a.place + (a.photo ? ' has-photo' : '') + '" style="--club:' + esc(a.color || '#71717a') + '">' +
      '<div class="visual">' + visual + '<span class="place">' + a.place + '</span></div>' +
      '<div class="who"><strong>' + esc(a.first_name) + ' <b>' + esc(a.last_name.toUpperCase()) + '</b></strong>' +
      '<span class="club">' + (a.logo ? '<img src="' + esc(a.logo) + '" alt="">' : '') + esc(a.club_short || a.club || '') + '</span>' +
      (a.medal ? '<span class="pod-medal m-' + a.medal + '">' + medalName[a.medal] + '</span>' : '') + '</div></article>';
  }
  function nextPodium() {
    if (showing || !queue.length || !dlg) return;
    const p = queue.shift();
    showing = true;
    const podium = p.athletes.filter((a) => a.place <= 3);
    const order = [2, 1, 3].flatMap((pl) => podium.filter((a) => a.place === pl));
    const rest = p.athletes.filter((a) => a.place > 3);
    dlg.querySelector('[data-pod-cat]').textContent = p.category;
    dlg.querySelector('[data-pod-weight]').textContent = p.weight ? p.weight + ' kg' : 'Poule ' + p.pool_number;
    dlg.querySelector('[data-pod-pool]').textContent = 'Poule ' + p.pool_number;
    dlg.querySelector('[data-pod-cards]').innerHTML = order.map(card).join('');
    dlg.querySelector('[data-pod-rest]').innerHTML = rest.map((a) => '<li><span class="pl">' + a.place + '</span>' + (a.logo ? '<img src="' + esc(a.logo) + '" alt="">' : '') + '<span>' + esc(a.first_name) + ' <b>' + esc(a.last_name.toUpperCase()) + '</b></span><small>' + esc(a.club_short || '') + '</small></li>').join('');
    dlg.querySelector('[data-pod-rest]').hidden = !rest.length;
    dlg.classList.remove('leaving');
    if (!dlg.open) dlg.showModal();
    const bar = dlg.querySelector('[data-pod-timer]');
    bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = '';
    bar.style.animationDuration = PODIUM_SECONDS + 's';
    clearTimeout(closeTimer);
    closeTimer = setTimeout(closePodium, PODIUM_SECONDS * 1000);
  }
  function closePodium() {
    clearTimeout(closeTimer);
    if (!dlg || !dlg.open) { showing = false; return; }
    dlg.classList.add('leaving');
    setTimeout(() => { dlg.close(); showing = false; nextPodium(); }, 350);
  }
  if (dlg) {
    dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.closest('[data-pod-close]')) closePodium(); });
    dlg.addEventListener('cancel', (e) => { e.preventDefault(); closePodium(); });
  }

  // --- Plein écran (écran de salle) -------------------------------------------------
  const fs = document.querySelector('[data-fullscreen]');
  if (fs) {
    if (!document.documentElement.requestFullscreen) fs.hidden = true;
    fs.addEventListener('click', () => { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {}); });
    document.addEventListener('fullscreenchange', () => {
      const on = !!document.fullscreenElement;
      body.classList.toggle('is-fullscreen', on);
      fs.setAttribute('aria-pressed', String(on));
      fs.querySelector('span').textContent = on ? 'Quitter le plein écran' : 'Plein écran';
    });
  }

  // --- Défilement automatique lent : descend jusqu'en bas, pause, puis remonte -------------
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let dir = 1;
  let pauseUntil = Date.now() + 6000;
  let pos = 0;
  let last = performance.now();
  function scrollStep(t) {
    const dt = Math.min(100, t - last);
    last = t;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (max > 4 && Date.now() > pauseUntil && !document.hidden && !showing) {
      pos = Math.min(max, Math.max(0, pos + dir * (reduce ? 12 : 26) * dt / 1000)); // px par seconde
      window.scrollTo(0, pos);
      if (pos >= max) { dir = -1; pauseUntil = Date.now() + 8000; } else if (pos <= 0) { dir = 1; pauseUntil = Date.now() + 8000; }
    } else if (max <= 4) {
      pos = 0;
    }
    requestAnimationFrame(scrollStep);
  }
  ['wheel', 'touchstart', 'keydown'].forEach((ev) => window.addEventListener(ev, () => { pauseUntil = Date.now() + 15000; pos = window.scrollY; }, { passive: true }));
  requestAnimationFrame(scrollStep);

  const initial = document.getElementById('board-data');
  if (initial) render(JSON.parse(initial.textContent));
  setInterval(checkStamp, STAMP_EVERY);
  setInterval(refresh, FULL_EVERY);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) checkStamp(); });
  document.querySelector('[data-refresh-now]').addEventListener('click', refresh);
})();
