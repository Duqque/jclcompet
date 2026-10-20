/* Accueil le jour J : liste filtrable, pointage en un geste, mise à jour immédiate côté serveur. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('att-config').textContent);
  const list = document.querySelector('[data-list]');
  const q = document.querySelector('[data-q]');
  const counts = document.querySelector('[data-counts]');
  const sync = document.querySelector('[data-sync]');
  const LS = 'jcl-att-filters';
  let rows = [];
  let timer = null;
  const label = { expected: 'Attendu', present: 'Présent', absent: 'Absent / forfait', to_check: 'À vérifier' };

  try { const saved = JSON.parse(localStorage.getItem(LS) || '{}'); Object.entries(saved).forEach(([k, v]) => { const s = document.querySelector('[data-f="' + k + '"]'); if (s) s.value = v; }); } catch (e) { /* stockage indisponible */ }

  function params() {
    const p = new URLSearchParams();
    const filters = {};
    document.querySelectorAll('[data-f]').forEach((s) => { if (s.value) { p.set(s.dataset.f, s.value); } filters[s.dataset.f] = s.value; });
    try { localStorage.setItem(LS, JSON.stringify(filters)); } catch (e) { /* stockage indisponible */ }
    if (q.value.trim()) p.set('q', q.value.trim());
    return p.toString();
  }

  function item(r) {
    return '<li class="att-item ' + r.attendance + '" data-id="' + r.id + '"><div class="who"><strong>' + JCL.esc(r.last_name) + ' ' + JCL.esc(r.first_name) + '</strong><span class="badge ' + ({ present: 'badge-green', absent: 'badge-red', to_check: 'badge-orange' }[r.attendance] || '') + '">' + label[r.attendance] + '</span></div>' +
      '<div class="meta">' + JCL.esc(r.club || '') + ' · ' + JCL.esc(r.category) + (r.weight ? ' ' + JCL.esc(r.weight) + ' kg' : '') + (r.measured ? ' · pesé ' + String(r.measured).replace('.', ',') + ' kg' : '') +
      (r.onsite ? ' · <strong>sur place</strong>' : '') + (r.pending ? ' · <span style="color:var(--orange)">à valider</span>' : '') + (r.at ? ' · ' + r.at + (r.by ? ' par ' + JCL.esc(r.by) : '') : '') + '</div>' +
      '<div class="att-actions">' +
      '<button class="btn btn-green' + (r.attendance === 'present' ? ' on' : '') + '" type="button" data-mark="present">Présent</button>' +
      '<button class="btn btn-danger' + (r.attendance === 'absent' ? ' on' : '') + '" type="button" data-mark="absent">Absent</button>' +
      '<button class="btn btn-yellow' + (r.attendance === 'to_check' ? ' on' : '') + '" type="button" data-mark="to_check">À vérifier</button></div></li>';
  }

  function render(data) {
    rows = data.rows;
    const c = data.counts;
    counts.innerHTML = '<span class="badge badge-green">' + c.present + ' présents</span><span class="badge">' + c.expected + ' attendus</span><span class="badge badge-red">' + c.absent + ' absents</span><span class="badge badge-orange">' + c.to_check + ' à vérifier</span><span class="badge badge-ink">' + c.total + ' inscrits</span>';
    list.innerHTML = rows.length ? rows.map(item).join('') : '<li class="empty"><strong>Aucun judoka</strong>Modifiez la recherche ou les filtres, ou inscrivez le judoka sur place.</li>';
  }

  async function load() {
    try { render(await JCL.api(cfg.listUrl + '?' + params())); sync.style.color = '#3ccf7a'; }
    catch (e) { sync.style.color = 'var(--red)'; JCL.toast(e.message, 'error'); }
  }

  q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 220); });
  document.querySelectorAll('[data-f]').forEach((s) => s.addEventListener('change', load));

  list.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-mark]');
    if (!b) return;
    const li = b.closest('[data-id]');
    const id = li.dataset.id;
    const status = b.dataset.mark;
    const body = { status };
    if (status === 'present' && cfg.weighIn) {
      const w = await JCL.ask({ title: 'Pesée', label: 'Poids mesuré (kg)', hint: 'Laisser vide s\'il n\'y a pas de pesée.', placeholder: 'ex. 32,4', inputmode: 'decimal', confirmLabel: 'Marquer présent' });
      if (w === null) return;
      if (w) body.measured_weight_kg = w;
    }
    if (status === 'to_check') {
      const n = await JCL.ask({ title: 'À vérifier', label: 'Point à vérifier (facultatif)', placeholder: 'ex. licence manquante', confirmLabel: 'Marquer à vérifier' });
      if (n === null) return;
      body.note = n;
    }
    li.classList.add('loading');
    try {
      const res = await JCL.api(cfg.markUrl.replace('__ID__', id), { method: 'POST', body });
      const r = rows.find((x) => String(x.id) === id);
      if (r) { r.attendance = res.attendance; r.at = res.at; r.weight = res.weight; li.outerHTML = item(r); }
      if (navigator.vibrate) navigator.vibrate(30);
      load();
    } catch (err) { li.classList.remove('loading'); JCL.toast(err.message, 'error', 6000); }
  });

  load();
  setInterval(() => { if (!document.hidden && !q.matches(':focus')) load(); }, 15000);
})();
