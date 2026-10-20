/* Direct public : combat en cours sur chaque tapis (polling toutes les 5 s). */
(function () {
  'use strict';
  const root = document.querySelector('[data-live-url]');
  if (!root) return;
  const grid = root.querySelector('[data-live-grid]');
  const status = root.querySelector('[data-live-status]');
  const label = { waiting: 'En attente', next: 'Prochain combat', running: 'Combat en cours', paused: 'Arrêt (matte)', result: 'Résultat' };
  const name = (f) => JCL.esc(f.first_name + ' ' + f.last_name);
  function card(t) {
    const c = t.current;
    let html = '<article class="card"><div class="card-head"><h2 style="margin:0">Tapis ' + t.number + '</h2><span class="badge ' + (t.mode === 'running' ? 'badge-red' : t.mode === 'result' ? 'badge-blue' : '') + '">' + label[t.mode] + '</span></div>';
    if (c) {
      html += '<p class="small muted">' + JCL.esc(c.category) + (c.weight_label ? ' ' + JCL.esc(c.weight_label) + ' kg' : '') + ' · ' + JCL.esc(c.pool_label) + '</p>';
      ['a', 'b'].forEach((s) => {
        const f = c[s]; const win = c.winner_side === s;
        html += '<div style="display:flex;justify-content:space-between;gap:.5rem;padding:.4rem 0;border-top:1px solid var(--line)' + (win ? ';font-weight:700;color:var(--victory)' : '') + '"><span>' + name(f) + '<br><small class="muted">' + JCL.esc(f.club) + '</small></span><span class="num" style="font:800 italic 1.4rem var(--font-display)">' + (win && (c.status === 'validated' || c.status === 'finished') ? 'Victoire' : f.ippon + '' + f.wazaari + '' + f.yuko) + '</span></div>';
      });
    } else { html += '<p class="muted">Aucun combat en cours.</p>'; }
    if (t.next && t.next.length) {
      html += '<p class="small" style="margin:.6rem 0 0"><strong>À suivre :</strong> ' + t.next.map((n) => name(n.a) + ' / ' + name(n.b)).join(' · ') + '</p>';
    }
    return html + '</article>';
  }
  async function load() {
    try {
      const d = await JCL.api(root.dataset.liveUrl);
      grid.innerHTML = d.tatamis.map(card).join('') || '<div class="empty"><strong>Aucun tapis actif</strong></div>';
      status.textContent = 'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR');
    } catch (e) { status.textContent = 'Connexion perdue, nouvel essai dans quelques secondes…'; }
    setTimeout(load, 5000);
  }
  load();
})();
