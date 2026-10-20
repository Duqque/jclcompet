/* Classement public des clubs : actualisation automatique (polling) + bouton manuel. */
(function () {
  'use strict';
  const body = document.body;
  const url = body.dataset.url;
  const every = Math.max(30, parseInt(body.dataset.refresh || '120', 10)) * 1000;
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
    const t = d.totals;
    document.querySelector('[data-totals]').innerHTML =
      '<div><strong>' + t.participants + '</strong><span>participants</span></div>' +
      '<div><strong class="g">' + t.gold + '</strong><span>or</span></div><div><strong class="s">' + t.silver + '</strong><span>argent</span></div><div><strong class="b">' + t.bronze + '</strong><span>bronze</span></div>' +
      '<div><strong>' + t.bouts_done + '<small>/' + t.bouts_total + '</small></strong><span>combats validés</span></div>';
    const when = d.updated_at ? new Date(d.updated_at.replace(' ', 'T')) : new Date(d.generated_at.replace(' ', 'T'));
    document.querySelector('[data-updated]').textContent = 'Mis à jour à ' + when.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }
  async function refresh() {
    clearTimeout(timer);
    try { render(await JCL.api(url)); document.body.classList.remove('stale'); }
    catch (e) { document.body.classList.add('stale'); document.querySelector('[data-updated]').textContent = 'Hors ligne — nouvel essai…'; }
    timer = setTimeout(refresh, every);
  }
  const initial = document.getElementById('board-data');
  if (initial) render(JSON.parse(initial.textContent));
  timer = setTimeout(refresh, every);
  document.querySelector('[data-refresh-now]').addEventListener('click', refresh);
})();
