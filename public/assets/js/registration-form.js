/* Formulaire d'inscription : catégorie d'âge calculée depuis l'année de naissance, catégories de poids filtrées.
   Indicatif uniquement : le serveur refait tous les contrôles. */
(function () {
  'use strict';
  const cfgEl = document.getElementById('reg-config');
  const form = document.querySelector('[data-reg-form]');
  if (!cfgEl || !form) return;
  const cfg = JSON.parse(cfgEl.textContent);
  const $ = (s) => form.querySelector(s);
  const birth = $('[data-birth]'), ageSel = $('[data-age]'), weightSel = $('[data-weight]'), kg = $('[data-kg]'), summary = $('[data-reg-summary]');
  const current = $('[data-weight-current]').value;
  // Catégorie de poids choisie à la main : on la garde ; sinon elle suit le poids saisi (même chiffre par chiffre).
  let manual = !!current;
  const sex = () => { const c = form.querySelector('[data-sex]:checked'); return c ? c.value : null; };
  const natural = () => {
    if (!birth.value) return null;
    const y = parseInt(birth.value.slice(0, 4), 10);
    return cfg.ages.find((a) => y >= a.min && y <= a.max) || null;
  };
  const label = (a, s) => (s === 'M' ? a.name_m : s === 'F' ? a.name_f : null) || a.name;
  function update(keepWeight) {
    const s = sex(), nat = natural();
    const chosen = ageSel.value ? cfg.ages.find((a) => String(a.id) === ageSel.value) : nat;
    const prev = manual ? (weightSel.value || current) : '';
    weightSel.length = 1;
    let msg = '';
    if (!birth.value || !s) { summary.textContent = 'Renseignez le sexe et la date de naissance pour voir la catégorie.'; return; }
    if (!nat) { summary.textContent = 'Année de naissance hors des catégories de la compétition.'; summary.style.color = 'var(--red)'; return; }
    summary.style.color = '';
    if (ageSel.value && chosen && chosen.id !== nat.id && nat.upgrade !== chosen.id) {
      msg = 'Surclassement non autorisé vers ' + chosen.name + '. ';
    }
    if (chosen && !cfg.open.includes(chosen.id + s)) msg += 'Tableau non ouvert pour cette catégorie. ';
    if (chosen && chosen.format === 'animation') {
      weightSel.disabled = true;
      summary.textContent = msg + 'Catégorie : ' + label(chosen, s) + ' — animation, pas de catégorie de poids.';
      return;
    }
    weightSel.disabled = false;
    const ws = cfg.weights.filter((w) => chosen && w.age === chosen.id && w.sex === s);
    ws.forEach((w) => { const o = new Option(w.label + ' kg', w.id); weightSel.add(o); });
    if (prev && ws.some((w) => String(w.id) === String(prev))) weightSel.value = prev;
    let auto = null;
    const k = parseFloat((kg.value || '').replace(',', '.'));
    if (!isNaN(k) && ws.length) { auto = ws.find((w) => w.max === null || k <= w.max) || null; }
    if (cfg.weightInput === 'weight' && auto && !weightSel.value) weightSel.value = auto.id;
    const wl = weightSel.value ? ws.find((w) => String(w.id) === weightSel.value).label + ' kg' : (ws.length ? 'catégorie de poids à choisir' : 'poule morphologique');
    summary.textContent = msg + 'Catégorie : ' + label(chosen, s) + (chosen.id !== nat.id ? ' (surclassement depuis ' + nat.name + ')' : '') + ' — ' + wl + '.';
  }
  weightSel.addEventListener('change', () => { manual = weightSel.value !== ''; });
  form.addEventListener('change', (e) => { if (e.target !== weightSel) update(false); });
  kg.addEventListener('input', () => update(false));
  update(true);
})();
