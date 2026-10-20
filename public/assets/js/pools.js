/* Poules : glisser-déposer (poules entre tapis, athlètes entre poules, échange athlète sur athlète). */
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

  board.addEventListener('dragstart', (e) => {
    const athlete = e.target.closest('.athlete[draggable=true]');
    const pool = e.target.closest('.pool[draggable=true]');
    if (athlete) { drag = { type: 'athlete', id: athlete.dataset.reg }; athlete.classList.add('dragging'); }
    else if (pool) { drag = { type: 'pool', id: pool.dataset.pool }; pool.classList.add('dragging'); }
    else return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', drag.type + ':' + drag.id);
  });
  board.addEventListener('dragend', () => { document.querySelectorAll('.dragging, .drag-over, .drop-target').forEach((el) => el.classList.remove('dragging', 'drag-over', 'drop-target')); });
  board.addEventListener('dragover', (e) => {
    if (!drag) return;
    const col = e.target.closest('.mat-col'), pool = e.target.closest('.pool'), ath = e.target.closest('.athlete');
    if ((drag.type === 'pool' && col) || (drag.type === 'athlete' && (pool || ath))) {
      e.preventDefault();
      document.querySelectorAll('.drag-over, .drop-target').forEach((el) => el.classList.remove('drag-over', 'drop-target'));
      if (drag.type === 'pool') col.classList.add('drag-over');
      else if (ath && ath.dataset.reg !== drag.id) ath.classList.add('drop-target');
    }
  });
  board.addEventListener('drop', (e) => {
    if (!drag) return;
    e.preventDefault();
    const d = drag; drag = null;
    if (d.type === 'pool') {
      const col = e.target.closest('.mat-col');
      if (!col) return;
      const cards = [...col.querySelectorAll('.pool')].filter((p) => p.dataset.pool !== d.id);
      let position = cards.length + 1;
      for (let i = 0; i < cards.length; i++) { const r = cards[i].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) { position = i + 1; break; } }
      send({ op: 'move_pool', pool_id: d.id, tatami_id: col.dataset.tatami === '0' ? null : col.dataset.tatami, position });
    } else {
      const ath = e.target.closest('.athlete'), pool = e.target.closest('.pool');
      if (ath && ath.dataset.reg !== d.id) send({ op: 'swap', a: d.id, b: ath.dataset.reg });
      else if (pool) send({ op: 'move_athlete', registration_id: d.id, pool_id: pool.dataset.pool });
    }
  });
})();
