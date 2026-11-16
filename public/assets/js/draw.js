/* Tirage au sort en direct : catégorie → liste des noms → placement animé dans le tableau → validation. */
(function () {
  'use strict';
  const root = document.querySelector('[data-draw]');
  if (!root) return;
  const base = root.dataset.base;
  const $ = (s, el = root) => el.querySelector(s);
  const steps = root.querySelectorAll('[data-step]');
  let cat = null; // { id, label, size, locked, athletes }
  let running = false;

  const show = (name) => steps.forEach((s) => { s.hidden = s.dataset.step !== name; });
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const logo = (a) => `<span class="dn-logo">${a.club_logo ? `<img src="${esc(a.club_logo)}" alt="">` : ''}</span>`;
  const chip = (a) => `${logo(a)}<span class="dn-name"><strong>${esc(a.last_name)}</strong> ${esc(a.first_name)}<small>${esc(a.club_short || '')}</small></span>${a.seed ? `<span class="dn-seed">TS ${a.seed}</span>` : ''}`;

  function renderNames() {
    $('[data-draw-title]').textContent = cat.label;
    const n = cat.athletes.length;
    $('[data-draw-count]').textContent = `${n} judokas · tableau de ${cat.size}` + (cat.locked ? ' · tirage déjà validé' : '');
    const list = $('[data-draw-names]');
    list.style.setProperty('--cols', n <= 12 ? 3 : n <= 24 ? 4 : 5);
    list.innerHTML = cat.athletes.map((a) => `<li class="${a.seed ? 'is-seed' : ''}">${chip(a)}</li>`).join('');
    $('[data-draw-run]').disabled = cat.locked;
    $('[data-draw-run]').textContent = cat.locked ? 'Tirage validé' : 'Lancer le tirage';
  }

  async function openCategory(id) {
    try {
      cat = await JCL.api(`${base}/${id}`);
      renderNames();
      show('names');
    } catch (e) { JCL.toast(e.message, 'error'); }
  }

  // Ceinture : le premier de chaque combat (case du haut) est rouge, le second (case du bas) est blanc
  const belt = (red) => `<span class="db-belt ${red ? 'is-red' : 'is-white'}" title="${red ? 'Rouge' : 'Blanc'}"><svg viewBox="0 0 34 22" width="30" height="20" aria-hidden="true"><g fill="${red ? '#c21a2c' : '#ffffff'}" stroke="${red ? '#8f1220' : '#a1a1aa'}" stroke-width="1.2" stroke-linejoin="round"><path d="M14.5 11.5 9 20.5h4.6l3.4-6.6z"/><path d="M19.5 11.5 25 20.5h-4.6L17 13.9z"/><rect x="1.5" y="5" width="31" height="6" rx="2"/><rect x="12.5" y="3.2" width="9" height="9.4" rx="2.6"/></g></svg></span>`;

  // Tableau vide : premier tour, une ligne par case, par paires (combats), en colonnes de 8 cases
  function renderBracket(size) {
    const perCol = size <= 16 ? 8 : 16;
    let html = '';
    for (let c = 0; c < size / perCol; c++) {
      html += '<div class="db-col">';
      for (let i = c * perCol; i < (c + 1) * perCol; i += 2) {
        html += `<div class="db-pair"><div class="db-slot is-red" data-slot="${i}"><span class="db-num">${i + 1}</span>${belt(true)}<span class="db-who"></span></div>`
              + `<div class="db-slot is-white" data-slot="${i + 1}"><span class="db-num">${i + 2}</span>${belt(false)}<span class="db-who"></span></div></div>`;
      }
      html += '</div>';
    }
    const b = $('[data-draw-bracket]');
    b.style.setProperty('--cols', size / perCol);
    b.innerHTML = html;
  }

  async function run() {
    if (running || !cat || cat.locked) return;
    running = true;
    $('[data-draw-again]').hidden = true;
    $('[data-draw-confirm]').hidden = true;
    try {
      const res = await JCL.api(`${base}/${cat.id}/lancer`, { method: 'POST', body: {} });
      show('bracket');
      renderBracket(res.size);
      const byId = new Map(cat.athletes.map((a) => [a.registration_id, a]));
      const pool = $('[data-draw-pool]');
      pool.innerHTML = cat.athletes.map((a) => `<li data-reg="${a.registration_id}" class="${a.seed ? 'is-seed' : ''}">${chip(a)}</li>`).join('');
      // Têtes de série d'abord, dans l'ordre, à leur place ; puis tous les autres dans le désordre
      const seeds = res.slots.filter((s) => s.seed).sort((a, b) => a.seed - b.seed);
      const others = res.slots.filter((s) => !s.seed);
      for (let i = others.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [others[i], others[j]] = [others[j], others[i]]; }
      const order = [...seeds, ...others];
      const total = order.length;
      for (let k = 0; k < total; k++) {
        const s = order[k];
        const a = byId.get(s.registration_id);
        const src = pool.querySelector(`[data-reg="${s.registration_id}"]`);
        const slot = $(`[data-slot="${s.slot}"]`);
        if (src) { src.classList.add('is-picked'); }
        await sleep(s.seed ? 650 : 220);
        if (slot && a) {
          slot.querySelector('.db-who').innerHTML = chip(Object.assign({}, a, { seed: s.seed }));
          slot.classList.add('is-filled', 'is-new');
          slot.scrollIntoView({ block: 'nearest', inline: 'nearest' });
          setTimeout(() => slot.classList.remove('is-new'), 900);
        }
        if (src) { src.classList.add('is-placed'); }
        $('[data-draw-progress]').textContent = `${k + 1} / ${total} placés`;
        await sleep(s.seed ? 450 : 260);
      }
      root.querySelectorAll('.db-slot:not(.is-filled)').forEach((el) => { el.classList.add('is-bye'); el.querySelector('.db-who').textContent = 'Exempt'; });
      $('[data-draw-progress]').textContent = `Tirage terminé : ${total} judokas placés`;
      $('[data-draw-again]').hidden = false;
      $('[data-draw-confirm]').hidden = false;
    } catch (e) {
      JCL.toast(e.message, 'error');
    } finally { running = false; }
  }

  async function confirmDraw() {
    try {
      const r = await JCL.api(`${base}/${cat.id}/valider`, { method: 'POST', body: {} });
      JCL.toast(r.message, 'success');
      const btn = root.querySelector(`[data-cat="${cat.id}"]`);
      if (btn) {
        btn.dataset.state = 'validated';
        const b = btn.querySelector('[data-cat-state]');
        b.textContent = 'Tirage validé'; b.className = 'badge badge-green';
      }
      $('[data-draw-title]').textContent = 'Tirage au sort';
      show('categories');
    } catch (e) { JCL.toast(e.message, 'error'); }
  }

  // Têtes de série
  function renderSeeds() {
    const box = document.querySelector('[data-seed-list]');
    const opts = (cur) => '<option value="">—</option>' + [1, 2, 3, 4, 5, 6, 7, 8].map((n) => `<option value="${n}"${cur === n ? ' selected' : ''}>TS ${n}</option>`).join('');
    box.innerHTML = cat.athletes.map((a) => `<label class="draw-seed-row">${logo(a)}<span class="dn-name"><strong>${esc(a.last_name)}</strong> ${esc(a.first_name)}<small>${esc(a.club_short || '')}</small></span>`
      + `<select data-seed-for="${a.registration_id}" aria-label="Tête de série">${opts(a.seed)}</select></label>`).join('');
  }
  async function saveSeeds(clear) {
    const seeds = {};
    if (!clear) document.querySelectorAll('[data-seed-for]').forEach((s) => { if (s.value) seeds[s.dataset.seedFor] = +s.value; });
    try {
      const r = await JCL.api(`${base}/${cat.id}/tetes-de-serie`, { method: 'POST', body: { seeds } });
      cat.athletes = r.athletes;
      renderNames();
      document.getElementById('draw-seeds').close();
    } catch (e) { JCL.toast(e.message, 'error'); }
  }

  root.addEventListener('click', (e) => {
    const c = e.target.closest('[data-cat]');
    if (c && !c.disabled) openCategory(c.dataset.cat);
    if (e.target.closest('[data-draw-to]')) { $('[data-draw-title]').textContent = 'Tirage au sort'; show(e.target.closest('[data-draw-to]').dataset.drawTo); }
    if (e.target.closest('[data-draw-run]')) run();
    if (e.target.closest('[data-draw-again]')) run();
    if (e.target.closest('[data-draw-confirm]')) confirmDraw();
    if (e.target.closest('[data-dialog-open="draw-seeds"]') && cat) renderSeeds();
    if (e.target.closest('[data-seed-save]')) saveSeeds(false);
    if (e.target.closest('[data-seed-clear]')) saveSeeds(true);
    if (e.target.closest('[data-draw-fullscreen]')) {
      if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {});
    }
  });
})();
