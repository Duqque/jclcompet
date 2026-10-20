/* Interface commissaire sportif.
   - Les actions partent dans une file, une à la fois, avec la version connue du combat (verrou optimiste)
     et un identifiant d'événement unique : un renvoi après coupure n'est jamais compté deux fois.
   - Le chronomètre s'affiche localement à partir des horodatages serveur (un rechargement ne le réinitialise pas).
   - L'écran TV lit les mêmes données sur le serveur : aucune double saisie. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('cm-config').textContent);
  const root = document.querySelector('[data-cm]');
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const model = cfg.model || {};
  const TH = model.osaekomi || { yuko: 5, wazaari: 10, ippon: 20 };
  const WZ = model.wazaari_for_ippon || 2;
  const SH = model.shido_for_hansoku || 3;
  if (cfg.color === 'blue') document.body.classList.add('blue');

  let data = null;          // état du tapis (courant + restants)
  let offset = 0;           // décalage horloge serveur
  const queue = [];         // actions en attente d'envoi
  let sending = false;
  let retryDelay = 1000;
  let autoToketaSent = null;
  let pollTimer = null;

  const now = () => Date.now() + offset;
  const cur = () => (data ? data.current : null);
  const esc = JCL.esc;
  const fullName = (f) => f.first_name + ' ' + f.last_name;

  // ---------------------------------------------------------------- réseau
  function setNet(mode, label) {
    const n = $('[data-net]');
    n.classList.toggle('offline', mode === 'offline');
    n.classList.toggle('syncing', mode === 'syncing');
    $('[data-net-label]').textContent = label;
  }

  async function load() {
    try {
      const t0 = Date.now();
      const d = await JCL.api(cfg.stateUrl);
      offset = d.server_now_ms + (Date.now() - t0) / 2 - Date.now();
      // Les données reçues ne remplacent pas un état plus récent issu d'une action en cours d'envoi.
      if (!queue.length) {
        data = d;
        render();
      }
      if (!queue.length) setNet('ok', 'Connecté');
    } catch (e) {
      if (e.status === 403 || e.status === 401) { JCL.toast(e.message, 'error', 8000); setNet('offline', 'Accès refusé'); return; }
      setNet('offline', 'Hors ligne');
    }
  }

  function schedulePoll() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(async () => { if (!sending && !queue.length) await load(); schedulePoll(); }, document.hidden ? 6000 : 2000);
  }

  /** Ajoute une action pour le combat courant. */
  function act(action, extra = {}) {
    const c = cur();
    if (!c) return;
    queue.push({ boutId: c.id, body: Object.assign({ action, client_event_id: JCL.uid(), at_ms: Math.round(now()) }, extra) });
    flush();
  }

  async function flush() {
    if (sending || !queue.length) return;
    sending = true;
    setNet('syncing', queue.length > 1 ? 'Envoi… (' + queue.length + ')' : 'Envoi…');
    const item = queue[0];
    const c = cur();
    item.body.version = c && c.id === item.boutId ? c.version : item.body.version;
    try {
      const t0 = Date.now();
      const res = await JCL.api(cfg.actionUrl.replace('__ID__', item.boutId), { method: 'POST', body: item.body });
      offset = res.state.server_now_ms + (Date.now() - t0) / 2 - Date.now();
      queue.shift();
      retryDelay = 1000;
      applyState(res.state);
      if (item.body.action === 'validate') { JCL.toast('Résultat validé : classements et écran TV mis à jour.', 'success'); }
      setNet('ok', 'Connecté');
    } catch (e) {
      if (e.offline || e.status >= 500) {
        // Coupure réseau : la même action (même identifiant) sera renvoyée, sans risque de double comptage.
        setNet('offline', 'Hors ligne — ' + queue.length + ' action(s) en attente');
        sending = false;
        setTimeout(flush, retryDelay);
        retryDelay = Math.min(retryDelay * 2, 10000);
        return;
      }
      queue.shift();
      if (e.status === 409 && e.data && e.data.state) {
        applyState(e.data.state);
        queue.length = 0;
        JCL.toast(e.message, 'error', 6000);
      } else if (e.status === 422 && e.data && e.data.needs_decision) {
        decision();
      } else {
        JCL.toast(e.message, 'error', 5000);
      }
      setNet('ok', 'Connecté');
    } finally {
      if (sending) sending = false;
    }
    if (queue.length) flush();
  }

  function applyState(state) {
    if (!data) return;
    data.current = state;
    const idx = data.remaining.findIndex((b) => b.id === state.id);
    if (state.status === 'validated' || state.status === 'cancelled') { if (idx >= 0) data.remaining.splice(idx, 1); }
    else if (idx >= 0) data.remaining[idx] = Object.assign({}, data.remaining[idx], state);
    render();
  }

  async function select(boutId) {
    try {
      const res = await JCL.api(cfg.selectUrl, { method: 'POST', body: { bout_id: boutId } });
      if (res.current) applyState(res.current); else { data.current = null; render(); }
      load();
    } catch (e) { JCL.toast(e.message, 'error', 5000); }
  }

  // ---------------------------------------------------------------- dialogues
  async function choose(title, message, options) {
    return new Promise((resolve) => {
      const d = document.createElement('dialog');
      d.innerHTML = '<div class="dialog-body"><h3></h3><p class="msg"></p><div class="btn-group" data-opts style="flex-direction:column;align-items:stretch"></div></div><div class="dialog-actions"><button class="btn btn-ghost" type="button" data-cancel>Annuler</button></div>';
      d.querySelector('h3').textContent = title;
      d.querySelector('.msg').textContent = message;
      options.forEach((o) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-lg ' + (o.cls || 'btn-ghost'); b.textContent = o.label; b.addEventListener('click', () => { d.close(); d.remove(); resolve(o.value); }); d.querySelector('[data-opts]').appendChild(b); });
      d.querySelector('[data-cancel]').addEventListener('click', () => { d.close(); d.remove(); resolve(null); });
      d.addEventListener('cancel', () => { d.remove(); resolve(null); });
      document.body.appendChild(d); d.showModal();
    });
  }

  async function decision() {
    const c = cur();
    if (!c) return;
    const opts = [
      { label: 'Victoire de ' + fullName(c.a) + ' (décision)', value: 'a', cls: 'btn-red' },
      { label: 'Victoire de ' + fullName(c.b) + ' (décision)', value: 'b', cls: 'btn-ghost' },
    ];
    if (c.clock.golden_score_mode !== 'none' && !c.clock.golden_score && elapsedMs(c.clock) >= c.clock.duration_ms) opts.unshift({ label: 'Passer en golden score', value: 'gs', cls: 'btn-yellow' });
    const v = await choose('Égalité', 'Les avantages sont à égalité. Choisissez la suite.', opts);
    if (v === 'gs') act('golden_score');
    else if (v) act('finish', { winner_side: v });
  }

  async function forfeit() {
    const c = cur();
    const w = await choose('Forfait ou abandon', 'Qui remporte le combat ?', [
      { label: fullName(c.a), value: 'a', cls: 'btn-ghost' }, { label: fullName(c.b), value: 'b', cls: 'btn-ghost' }]);
    if (!w) return;
    const m = await choose('Motif', '', [{ label: 'Forfait de l\'adversaire (fusen-gachi)', value: 'fusen_gachi' }, { label: 'Abandon de l\'adversaire (kiken-gachi)', value: 'kiken_gachi' }]);
    if (m) act('forfeit', { winner_side: w, method: m });
  }

  // ---------------------------------------------------------------- rendu
  function elapsedMs(clock) {
    let e = clock.elapsed_ms;
    if (clock.running && clock.started_at_ms) e += Math.max(0, now() - clock.started_at_ms);
    return e;
  }
  const fmt = (ms) => { const t = Math.max(0, Math.ceil(ms / 1000)); return Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0'); };

  function renderQueue() {
    const list = data ? data.remaining : [];
    $('[data-remaining-count]').textContent = '(' + list.length + ')';
    $('[data-done-count]').textContent = data ? data.done_count + ' combat(s) validé(s) sur ce tapis' : '';
    const c = cur();
    $('[data-queue]').innerHTML = list.length ? list.map((b) =>
      '<li class="' + (c && c.id === b.id ? 'current' : '') + (b.status === 'finished' ? ' finished' : '') + '"><button type="button" data-select="' + b.id + '">' +
      '<span class="num">' + b.order_on_tatami + '</span><span class="who">' + esc(fullName(b.a)) + '<br>' + esc(fullName(b.b)) +
      '<small>' + esc(b.category) + (b.weight_label ? ' ' + esc(b.weight_label) + ' kg' : '') + ' · ' + esc(b.pool_label.replace(/^.*· /, '')) + ' · ' + b.pool_position + '/' + b.pool_bouts + (b.status !== 'scheduled' ? ' · ' + esc(b.status_label) : '') + '</small></span></button></li>'
    ).join('') : '<li class="small" style="padding:1rem;color:rgba(255,255,255,.7)">Aucun combat restant sur ce tapis.</li>';
  }

  function boxes(n, on, letter) {
    return Array.from({ length: n }, (_, i) => '<i class="' + (i < on ? 'on' : '') + '">' + letter + '</i>').join('');
  }

  function render() {
    renderQueue();
    const c = cur();
    const head = $('[data-bout-head]');
    const fighters = $('[data-fighters]');
    const foot = $('[data-foot]');
    if (!c) {
      head.innerHTML = '<div><h1>Aucun combat en cours</h1><p class="meta">Choisissez un combat dans la liste de gauche ou chargez le suivant.</p></div>';
      fighters.hidden = true;
      foot.innerHTML = data && data.remaining.length ? '<button class="btn btn-red btn-display" type="button" data-next>Charger le combat suivant</button>' : '';
      $('[data-hajime]').disabled = true; $('[data-finish-top]').disabled = true;
      return;
    }
    const catLabel = c.category + (c.weight_label ? ' ' + c.weight_label + ' kg' : '');
    const title = c.pool_label && c.pool_label.indexOf(c.category) === 0 ? c.pool_label : catLabel + ' — ' + c.pool_label;
    head.innerHTML = '<div><h1>' + esc(title) + '</h1><p class="meta">' +
      (c.phase === 'final' ? 'Barrage' : 'Combat ' + c.pool_position + '/' + c.pool_bouts) + ' · ordre ' + c.order_on_tatami + ' sur le tapis · durée ' + fmt(c.clock.duration_ms) +
      (c.clock.golden_score ? ' · <strong style="color:var(--red)">Golden score</strong>' : '') + '</p></div><span class="badge ' +
      ({ validated: 'badge-green', finished: 'badge-orange', in_progress: 'badge-red' }[c.status] || '') + '">' + esc(c.status_label) + '</span>';
    fighters.hidden = false;
    const editable = c.status === 'in_progress';
    ['a', 'b'].forEach((s) => {
      const f = c[s];
      const card = $('[data-side="' + s + '"]', fighters);
      $('[data-tag]', card).textContent = s === 'a' ? (cfg.color === 'blue' ? 'AO' : 'AKA') : 'SHIRO';
      $('[data-name]', card).textContent = fullName(f);
      $('[data-club]', card).textContent = f.club || '';
      $('[data-wz-max]', card).textContent = WZ;
      $('[data-sh-max]', card).textContent = SH;
      $('[data-wz-boxes]', card).innerHTML = boxes(WZ, f.wazaari, 'W');
      $('[data-sh-boxes]', card).innerHTML = boxes(SH, f.shido, 'S');
      $('[data-yuko]', card).textContent = f.yuko;
      $('[data-ippon]', card).textContent = f.ippon;
      $('[data-yuko-line]', card).hidden = model.use_yuko === false;
      $('[data-yuko-act]', card).hidden = model.use_yuko === false;
      ['wazaari', 'yuko', 'shido'].forEach((k) => { const el = $('[data-count="' + k + '"]', card); if (el) el.textContent = f[k]; });
      $$('[data-score]', card).forEach((b) => { b.disabled = !editable; });
      $$('[data-undo]', card).forEach((b) => { b.disabled = !(editable || c.status === 'finished') || !f[b.dataset.undo]; });
      $('[data-osae]', card).disabled = !(editable && c.clock.running) && !(c.osaekomi && c.osaekomi.side === s);
      card.classList.toggle('winner', (c.status === 'finished' || c.status === 'validated') && c.winner_side === s);
    });
    $$('[data-osae-th]').forEach((el) => { el.textContent = (model.use_yuko === false ? '' : TH.yuko + ' s · ') + TH.wazaari + ' s · ' + TH.ippon + ' s'; });

    const play = $('[data-hajime]');
    play.disabled = !(c.status === 'scheduled' || c.status === 'in_progress');
    play.classList.toggle('running', c.clock.running);
    play.textContent = c.clock.running ? '❚❚' : '▶';
    play.setAttribute('aria-label', c.clock.running ? 'Matte (arrêter le temps)' : 'Hajime (démarrer le temps)');
    $('[data-finish-top]').disabled = c.status !== 'in_progress';

    const timeOver = elapsedMs(c.clock) >= c.clock.duration_ms && !c.clock.golden_score;
    const tie = c.a.ippon === c.b.ippon && c.a.wazaari === c.b.wazaari && c.a.yuko === c.b.yuko;
    let html = '';
    if (c.status === 'scheduled') {
      html = '<button class="btn btn-yellow btn-display" type="button" data-start>Hajime — démarrer le combat</button><button class="btn btn-ghost" type="button" data-forfeit>Forfait / abandon</button>';
    } else if (c.status === 'in_progress') {
      if (timeOver && tie && !c.clock.running && c.clock.golden_score_mode !== 'none') html += '<button class="btn btn-yellow btn-display" type="button" data-gs>Golden score</button>';
      html += '<button class="btn btn-red btn-display" type="button" data-finish>Fin du combat</button><button class="btn btn-ghost" type="button" data-forfeit>Forfait / abandon</button>';
      if (!c.clock.running) html += '<button class="btn btn-ghost" type="button" data-reset>Réinitialiser</button>';
    } else if (c.status === 'finished') {
      const w = c[c.winner_side];
      html = '<div class="result">Victoire de <strong>' + esc(fullName(w)) + '</strong> — ' + esc(c.win_method_label) + '. Vérifiez puis validez le résultat.</div>' +
        '<button class="btn btn-green btn-display" type="button" data-validate>Valider le résultat</button><button class="btn btn-ghost" type="button" data-reopen>Reprendre le combat</button>';
    } else if (c.status === 'validated') {
      const w = c[c.winner_side];
      html = '<div class="result">Résultat validé : victoire de <strong>' + esc(fullName(w)) + '</strong> — ' + esc(c.win_method_label) + (c.validated_by ? ' (validé par ' + esc(c.validated_by) + ')' : '') + '.</div>' +
        (data.remaining.length ? '<button class="btn btn-red btn-display" type="button" data-next>Combat suivant →</button>' : '<div class="result" style="border-color:var(--green)">Tous les combats de ce tapis sont terminés.</div>') +
        '<a class="btn btn-ghost" href="' + esc(cfg.correctUrl.replace('__ID__', c.id)) + '">Corriger ce résultat</a>';
    }
    foot.innerHTML = html;
  }

  // ---------------------------------------------------------------- boucle d'affichage
  function tick() {
    const c = cur();
    const clock = $('[data-clock]');
    if (c) {
      const e = elapsedMs(c.clock);
      let txt;
      if (c.status === 'finished' || c.status === 'validated') txt = c.clock.golden_score ? 'GS ' + fmt(c.clock.elapsed_ms) : fmt(c.clock.duration_ms - c.clock.elapsed_ms);
      else if (c.clock.golden_score) txt = 'GS ' + (c.clock.golden_score_mode === 'limited' && c.clock.golden_score_ms ? fmt(c.clock.golden_score_ms - e) : fmt(e));
      else txt = fmt(c.clock.duration_ms - e);
      clock.textContent = txt;
      clock.classList.toggle('gs', c.clock.golden_score);
      clock.classList.toggle('over', c.status === 'in_progress' && !c.clock.golden_score && e >= c.clock.duration_ms && c.clock.running);
      ['a', 'b'].forEach((s) => {
        const btn = $('[data-side="' + s + '"] [data-osae]');
        const on = c.osaekomi && c.osaekomi.side === s && c.status === 'in_progress';
        btn.classList.toggle('on', !!on);
        if (on) {
          const held = Math.max(0, now() - c.osaekomi.started_at_ms);
          const sec = held / 1000;
          const award = sec >= TH.ippon ? 'ippon' : sec >= TH.wazaari ? 'wazaari' : (model.use_yuko !== false && sec >= TH.yuko ? 'yuko' : null);
          $('[data-osae-label]', btn).textContent = 'Toketa — ' + Math.floor(sec) + ' s' + (award ? ' (' + { ippon: 'ippon', wazaari: 'waza-ari', yuko: 'yuko' }[award] + ')' : '');
          $('[data-osae-fill]', btn).style.width = Math.min(100, sec / TH.ippon * 100) + '%';
          // Ippon atteint : fin d'immobilisation envoyée automatiquement.
          if (sec >= TH.ippon && autoToketaSent !== c.osaekomi.started_at_ms) {
            autoToketaSent = c.osaekomi.started_at_ms;
            act('toketa', { held_ms: Math.round(held) });
          }
        } else {
          $('[data-osae-label]', btn).textContent = 'Osaekomi';
          $('[data-osae-fill]', btn).style.width = '0';
        }
      });
    } else {
      clock.textContent = '0:00';
    }
    requestAnimationFrame(tick);
  }

  // ---------------------------------------------------------------- interactions
  function hajimeMatte() {
    const c = cur();
    if (!c || !(c.status === 'scheduled' || c.status === 'in_progress')) return;
    act(c.clock.running ? 'pause' : 'start');
  }

  root.addEventListener('click', async (e) => {
    const t = e.target.closest('button, a');
    if (!t) return;
    const c = cur();
    if (t.matches('[data-select]')) {
      const id = parseInt(t.dataset.select, 10);
      if (c && c.id === id) return;
      if (c && (c.status === 'in_progress' || c.status === 'finished')) { JCL.toast('Terminez et validez le combat en cours avant d\'en charger un autre.', 'error'); return; }
      select(id); return;
    }
    if (t.matches('[data-hajime], [data-start]')) { hajimeMatte(); return; }
    if (t.matches('[data-next]')) { select(null); return; }
    if (!c) return;
    const side = t.closest('[data-side]') ? t.closest('[data-side]').dataset.side : null;
    if (t.matches('[data-score]')) {
      const kind = t.dataset.score;
      if (kind === 'hansoku') { const ok = await JCL.confirm('Hansoku-make pour ' + fullName(c[side]) + ' ? Le combat sera perdu.', { danger: true, confirmLabel: 'Hansoku-make' }); if (!ok) return; }
      act('score', { side, kind, delta: 1 }); return;
    }
    if (t.matches('[data-undo]')) { act('score', { side, kind: t.dataset.undo, delta: -1 }); return; }
    if (t.matches('[data-osae]')) {
      if (c.osaekomi && c.osaekomi.side === side) act('toketa', { held_ms: Math.round(now() - c.osaekomi.started_at_ms) });
      else act('osaekomi', { side });
      return;
    }
    if (t.matches('[data-finish], [data-finish-top]')) { act('finish'); return; }
    if (t.matches('[data-gs]')) { act('golden_score'); return; }
    if (t.matches('[data-validate]')) { act('validate'); return; }
    if (t.matches('[data-reopen]')) { act('reopen'); return; }
    if (t.matches('[data-forfeit]')) { forfeit(); return; }
    if (t.matches('[data-reset]')) {
      const ok = await JCL.confirm('Réinitialiser le combat ? Le temps et les scores repartent de zéro.', { danger: true, confirmLabel: 'Réinitialiser' });
      if (ok) act('reset');
    }
  });

  // Barre d'espace : Hajime / Matte (hors champs de saisie et boîtes de dialogue)
  document.addEventListener('keydown', (e) => {
    if (e.code === 'Space' && !e.repeat && !e.target.closest('input, textarea, select, dialog, button')) { e.preventDefault(); hajimeMatte(); }
  });
  // Retour aux paramètres : la saisie reste sur le serveur, l'écran TV continue d'afficher le combat.
  $('[data-back]').addEventListener('click', (e) => {
    if (queue.length) { e.preventDefault(); JCL.toast('Des actions sont en cours d\'envoi : patientez avant de quitter.', 'error'); }
  });
  window.addEventListener('beforeunload', (e) => { if (queue.length) { e.preventDefault(); e.returnValue = ''; } });
  window.addEventListener('online', () => { retryDelay = 500; flush(); load(); });

  load().then(() => { requestAnimationFrame(tick); schedulePoll(); });
})();
