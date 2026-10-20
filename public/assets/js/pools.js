/* Poules : glisser-déposer (poules entre tapis, athlètes entre poules, échange athlète sur athlète).
   Souris : glisser-déposer natif. Tablette : appui long puis glisser du doigt. */
(function () {
  'use strict';
  const board = document.querySelector('[data-pool-board]');
  if (!board) return;
  const api = board.dataset.api;
  let drag = null; // { type: 'pool'|'athlete', id }

  async function send(body) {
    try {
      const res = await JCL.api(api, { method: 'POST', body });
      JCL.toast(res.message, 'success');
      setTimeout(() => location.reload(), 350);
    } catch (e) {
      if (e.data && e.data.needs_exception) {
        const ok = await JCL.confirm(e.message, { title: 'Exception à confirmer', confirmLabel: 'Autoriser l\'exception' });
        if (ok) return send(Object.assign({}, body, { allow_exception: 1 }));
        return;
      }
      JCL.toast(e.message, 'error', 6000);
    }
  }

  const clear = () => document.querySelectorAll('.dragging, .drag-over, .drop-target, .pool-target').forEach((el) => el.classList.remove('dragging', 'drag-over', 'drop-target', 'pool-target'));

  /** Met en évidence la cible sous le pointeur ; renvoie true si le dépôt y est possible. */
  function hover(target) {
    if (!drag || !target) return false;
    const col = target.closest('.mat-col'), pool = target.closest('.pool'), ath = target.closest('.athlete');
    document.querySelectorAll('.drag-over, .drop-target, .pool-target').forEach((el) => el.classList.remove('drag-over', 'drop-target', 'pool-target'));
    if (drag.type === 'pool' && col) { col.classList.add('drag-over'); return true; }
    if (drag.type === 'athlete' && (pool || ath)) {
      if (ath && ath.dataset.reg !== drag.id) ath.classList.add('drop-target');
      else if (pool) pool.classList.add('pool-target');
      return true;
    }
    return false;
  }

  function drop(target, clientY) {
    if (!drag || !target) return;
    const d = drag; drag = null;
    clear();
    if (d.type === 'pool') {
      const col = target.closest('.mat-col');
      if (!col) return;
      const cards = [...col.querySelectorAll('.pool')].filter((p) => p.dataset.pool !== d.id);
      let position = cards.length + 1;
      for (let i = 0; i < cards.length; i++) { const r = cards[i].getBoundingClientRect(); if (clientY < r.top + r.height / 2) { position = i + 1; break; } }
      send({ op: 'move_pool', pool_id: d.id, tatami_id: col.dataset.tatami === '0' ? null : col.dataset.tatami, position });
    } else {
      const ath = target.closest('.athlete'), pool = target.closest('.pool');
      if (ath && ath.dataset.reg !== d.id) send({ op: 'swap', a: d.id, b: ath.dataset.reg });
      else if (pool && !pool.contains(document.querySelector('.athlete[data-reg="' + d.id + '"]'))) send({ op: 'move_athlete', registration_id: d.id, pool_id: pool.dataset.pool });
    }
  }

  function start(el) {
    const athlete = el.closest('.athlete[draggable=true]');
    const pool = el.closest('.pool[draggable=true]');
    if (athlete) { drag = { type: 'athlete', id: athlete.dataset.reg }; athlete.classList.add('dragging'); return athlete; }
    if (pool) { drag = { type: 'pool', id: pool.dataset.pool }; pool.classList.add('dragging'); return pool; }
    return null;
  }

  // Souris : glisser-déposer natif
  board.addEventListener('dragstart', (e) => {
    if (!start(e.target)) return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', drag.type + ':' + drag.id);
  });
  board.addEventListener('dragend', () => { drag = null; clear(); });
  board.addEventListener('dragover', (e) => { if (hover(e.target)) e.preventDefault(); });
  board.addEventListener('drop', (e) => { e.preventDefault(); drop(e.target, e.clientY); });

  // Tablette / écran tactile : appui long (250 ms) puis glisser du doigt
  let touch = null;
  board.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' || !e.target.closest('[draggable=true]')) return;
    const startEl = e.target;
    touch = { id: e.pointerId, x: e.clientX, y: e.clientY, ghost: null, timer: setTimeout(() => {
      const src = start(startEl);
      if (!src) { touch = null; return; }
      const g = src.cloneNode(true);
      g.classList.add('drag-ghost');
      g.style.width = src.getBoundingClientRect().width + 'px';
      document.body.appendChild(g);
      touch.ghost = g;
      move(touch.x, touch.y);
      if (navigator.vibrate) navigator.vibrate(20);
    }, 250) };
  });
  function move(x, y) {
    if (!touch || !touch.ghost) return;
    touch.ghost.style.transform = 'translate(' + (x - 30) + 'px,' + (y - 20) + 'px)';
    touch.ghost.hidden = true;
    hover(document.elementFromPoint(x, y));
    touch.ghost.hidden = false;
  }
  board.addEventListener('pointermove', (e) => {
    if (!touch || e.pointerId !== touch.id) return;
    if (!touch.ghost) { if (Math.hypot(e.clientX - touch.x, e.clientY - touch.y) > 8) { clearTimeout(touch.timer); touch = null; } return; }
    e.preventDefault();
    touch.x = e.clientX; touch.y = e.clientY;
    move(e.clientX, e.clientY);
  });
  const end = (e) => {
    if (!touch || e.pointerId !== touch.id) return;
    clearTimeout(touch.timer);
    if (touch.ghost) {
      touch.ghost.remove();
      drop(document.elementFromPoint(e.clientX, e.clientY), e.clientY);
    }
    touch = null;
  };
  board.addEventListener('pointerup', end);
  board.addEventListener('pointercancel', (e) => { if (touch) { clearTimeout(touch.timer); if (touch.ghost) touch.ghost.remove(); touch = null; drag = null; clear(); } });
  board.addEventListener('touchmove', (e) => { if (touch && touch.ghost) e.preventDefault(); }, { passive: false });
})();
