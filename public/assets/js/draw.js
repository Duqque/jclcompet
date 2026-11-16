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
    const played = cat.played || 0;
    $('[data-draw-count]').textContent = `${n} judokas · tableau de ${cat.size}`
      + (played ? ` · ${played} combat${played > 1 ? 's' : ''} déjà disputé${played > 1 ? 's' : ''}` : cat.locked ? ' · tirage déjà validé' : '');
    const list = $('[data-draw-names]');
    list.style.setProperty('--cols', n <= 12 ? 3 : n <= 24 ? 4 : 5);
    list.innerHTML = cat.athletes.map((a) => `<li class="${a.seed ? 'is-seed' : ''}">${chip(a)}</li>`).join('');
    const redo = cat.locked || played > 0;
    $('[data-draw-run]').hidden = redo;
    $('[data-draw-redo]').hidden = !redo;
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

  // Tableau vide en forme d'arbre (feuille officielle) : moitié haute à gauche, moitié basse à droite,
  // les tours se rejoignent vers la finale au centre. Premier tour : cases numérotées, rouge en haut, blanc en bas.
  const G = { slotW: 250, slotH: 36, inPair: 3, pairGap: 12, boxW: 74, boxH: 26, gap: 26, finalW: 150 };
  let fitTree = () => {};
  function renderBracket(size) {
    const half = size / 2;
    const rounds = Math.round(Math.log2(half)); // tours après le premier, par moitié
    const pitch = 2 * G.slotH + G.inPair + G.pairGap;
    const slotY = (i) => Math.floor(i / 2) * pitch + (i % 2) * (G.slotH + G.inPair);
    const height = (half / 2) * pitch - G.pairGap;
    const sideW = G.slotW + rounds * (G.gap + G.boxW);
    const totalW = 2 * sideW + 2 * G.gap + G.finalW;
    const lines = [];
    let html = '';
    const box = (x, y, w, h, cls, inner = '', attrs = '') => `<div class="${cls}"${attrs} style="left:${x}px;top:${y}px;width:${w}px;height:${h}px">${inner}</div>`;
    let finalists = [];
    for (const side of [0, 1]) {
      const X = (x, w) => (side === 0 ? x : totalW - x - w);
      // Premier tour
      let col = [];
      for (let k = 0; k < half; k++) {
        const i = side * half + k;
        const y = slotY(k);
        const red = i % 2 === 0;
        html += box(X(0, G.slotW), y, G.slotW, G.slotH, `db-slot ${red ? 'is-red' : 'is-white'}${side ? ' is-right' : ''}`,
          `<span class="db-num">${i + 1}</span>${belt(red)}<span class="db-who"></span>`, ` data-slot="${i}"`);
        col.push({ y: y + G.slotH / 2, x: X(0, G.slotW), w: G.slotW });
      }
      // Tours suivants
      let x = G.slotW;
      for (let r = 1; r <= rounds; r++) {
        const next = [];
        const bx = x + G.gap;
        for (let j = 0; j < col.length; j += 2) {
          const cy = (col[j].y + col[j + 1].y) / 2;
          const edge = side === 0 ? x : totalW - x;
          const mid = side === 0 ? x + G.gap / 2 : totalW - x - G.gap / 2;
          const into = side === 0 ? bx : totalW - bx;
          lines.push(`M${edge},${col[j].y}H${mid}V${col[j + 1].y}H${edge}M${mid},${cy}H${into}`);
          html += box(X(bx, G.boxW), cy - G.boxH / 2, G.boxW, G.boxH, 'db-node');
          next.push({ y: cy });
        }
        col = next;
        x = bx + G.boxW;
      }
      finalists.push(col[0].y);
    }
    // Finale au centre
    const fx = sideW + G.gap;
    const fy = finalists[0];
    lines.push(`M${sideW},${fy}H${fx}M${totalW - sideW},${fy}H${fx + G.finalW}`);
    html += box(fx, fy - 22, G.finalW, 44, 'db-node is-final', '<span>Finale</span>');
    html += box(fx - 60, Math.max(0, fy - 120), G.finalW + 120, 64, 'db-cat', `<small>Catégorie</small><strong>${esc(cat.label)}</strong>`);
    const svg = `<svg class="db-lines" width="${totalW}" height="${height}" aria-hidden="true"><path d="${lines.join('')}"/></svg>`;
    const b = $('[data-draw-bracket]');
    b.innerHTML = `<div class="db-tree" style="width:${totalW}px;height:${height}px">${svg}${html}</div>`;
    const tree = b.firstElementChild;
    fitTree = () => {
      const scale = Math.min(1, b.clientWidth / totalW);
      tree.style.transform = `scale(${scale})`;
      b.style.height = `${Math.ceil(height * scale)}px`;
    };
    fitTree();
  }
  window.addEventListener('resize', () => fitTree());

  // Refaire un tirage validé ou commencé : choix dans une fenêtre du site (garder ou non les combats disputés)
  function openRedo() {
    const dlg = document.getElementById('draw-redo');
    const played = cat.played || 0;
    const text = dlg.querySelector('[data-redo-text]');
    const box = dlg.querySelector('[data-redo-choices]');
    if (played) {
      text.textContent = `${played} combat${played > 1 ? 's ont' : ' a'} déjà été disputé${played > 1 ? 's' : ''} dans ce tableau. Que faire de ${played > 1 ? 'ces résultats' : 'ce résultat'} ?`;
      box.innerHTML = `<button type="button" class="draw-redo-choice" data-redo-mode="keep"><strong>Garder les combats disputés</strong><span>Les judokas qui ont déjà combattu restent à leur place avec leurs résultats ; les autres sont tirés à nouveau.</span></button>`
        + `<button type="button" class="draw-redo-choice is-danger" data-redo-mode="reset"><strong>Tout recommencer</strong><span>Les ${played} résultat${played > 1 ? 's' : ''} sont effacés (gardés dans le journal) et tout le tableau est tiré à nouveau.</span></button>`;
    } else {
      text.textContent = 'Le tirage validé sera remplacé par un nouveau tirage, à valider de nouveau.';
      box.innerHTML = `<button type="button" class="draw-redo-choice" data-redo-mode="reset"><strong>Refaire le tirage</strong><span>Têtes de série à leur place, les autres combattants tirés au hasard en séparant les clubs.</span></button>`;
    }
    dlg.showModal();
  }

  async function run(mode) {
    if (running || !cat) return;
    if (mode === undefined && (cat.locked || cat.played)) { openRedo(); return; }
    cat.mode = mode ?? cat.mode ?? null;
    running = true;
    $('[data-draw-again]').hidden = true;
    $('[data-draw-confirm]').hidden = true;
    try {
      const res = await JCL.api(`${base}/${cat.id}/lancer`, { method: 'POST', body: cat.mode ? { mode: cat.mode } : {} });
      cat.locked = false;
      const card = root.querySelector(`[data-cat="${cat.id}"]`);
      if (card) { const b = card.querySelector('[data-cat-state]'); b.textContent = 'À valider'; b.className = 'badge'; }
      show('bracket');
      renderBracket(res.size);
      const byId = new Map(cat.athletes.map((a) => [a.registration_id, a]));
      const pool = $('[data-draw-pool]');
      pool.innerHTML = cat.athletes.map((a) => `<li data-reg="${a.registration_id}" class="${a.seed ? 'is-seed' : ''}">${chip(a)}</li>`).join('');
      // Têtes de série d'abord, dans l'ordre, à leur place ; puis tous les autres dans le désordre
      const kept = new Set(res.kept || []);
      for (const s of res.slots.filter((x) => kept.has(x.registration_id))) {
        const a = byId.get(s.registration_id);
        const slot = $(`[data-slot="${s.slot}"]`);
        if (slot && a) { slot.querySelector('.db-who').innerHTML = chip(Object.assign({}, a, { seed: s.seed })); slot.classList.add('is-filled', 'is-kept'); }
        pool.querySelector(`[data-reg="${s.registration_id}"]`)?.classList.add('is-placed');
      }
      const seeds = res.slots.filter((s) => s.seed && !kept.has(s.registration_id)).sort((a, b) => a.seed - b.seed);
      const others = res.slots.filter((s) => !s.seed && !kept.has(s.registration_id));
      for (let i = others.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [others[i], others[j]] = [others[j], others[i]]; }
      const order = [...seeds, ...others];
      const total = order.length;
      for (let k = 0; k < total; k++) {
        const s = order[k];
        const a = byId.get(s.registration_id);
        const src = pool.querySelector(`[data-reg="${s.registration_id}"]`);
        const slot = $(`[data-slot="${s.slot}"]`);
        if (src) { src.classList.add('is-picked'); src.scrollIntoView({ block: 'nearest', inline: 'center' }); }
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
      $('[data-draw-progress]').textContent = `Tirage terminé : ${total + kept.size} judokas placés` + (kept.size ? ` (${kept.size} déjà engagés, gardés à leur place)` : '');
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
    if (e.target.closest('[data-draw-run]')) run(null);
    if (e.target.closest('[data-draw-redo]') && cat) openRedo();
    if (e.target.closest('[data-draw-again]')) run(cat.mode ?? null);
    if (e.target.closest('[data-draw-confirm]')) confirmDraw();
    if (e.target.closest('[data-dialog-open="draw-seeds"]') && cat) renderSeeds();
    const choice = e.target.closest('[data-redo-mode]');
    if (choice) { document.getElementById('draw-redo').close(); run(choice.dataset.redoMode); }
    if (e.target.closest('[data-seed-save]')) saveSeeds(false);
    if (e.target.closest('[data-seed-clear]')) saveSeeds(true);
    if (e.target.closest('[data-draw-fullscreen]')) {
      if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {});
    }
  });
})();
