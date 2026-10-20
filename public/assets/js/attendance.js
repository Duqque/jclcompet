/* Accueil le jour J : cartes des judokas filtrables, pointage en un geste, mise à jour immédiate côté serveur. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('att-config').textContent);
  const list = document.querySelector('[data-list]');
  const q = document.querySelector('[data-q]');
  const sub = document.querySelector('[data-sub]');
  const sync = document.querySelector('[data-sync]');
  const esc = JCL.esc;
  const LS = 'jcl-att-filters';
  const state = { club_id: '', attendance: '' };
  let rows = [];
  let timer = null;
  const label = { expected: 'Attendu', present: 'Présent', absent: 'Absent', to_check: 'À vérifier' };
  const badge = { expected: '', present: 'badge-green', absent: 'badge-red', to_check: 'badge-orange' };
  const svg = (d) => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  const ICON = {
    present: svg('<path d="m5 12.5 4.5 4.5L19 7.5"/>'),
    absent: svg('<path d="M6 6l12 12"/><path d="M18 6 6 18"/>'),
    to_check: svg('<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>'),
  };

  function initials(name) {
    const skip = ['de', 'du', 'des', 'la', 'le', 'les', 'et', "d'", "l'"];
    return String(name || '').trim().split(/[\s-]+/).filter((w) => w && !skip.includes(w.toLowerCase())).slice(0, 3).map((w) => w[0].toUpperCase()).join('');
  }
  const kg = (v) => String(v).replace('.', ',') + ' kg';

  // Filtres mémorisés sur cet appareil (confort uniquement : la base reste la référence)
  try {
    const saved = JSON.parse(localStorage.getItem(LS) || '{}');
    Object.entries(saved).forEach(([k, v]) => { const s = document.querySelector('[data-f="' + k + '"]'); if (s) s.value = v; else if (k in state) state[k] = v || ''; });
  } catch (e) { /* stockage indisponible */ }

  function params() {
    const p = new URLSearchParams();
    const filters = Object.assign({}, state);
    document.querySelectorAll('[data-f]').forEach((s) => { filters[s.dataset.f] = s.value; });
    Object.entries(filters).forEach(([k, v]) => { if (v) p.set(k, v); });
    try { localStorage.setItem(LS, JSON.stringify(filters)); } catch (e) { /* stockage indisponible */ }
    if (q.value.trim()) p.set('q', q.value.trim());
    return p.toString();
  }

  function syncControls() {
    document.querySelectorAll('[data-att]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.att === state.attendance)));
  }

  function card(r) {
    const name = r.first_name + ' ' + r.last_name;
    const avatar = r.photo ? '<img class="avatar" src="' + esc(r.photo) + '" alt="" loading="lazy">' : '<span class="avatar-mono avatar" aria-hidden="true">' + esc(initials(name)) + '</span>';
    const logo = r.logo ? '<img class="club-logo" src="' + esc(r.logo) + '" alt="" width="22" height="22" loading="lazy">' : '<span class="club-mono" aria-hidden="true">' + esc(initials(r.club)) + '</span>';
    const weight = r.measured ? 'pesé ' + kg(r.measured) : (r.weight ? r.weight + ' kg' : (r.declared ? kg(r.declared) : '—'));
    const notes = [r.at ? esc(r.at) + (r.by ? ' · ' + esc(r.by) : '') : '', r.onsite ? 'inscrit sur place' : '', r.pending ? '<span class="warn">à valider</span>' : ''].filter(Boolean).join(' · ');
    const btn = (s, text) => '<button type="button" data-mark="' + s + '" class="' + s + '" aria-pressed="' + (r.attendance === s) + '">' + ICON[s] + '<span>' + text + '</span></button>';
    return '<article class="profile att-card is-' + r.attendance + '" data-id="' + r.id + '">' +
      '<div class="top">' + avatar + '<div class="name">' + esc(r.first_name) + ' ' + esc(r.last_name.toUpperCase()) + '<small>' + esc(r.year) + (r.grade ? ' · ' + esc(r.grade) : '') + '</small></div></div>' +
      '<div class="tiles"><div class="tile"><span class="label">Catégorie</span><strong>' + esc(r.category) + '</strong></div><div class="tile"><span class="label">Poids</span><strong>' + esc(weight) + '</strong></div></div>' +
      '<div class="foot"><span class="club" title="' + esc(r.club || '') + '">' + logo + '<span>' + esc(r.club_short || '') + '</span></span><span class="badge ' + badge[r.attendance] + '"><span class="dot"></span>' + label[r.attendance] + '</span></div>' +
      '<div class="att-actions" role="group" aria-label="Présence de ' + esc(name) + '">' + btn('present', 'Présent') + btn('absent', 'Absent') + btn('to_check', 'À vérifier') + '</div>' +
      (notes ? '<p class="att-note">' + notes + '</p>' : '') + '</article>';
  }

  function render(data) {
    rows = data.rows;
    const c = data.counts;
    Object.entries(c).forEach(([k, v]) => { const el = document.querySelector('[data-n="' + k + '"]'); if (el) el.textContent = v; });
    sub.textContent = c.total + ' judokas · ' + c.present + ' présents · ' + c.expected + ' attendus';
    list.innerHTML = rows.length ? rows.map(card).join('') : '<div class="empty"><strong>Aucun judoka</strong>Modifiez la recherche ou les filtres, ou inscrivez le judoka sur place.</div>';
    syncControls();
  }

  function online(ok) {
    sync.classList.toggle('off', !ok);
    sync.querySelector('.lbl').textContent = ok ? 'En ligne' : 'Hors ligne';
  }

  async function load() {
    try { render(await JCL.api(cfg.listUrl + '?' + params())); online(true); }
    catch (e) { online(false); JCL.toast(e.message, 'error'); }
  }

  q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 220); });
  document.querySelectorAll('[data-f]').forEach((s) => s.addEventListener('change', load));
  document.addEventListener('clubpick', (e) => { state.club_id = e.detail.id || ''; load(); });
  document.addEventListener('click', (e) => {
    const att = e.target.closest('[data-att]');
    if (att) { state.attendance = state.attendance === att.dataset.att ? '' : att.dataset.att; load(); }
  });

  list.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-mark]');
    if (!b) return;
    const el = b.closest('[data-id]');
    const id = el.dataset.id;
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
    el.classList.add('loading');
    try {
      const res = await JCL.api(cfg.markUrl.replace('__ID__', id), { method: 'POST', body });
      const r = rows.find((x) => String(x.id) === id);
      if (r) { r.attendance = res.attendance; r.at = res.at; r.weight = res.weight; if (body.measured_weight_kg) r.measured = body.measured_weight_kg; el.outerHTML = card(r); }
      if (navigator.vibrate) navigator.vibrate(30);
      load();
    } catch (err) { el.classList.remove('loading'); JCL.toast(err.message, 'error', 6000); }
  });

  syncControls();
  // Club mémorisé : on rétablit l'affichage du sélecteur (le clic déclenche aussi le chargement)
  const savedPick = state.club_id ? document.querySelector('[data-picker="att-clubs"] [data-pick="' + state.club_id + '"]') : null;
  if (savedPick) savedPick.click(); else load();
  setInterval(() => { if (!document.hidden && !q.matches(':focus') && !document.querySelector('dialog[open]')) load(); }, 15000);
})();
