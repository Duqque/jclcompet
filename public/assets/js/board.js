/* Classement public des clubs : actualisation automatique (polling) + bouton manuel. */
(function () {
  'use strict';
  const body = document.body;
  const url = body.dataset.url;
  const every = Math.max(30, parseInt(body.dataset.refresh || '120', 10) || 120) * 1000;
  let timer = null;

  function logo(c) {
    return c.logo ? '<img src="' + JCL.esc(c.logo) + '" alt="" width="44" height="44" loading="lazy">' : '<span class="mono">' + JCL.esc(c.club.split(/\s+/).filter(w => w.length > 2).slice(0, 3).map(w => w[0]).join('').toUpperCase()) + '</span>';
  }
  function row(c, by) {
    const rank = by === 'points' ? c.rank_points : c.rank_medals;
    return '<li class="' + (rank <= 3 ? 'top top-' + rank : '') + '"><span class="rank">' + rank + '</span>' + logo(c) +
      '<span class="name"><strong>' + JCL.esc(c.club) + '</strong><small>' + c.participants + ' participant' + (c.participants > 1 ? 's' : '') + '</small></span>' +
      (by === 'points'
        ? '<span class="pts"><strong>' + c.points + '</strong><small>pts</small></span>'
        : '<span class="meds"><i class="g">' + c.gold + '</i><i class="s">' + c.silver + '</i><i class="b">' + c.bronze + '</i><small>4e ' + c.fourth + ' · 5e ' + c.fifth + '</small></span>') + '</li>';
  }
  function render(d) {
    ['points', 'medals'].forEach((by) => {
      const ol = document.querySelector('[data-list="' + by + '"]');
      ol.innerHTML = d[by].length ? d[by].map((c) => row(c, by)).join('') : '<li class="empty-row">Classement en attente des premières poules terminées.</li>';
    });
    const when = d.updated_at ? new Date(d.updated_at.replace(' ', 'T')) : new Date(d.generated_at.replace(' ', 'T'));
    document.querySelector('[data-updated]').textContent = 'Mis à jour à ' + when.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }
  // Compte à rebours jusqu'à la prochaine actualisation automatique
  const cd = document.querySelector('[data-countdown]');
  let nextAt = Date.now() + every;
  setInterval(() => {
    const left = Math.max(0, Math.round((nextAt - Date.now()) / 1000));
    if (cd) cd.textContent = '· prochaine dans ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
  }, 1000);

  // Plein écran (écran de salle)
  const fs = document.querySelector('[data-fullscreen]');
  const fsLabel = () => {
    const on = !!document.fullscreenElement;
    body.classList.toggle('is-fullscreen', on);
    fs.setAttribute('aria-pressed', String(on));
    fs.querySelector('span').textContent = on ? 'Quitter le plein écran' : 'Plein écran';
  };
  if (fs) {
    if (!document.documentElement.requestFullscreen) fs.hidden = true;
    fs.addEventListener('click', () => {
      if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {});
    });
    document.addEventListener('fullscreenchange', fsLabel);
  }

  async function refresh() {
    clearTimeout(timer);
    nextAt = Date.now() + every;
    try { render(await JCL.api(url)); document.body.classList.remove('stale'); }
    catch (e) { document.body.classList.add('stale'); document.querySelector('[data-updated]').textContent = 'Hors ligne — nouvel essai…'; }
    timer = setTimeout(refresh, every);
  }
  // Défilement automatique lent : descend jusqu'en bas du classement, marque une pause, puis remonte.
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let dir = 1;
  let pauseUntil = Date.now() + 6000;
  let pos = 0;
  let last = performance.now();
  function scrollStep(t) {
    const dt = Math.min(100, t - last);
    last = t;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (max > 4 && Date.now() > pauseUntil && !document.hidden) {
      pos = Math.min(max, Math.max(0, pos + dir * (reduce ? 12 : 26) * dt / 1000)); // px par seconde
      window.scrollTo(0, pos);
      if (pos >= max) { dir = -1; pauseUntil = Date.now() + 8000; }
      else if (pos <= 0) { dir = 1; pauseUntil = Date.now() + 8000; }
    } else if (max <= 4) {
      pos = 0;
    }
    requestAnimationFrame(scrollStep);
  }
  ['wheel', 'touchstart', 'keydown'].forEach((ev) => window.addEventListener(ev, () => { pauseUntil = Date.now() + 15000; pos = window.scrollY; }, { passive: true }));
  requestAnimationFrame(scrollStep);

  const initial = document.getElementById('board-data');
  if (initial) render(JSON.parse(initial.textContent));
  timer = setTimeout(refresh, every);
  document.querySelector('[data-refresh-now]').addEventListener('click', refresh);
})();
