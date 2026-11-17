/* Régie vidéo d'un tapis (Chrome / Edge) :
   - 2 caméras (webcam de l'ordinateur ou téléphone / iPad relié en WebRTC sur le Wi-Fi de la salle) ;
   - chaque caméra est enregistrée en continu en mémoire (MediaRecorder → MediaSource) : retour en arrière en direct,
     image par image, ralenti, zoom et déplacement, choix de l'angle ;
   - programme 1280×720 : caméra à l'antenne + incrustation du combat en haut à gauche (chrono et scores du commissaire)
     et, pendant la vidéo-arbitrage, le ralenti avec le bandeau « Vidéo-arbitrage en cours » ;
   - la fenêtre « Sortie programme » affiche ce programme : OBS la capture et l'envoie sur YouTube. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('video-config').textContent);
  const root = document.querySelector('[data-regie]');
  if (!root) return;
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const W = 1280, H = 720, FPS = 30;
  const KEEP_S = 300; // durée gardée en mémoire par caméra (5 min)
  const STORE = 'jcl-video-' + cfg.tatamiId;
  const MIME = ['video/webm;codecs=vp8', 'video/webm;codecs=vp9', 'video/webm']
    .find((m) => window.MediaRecorder && MediaRecorder.isTypeSupported(m) && window.MediaSource && MediaSource.isTypeSupported(m));

  let state = null;
  let offset = 0; // horloge serveur − horloge locale
  const serverNow = () => Date.now() + offset;
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const store = { get(k, d) { try { const v = localStorage.getItem(STORE + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(STORE + k, JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } } };
  const toast = (m, t) => (window.JCL && JCL.toast ? JCL.toast(m, t || 'info') : console.log(m));
  const api = (url, body) => JCL.api(url, body === undefined ? {} : { method: 'POST', body });

  // ------------------------------------------------------------------ Caméra : direct + enregistrement continu
  class Track {
    constructor(slot) {
      this.slot = slot;
      this.card = $(`[data-cam="${slot}"]`);
      this.live = $('[data-cam-live]', this.card);
      this.replay = document.createElement('video');
      this.replay.muted = true; this.replay.playsInline = true; this.replay.preload = 'auto';
      $('[data-hidden-media]').appendChild(this.replay);
      this.stream = null; this.rec = null; this.sb = null; this.ms = null; this.queue = []; this.busy = false;
      this.t0 = null; // horloge locale (ms) correspondant au temps 0 de l'enregistrement
      this.source = '';
    }
    attach(stream) {
      this.detach(false);
      this.stream = stream;
      this.live.srcObject = stream;
      this.live.play().catch(() => {});
      stream.getVideoTracks().forEach((t) => t.addEventListener('ended', () => { if (this.stream === stream) this.detach(); }));
      this.record();
      this.status();
    }
    detach(updateUi = true) {
      try { if (this.rec && this.rec.state !== 'inactive') this.rec.stop(); } catch (e) { /* déjà arrêté */ }
      if (this.stream && this.source !== 'phone') this.stream.getTracks().forEach((t) => t.stop());
      this.stream = null; this.rec = null; this.sb = null; this.queue = []; this.t0 = null;
      this.live.srcObject = null;
      if (this.ms) { try { URL.revokeObjectURL(this.replay.src); } catch (e) { /* rien */ } }
      this.replay.removeAttribute('src'); this.replay.load(); this.ms = null;
      if (updateUi) this.status();
    }
    record() {
      if (!MIME) { toast("Ce navigateur ne peut pas enregistrer la vidéo : utilisez Chrome ou Edge à jour.", 'error'); return; }
      const ms = new MediaSource();
      this.ms = ms;
      this.replay.src = URL.createObjectURL(ms);
      ms.addEventListener('sourceopen', () => {
        if (this.ms !== ms) return;
        this.sb = ms.addSourceBuffer(MIME);
        this.sb.mode = 'segments';
        this.sb.addEventListener('updateend', () => { this.busy = false; this.sync(); this.pump(); });
        const opts = { mimeType: MIME, videoBitsPerSecond: 2500000, videoKeyFrameIntervalDuration: 1000 };
        try { this.rec = new MediaRecorder(this.stream, opts); } catch (e) { this.rec = new MediaRecorder(this.stream, { mimeType: MIME }); }
        this.rec.ondataavailable = (e) => { if (e.data && e.data.size) { this.queue.push(e.data); this.pump(); } };
        this.rec.start(500);
      }, { once: true });
    }
    async pump() {
      if (!this.sb || this.busy || !this.queue.length || this.ms.readyState !== 'open') return;
      this.busy = true;
      const blob = this.queue.shift();
      const buf = await blob.arrayBuffer();
      try { this.sb.appendBuffer(buf); } catch (e) {
        this.busy = false;
        if (e.name === 'QuotaExceededError') { this.queue.unshift(blob); this.trim(KEEP_S / 2); } else { console.warn(e); }
      }
    }
    /** Recalage horloge locale ↔ temps de l'enregistrement (le plus petit délai observé). */
    sync() {
      const end = this.end();
      if (!end) return;
      const t0 = Date.now() - end * 1000;
      this.t0 = this.t0 == null ? t0 : Math.min(this.t0 + 2, t0); // léger glissement autorisé
    }
    start() { const b = this.sb && this.sb.buffered; return b && b.length ? b.start(0) : 0; }
    end() { const b = this.sb && this.sb.buffered; return b && b.length ? b.end(b.length - 1) : 0; }
    ready() { return this.end() > 0.5 && this.t0 != null; }
    toMedia(wall) { return (wall - this.t0) / 1000; }
    toWall(media) { return this.t0 + media * 1000; }
    trim(keep = KEEP_S) {
      if (!this.sb || this.sb.updating) return;
      const end = this.end(), start = this.start();
      if (end - start > keep + 20) { this.busy = true; try { this.sb.remove(0, end - keep); } catch (e) { this.busy = false; } }
    }
    status() {
      const el = $('[data-cam-state]', this.card);
      const ok = !!this.stream;
      el.textContent = ok ? (this.source === 'phone' ? 'Téléphone connecté' : 'Webcam active') : (this.source === 'phone' ? 'En attente du téléphone' : 'Aucune source');
      el.className = 'vr-cam-state' + (ok ? ' ok' : this.source === 'phone' ? ' wait' : '');
    }
  }
  const tracks = { 1: new Track(1), 2: new Track(2) };
  setInterval(() => Object.values(tracks).forEach((t) => t.trim()), 15000);

  // ------------------------------------------------------------------ Sources : webcams et téléphones
  async function listDevices() {
    let devices = [];
    try { devices = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput'); } catch (e) { /* rien */ }
    $$('[data-cam-source]').forEach((sel) => {
      const cur = sel.value;
      $$('option[data-device]', sel).forEach((o) => o.remove());
      devices.forEach((d, i) => {
        const o = document.createElement('option');
        o.value = d.deviceId; o.dataset.device = '1'; o.textContent = d.label || ('Caméra ' + (i + 1));
        sel.appendChild(o);
      });
      sel.value = cur;
    });
    $('[data-allow-cams]').hidden = devices.some((d) => d.label);
    return devices;
  }

  async function useDevice(slot, deviceId) {
    const tr = tracks[slot];
    tr.source = deviceId;
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: FPS } } });
      tr.attach(stream);
    } catch (e) { toast('Caméra indisponible : ' + e.message, 'error'); tr.status(); }
  }

  async function choose(slot, value) {
    const tr = tracks[slot];
    const card = tr.card;
    const wasPhone = tr.source === 'phone';
    if (peers[slot]) { peers[slot].pc.close(); delete peers[slot]; }
    tr.detach();
    $('[data-cam-phone]', card).hidden = value !== 'phone';
    store.set('src' + slot, value);
    if (wasPhone && value !== 'phone') api(cfg.cameraRemoveUrl.replace('__SLOT__', slot), {}).catch(() => {});
    if (value === 'phone') {
      tr.source = 'phone'; tr.status();
      await newPhoneCode(slot);
    } else if (value) {
      await useDevice(slot, value);
    } else { tr.source = ''; tr.status(); }
  }

  async function newPhoneCode(slot) {
    const card = tracks[slot].card;
    try {
      const r = await api(cfg.cameraUrl.replace('__SLOT__', slot), {});
      $('[data-cam-code]', card).textContent = r.code;
      $('[data-cam-device]', card).textContent = 'Code valable 24 h. Un nouveau code déconnecte l\'ancien téléphone.';
    } catch (e) { toast(e.message, 'error'); }
  }

  // Réponse WebRTC aux téléphones (non-trickle : la description contient tous les candidats)
  const peers = {};
  const iceDone = (pc, ms) => new Promise((res) => {
    if (pc.iceGatheringState === 'complete') return res();
    const t = setTimeout(res, ms);
    pc.addEventListener('icegatheringstatechange', () => { if (pc.iceGatheringState === 'complete') { clearTimeout(t); res(); } });
  });
  async function answerPhone(slot, cam) {
    if (peers[slot]) peers[slot].pc.close();
    const pc = new RTCPeerConnection({ iceServers: cfg.ice || [] });
    const peer = { pc, session: cam.session };
    peers[slot] = peer;
    pc.ontrack = (e) => { if (peers[slot] === peer) tracks[slot].attach(e.streams[0] || new MediaStream([e.track])); };
    pc.onconnectionstatechange = () => {
      if (['failed', 'closed'].includes(pc.connectionState) && peers[slot] === peer) { tracks[slot].detach(); }
    };
    try {
      await pc.setRemoteDescription({ type: 'offer', sdp: cam.offer });
      await pc.setLocalDescription(await pc.createAnswer());
      await iceDone(pc, 2500);
      await api(cfg.answerUrl.replace('__SLOT__', slot), { session: cam.session, sdp: pc.localDescription.sdp });
    } catch (e) { if (e.status !== 409) toast('Caméra ' + slot + ' : ' + e.message, 'error'); }
  }
  function handleCameras(list) {
    (list || []).forEach((c) => {
      const tr = tracks[c.slot];
      if (!tr || tr.source !== 'phone') return;
      $('[data-cam-device]', tr.card).textContent = c.device ? 'Appareil : ' + c.device + (c.online ? '' : ' (hors ligne)') : $('[data-cam-device]', tr.card).textContent;
      if (c.offer && c.session && (!peers[c.slot] || peers[c.slot].session !== c.session)) answerPhone(c.slot, c);
    });
  }

  // ------------------------------------------------------------------ Vidéo-arbitrage
  const v = { active: false, onAir: true, angle: 1, playing: false, rate: 1, zoom: 1, cx: 0.5, cy: 0.5 };
  let programCam = store.get('program', 1);
  const cur = () => tracks[v.angle];
  const wallNow = () => Date.now();

  function playheadWall() {
    const t = cur();
    return v.active && t.ready() ? t.toWall(t.replay.currentTime) : wallNow();
  }
  function seekWall(wall) {
    const t = cur();
    if (!t.ready()) return;
    const m = Math.max(t.start() + 0.05, Math.min(t.end() - 0.1, t.toMedia(wall)));
    t.replay.currentTime = m;
  }
  function setVarUi() {
    root.classList.toggle('is-var', v.active);
    $('[data-monitor-box]').classList.toggle('is-var', v.active);
    $('[data-var-label]').textContent = v.active ? 'Vidéo-arbitrage en cours' : 'Vidéo-arbitrage';
    $('[data-play]').textContent = v.playing ? '❚❚' : '▶︎';
    $$('[data-speed]').forEach((b) => b.classList.toggle('on', parseFloat(b.dataset.speed) === v.rate));
    $$('[data-angle]').forEach((b) => b.classList.toggle('on', +b.dataset.angle === v.angle));
    $$('[data-program-cam]').forEach((b) => b.classList.toggle('on', +b.dataset.programCam === programCam));
    $('[data-zoom-label]').textContent = Math.round(v.zoom * 100) + ' %';
    const zt = $('[data-zoom-tag]'); zt.hidden = v.zoom === 1; zt.textContent = 'Zoom ' + Math.round(v.zoom * 100) + ' %';
    const pill = $('[data-onair-pill]');
    pill.classList.toggle('var', v.active && v.onAir);
    pill.classList.toggle('live', !(v.active && v.onAir) && !!(state && state.video && state.video.stream));
    $('[data-onair-text]').textContent = v.active && v.onAir ? 'Ralenti à l\'antenne' : (state && state.video && state.video.stream ? 'En direct sur YouTube' : 'Hors antenne');
  }
  function sendVar() { api(cfg.varUrl, { active: v.active, on_air: v.onAir }).catch((e) => toast(e.message, 'error')); }

  function enterVar(atWall) {
    if (!cur().ready()) {
      const other = tracks[v.angle === 1 ? 2 : 1];
      if (other.ready()) v.angle = other.slot; else { toast("Aucune image enregistrée : branchez une caméra d'abord.", 'error'); return; }
    }
    const was = v.active;
    v.active = true; v.playing = false;
    Object.values(tracks).forEach((t) => t.replay.pause());
    seekWall(atWall != null ? atWall : wallNow() - 4000);
    cur().replay.playbackRate = v.rate;
    setVarUi();
    if (!was) sendVar();
  }
  function exitVar() {
    v.active = false; v.playing = false; v.zoom = 1; v.cx = v.cy = 0.5;
    Object.values(tracks).forEach((t) => t.replay.pause());
    setVarUi(); sendVar();
  }
  function play(on) {
    if (!v.active) return;
    const el = cur().replay;
    v.playing = on;
    el.playbackRate = v.rate;
    if (on) el.play().catch(() => { v.playing = false; setVarUi(); }); else el.pause();
    setVarUi();
  }
  function seekBy(sec) { if (!v.active) enterVar(); else seekWall(playheadWall() + sec * 1000); }
  function frame(dir) {
    if (!v.active) { enterVar(); return; }
    play(false);
    const el = cur().replay;
    el.currentTime = Math.max(cur().start(), Math.min(cur().end() - 0.05, el.currentTime + dir / FPS));
  }
  function setAngle(n) {
    if (!tracks[n].ready() && !tracks[n].stream) { toast('Caméra ' + n + ' sans image.', 'error'); return; }
    if (v.active) {
      const wall = playheadWall();
      const playing = v.playing;
      play(false);
      v.angle = n;
      seekWall(wall);
      if (playing) play(true);
    } else v.angle = n;
    setVarUi();
  }
  function setZoom(z, cx, cy) {
    v.zoom = Math.max(1, Math.min(6, z));
    if (cx != null) { v.cx = cx; v.cy = cy; }
    if (v.zoom === 1) { v.cx = v.cy = 0.5; }
    setVarUi();
  }

  // ------------------------------------------------------------------ Dessin
  const monitor = $('[data-monitor]'), mctx = monitor.getContext('2d');
  const program = $('[data-program]'), pctx = program.getContext('2d');
  const logo = new Image(); logo.src = (document.querySelector('link[rel=icon]') || {}).href || '';
  const logos = {};
  const logoOf = (url) => { if (!url) return null; if (!logos[url]) { const i = new Image(); i.src = url; logos[url] = i; } return logos[url].complete && logos[url].naturalWidth ? logos[url] : null; };

  /** Image de la source, ajustée au 16:9 (bandes si besoin), avec zoom et déplacement. */
  function drawSource(ctx, el, z) {
    const vw = el && el.videoWidth, vh = el && el.videoHeight;
    if (!vw || !vh || el.readyState < 2) return false;
    const zoom = z ? z.zoom : 1;
    const s = Math.min(W / vw, H / vh) * zoom;
    const dw = vw * s, dh = vh * s;
    let dx = W / 2 - (z ? z.cx : 0.5) * dw, dy = H / 2 - (z ? z.cy : 0.5) * dh;
    if (dw > W) dx = Math.min(0, Math.max(W - dw, dx)); else dx = (W - dw) / 2;
    if (dh > H) dy = Math.min(0, Math.max(H - dh, dy)); else dy = (H - dh) / 2;
    ctx.drawImage(el, dx, dy, dw, dh);
    return true;
  }
  function rr(ctx, x, y, w, h, r) { ctx.beginPath(); ctx.roundRect ? ctx.roundRect(x, y, w, h, r) : ctx.rect(x, y, w, h); }
  function slate(ctx, text) {
    const g = ctx.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#1d1d21'); g.addColorStop(1, '#3a0d13');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    if (logo.complete && logo.naturalWidth) { const lh = 150, lw = lh * logo.naturalWidth / logo.naturalHeight; ctx.drawImage(logo, (W - lw) / 2, 200, lw, lh); }
    ctx.fillStyle = '#fff'; ctx.textAlign = 'center';
    ctx.font = 'italic 800 54px Poppins, sans-serif'; ctx.fillText('TAPIS ' + cfg.tatamiNumber, W / 2, 440);
    ctx.font = '600 26px Inter, sans-serif'; ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.fillText(text, W / 2, 490);
    ctx.textAlign = 'left';
  }
  const fmt = (ms) => { const t = Math.max(0, Math.ceil(ms / 1000)); return Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0'); };
  function clockText(b) {
    const c = b.clock;
    let e = c.elapsed_ms;
    if (c.running && c.started_at_ms) e += Math.max(0, serverNow() - c.started_at_ms);
    if (b.status === 'finished' || b.status === 'validated') return c.golden_score ? fmt(c.elapsed_ms) : fmt(c.duration_ms - (c.final_elapsed_ms || c.elapsed_ms));
    if (c.golden_score) return c.golden_score_mode === 'limited' && c.golden_score_ms ? fmt(c.golden_score_ms - e) : fmt(e);
    return fmt(c.duration_ms - e);
  }

  /** Incrustation du combat en haut à gauche : catégorie, deux combattants (scores, shidos), chronomètre. */
  function drawOverlay(ctx) {
    const b = state && state.current;
    const x = 28, y = 26, w = 620, head = 30, row = 50;
    if (!b) {
      ctx.fillStyle = 'rgba(16,16,19,.92)'; rr(ctx, x, y, 300, 46, 10); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.font = 'italic 800 22px Poppins, sans-serif'; ctx.fillText('TAPIS ' + cfg.tatamiNumber, x + 16, y + 31);
      return;
    }
    const d = state.display || {};
    const blue = d.fighter_color === 'blue';
    const useYuko = d.use_yuko !== false;
    const clockW = 128;
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 4;
    ctx.fillStyle = '#ffffff'; rr(ctx, x, y, w, head + row * 2, 12); ctx.fill();
    ctx.restore();
    // Bandeau catégorie
    ctx.save(); rr(ctx, x, y, w, head + row * 2, 12); ctx.clip();
    ctx.fillStyle = '#18181b'; ctx.fillRect(x, y, w, head);
    ctx.fillStyle = '#c21a2c'; ctx.fillRect(x, y, 6, head);
    ctx.font = '700 15px Inter, sans-serif'; ctx.fillStyle = '#fff';
    const cat = (b.category + (b.weight_label ? ' ' + b.weight_label + ' kg' : '') + (b.stage_label ? ' · ' + b.stage_label : '')).toUpperCase();
    ctx.fillText(fit(ctx, cat, w - clockW - 30), x + 16, y + 20);
    ctx.textAlign = 'right'; ctx.fillStyle = '#f5c400'; ctx.fillText('TAPIS ' + cfg.tatamiNumber, x + w - 14, y + 20); ctx.textAlign = 'left';
    // Combattants
    ['a', 'b'].forEach((side, i) => {
      const f = b[side];
      const ry = y + head + i * row;
      if (i === 1) { ctx.fillStyle = '#e4e4e7'; ctx.fillRect(x, ry, w - clockW, 1); }
      const color = side === 'a' ? (blue ? '#1d4ed8' : '#c21a2c') : '#f4f4f5';
      ctx.fillStyle = color; ctx.fillRect(x, ry, 12, row);
      if (side === 'b') { ctx.fillStyle = '#a1a1aa'; ctx.fillRect(x + 12, ry, 1.5, row); }
      const win = b.winner_side === side && (b.status === 'finished' || b.status === 'validated');
      const lg = logoOf(f.club_logo);
      let nx = x + 24;
      if (lg) { const s = 32; ctx.drawImage(lg, nx, ry + (row - s) / 2, s * lg.naturalWidth / Math.max(lg.naturalWidth, lg.naturalHeight), s * lg.naturalHeight / Math.max(lg.naturalWidth, lg.naturalHeight)); nx += 42; }
      ctx.fillStyle = '#18181b'; ctx.font = 'italic 800 22px Poppins, sans-serif';
      const scoreX = x + w - clockW - (useYuko ? 150 : 110);
      ctx.fillText(fit(ctx, (f.last_name || '').toUpperCase() + ' ' + (f.first_name ? f.first_name[0] + '.' : ''), scoreX - nx - 50), nx, ry + 33);
      if (win) { ctx.fillStyle = '#15803d'; ctx.font = '800 13px Inter, sans-serif'; ctx.fillText('✓', scoreX - 26, ry + 31); }
      // Shidos
      for (let k = 0; k < Math.min(3, f.shido); k++) { ctx.fillStyle = f.hansoku ? '#c21a2c' : '#f5c400'; rr(ctx, scoreX - 48 + k * 13, ry + 13, 10, 24, 2); ctx.fill(); }
      // Scores I W (Y)
      const digits = useYuko ? [f.ippon, f.wazaari, f.yuko] : [f.ippon, f.wazaari];
      ctx.font = 'italic 900 30px Poppins, sans-serif'; ctx.fillStyle = '#18181b'; ctx.textAlign = 'center';
      digits.forEach((n, k) => ctx.fillText(String(n), scoreX + 20 + k * 36, ry + 36));
      ctx.textAlign = 'left';
      // Osaekomi
      const o = b.osaekomi;
      if (o && o.side === side && b.status === 'in_progress') {
        const sec = Math.max(0, serverNow() - o.started_at_ms) / 1000;
        const th = (d.osaekomi && d.osaekomi.ippon) || 20;
        ctx.fillStyle = '#f5c400'; ctx.fillRect(x + 12, ry + row - 5, (w - clockW - 12) * Math.min(1, sec / th), 5);
      }
    });
    // Chronomètre
    const cx = x + w - clockW;
    ctx.fillStyle = b.clock.golden_score ? '#f5c400' : '#18181b';
    ctx.fillRect(cx, y + head, clockW, row * 2);
    ctx.textAlign = 'center';
    ctx.fillStyle = b.clock.golden_score ? '#18181b' : (b.clock.running ? '#ffffff' : '#f5c400');
    ctx.font = '800 40px ui-monospace, Menlo, monospace';
    ctx.fillText(clockText(b), cx + clockW / 2, y + head + row + 12);
    ctx.font = '800 12px Inter, sans-serif';
    ctx.fillText(b.clock.golden_score ? 'GOLDEN SCORE' : (b.status === 'in_progress' && !b.clock.running ? 'MATTE' : (b.status === 'scheduled' ? 'À VENIR' : '')), cx + clockW / 2, y + head + row * 2 - 12);
    ctx.textAlign = 'left';
    ctx.restore();
  }
  function fit(ctx, text, max) {
    if (ctx.measureText(text).width <= max) return text;
    while (text.length > 1 && ctx.measureText(text + '…').width > max) text = text.slice(0, -1);
    return text + '…';
  }
  /** Bandeau du ralenti à l'antenne. */
  function drawVarBanner(ctx) {
    const h = 64, y = H - h - 30;
    ctx.fillStyle = 'rgba(16,16,19,.94)'; ctx.fillRect(0, y, W, h);
    ctx.fillStyle = '#c21a2c'; ctx.fillRect(0, y, 10, h);
    ctx.fillStyle = '#f5c400'; ctx.fillRect(W - 10, y, 10, h);
    const blink = Math.floor(Date.now() / 500) % 2 === 0;
    if (blink) { ctx.fillStyle = '#c21a2c'; ctx.beginPath(); ctx.arc(46, y + h / 2, 10, 0, Math.PI * 2); ctx.fill(); }
    ctx.fillStyle = '#fff'; ctx.font = 'italic 800 32px Poppins, sans-serif';
    ctx.fillText('VIDÉO-ARBITRAGE EN COURS', 72, y + 44);
    ctx.textAlign = 'right'; ctx.font = '800 22px Inter, sans-serif'; ctx.fillStyle = '#f5c400';
    const parts = [];
    if (v.rate < 1) parts.push('RALENTI ×' + (v.rate === 0.5 ? '½' : v.rate === 0.25 ? '¼' : '⅛'));
    if (v.zoom > 1) parts.push('ZOOM ×' + v.zoom.toFixed(1).replace('.0', ''));
    parts.push('CAMÉRA ' + v.angle);
    ctx.fillText(parts.join('  ·  '), W - 34, y + 41);
    ctx.textAlign = 'left';
  }

  function renderProgram() {
    pctx.fillStyle = '#000'; pctx.fillRect(0, 0, W, H);
    const onAirVar = v.active && v.onAir;
    const ok = onAirVar ? drawSource(pctx, cur().replay, v) : drawSource(pctx, tracks[programCam].live, null);
    if (!ok) slate(pctx, onAirVar ? 'Vidéo-arbitrage en cours' : 'La diffusion va commencer');
    drawOverlay(pctx);
    if (onAirVar) drawVarBanner(pctx);
  }
  function renderMonitor() {
    mctx.fillStyle = '#000'; mctx.fillRect(0, 0, W, H);
    const el = v.active ? cur().replay : tracks[v.angle].live;
    if (!drawSource(mctx, el, v.active ? v : null)) slate(mctx, tracks[v.angle].stream ? 'Enregistrement en cours…' : 'Caméra ' + v.angle + ' : choisissez une source');
    const tag = $('[data-monitor-tag]');
    tag.textContent = (v.active ? 'VIDÉO-ARBITRAGE · ' : 'DIRECT · ') + 'Caméra ' + v.angle;
    const tm = $('[data-monitor-time]');
    tm.hidden = !v.active;
    if (v.active && cur().ready()) {
      const back = (wallNow() - playheadWall()) / 1000;
      tm.textContent = '−' + Math.floor(back / 60) + ':' + (back % 60).toFixed(1).padStart(4, '0') + (v.rate < 1 ? '  ×' + v.rate : '');
      if (v.playing && cur().replay.currentTime >= cur().end() - 0.15) play(false);
    }
  }

  // Boucle d'image : minuterie dans un « worker » pour ne pas ralentir quand la fenêtre de régie est masquée
  const ticker = new Worker(cfg.tickerUrl);
  let lastTl = 0;
  ticker.onmessage = () => {
    renderProgram();
    renderMonitor();
    const t = Date.now();
    if (t - lastTl > 200) { lastTl = t; renderTimeline(); }
  };
  const programStream = program.captureStream(FPS);
  window.JCLVideo = { programStream: () => programStream, tatami: cfg.tatamiNumber };

  // ------------------------------------------------------------------ Ligne de temps (3 dernières minutes)
  const TL_S = 180;
  const tl = $('[data-timeline]');
  const short = { start: 'Hajime', end: 'Fin', 'score:ippon': 'I', 'score:wazaari': 'W', 'score:yuko': 'Y', 'score:shido': 'S', osaekomi: 'O', pause: 'M', toketa: 'T' };
  let markersKey = '';
  function tlX(wall) { const now = wallNow(); return ((wall - (now - TL_S * 1000)) / (TL_S * 1000)) * 100; }
  function renderTimeline() {
    const t = cur();
    const buf = $('[data-tl-buffer]');
    if (t.ready()) { const a = Math.max(0, tlX(t.toWall(t.start()))), b = Math.min(100, tlX(t.toWall(t.end()))); buf.style.left = a + '%'; buf.style.width = Math.max(0, b - a) + '%'; } else buf.style.width = '0';
    $('[data-tl-head]').style.left = Math.min(100, tlX(playheadWall())) + '%';
    const marks = (state && state.markers || []).map((m) => ({ ...m, wall: m.at_ms - offset })).filter((m) => tlX(m.wall) >= 0);
    const key = marks.map((m) => m.kind + m.at_ms).join();
    const box = $('[data-tl-markers]');
    if (key !== markersKey) {
      markersKey = key;
      box.innerHTML = marks.map((m) => {
        const cls = m.kind === 'score:shido' ? 'shido' : m.kind.startsWith('score') ? 'score' : m.kind;
        const who = m.side ? (m.side === 'a' ? ' · premier appelé' : ' · second appelé') : '';
        return `<button type="button" class="vr-tl-mark ${cls} side-${m.side || 'x'}" data-wall="${m.wall}" data-short="${short[m.kind] || ''}" title="${esc(m.label + who)}" aria-label="${esc(m.label + who)}"></button>`;
      }).join('');
    }
    $$('.vr-tl-mark', box).forEach((el) => { el.style.left = tlX(+el.dataset.wall) + '%'; });
    const scale = $('[data-tl-scale]');
    if (!scale.childElementCount) scale.innerHTML = [180, 150, 120, 90, 60, 30, 0].map((s) => `<span style="left:${((TL_S - s) / TL_S) * 100}%${s === 180 ? ';transform:none;padding-left:6px' : s === 0 ? ';transform:translateX(-100%);padding-right:6px' : ''}">${s ? '−' + (s >= 60 ? Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') : s + ' s') : 'direct'}</span>`).join('');
  }
  tl.addEventListener('click', (e) => {
    const mark = e.target.closest('.vr-tl-mark');
    const rect = tl.getBoundingClientRect();
    const wall = mark ? +mark.dataset.wall - 2000 : wallNow() - TL_S * 1000 + ((e.clientX - rect.left) / rect.width) * TL_S * 1000;
    if (!v.active) enterVar(wall); else { play(false); seekWall(wall); }
  });

  // ------------------------------------------------------------------ Zoom : molette, glisser, pincer
  monitor.addEventListener('wheel', (e) => {
    if (!v.active) return;
    e.preventDefault();
    const r = monitor.getBoundingClientRect();
    const px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
    // Point visé conservé sous le curseur
    const z0 = v.zoom, z1 = Math.max(1, Math.min(6, z0 * (e.deltaY < 0 ? 1.15 : 1 / 1.15)));
    const sx = v.cx + (px - 0.5) / z0, sy = v.cy + (py - 0.5) / z0;
    setZoom(z1, sx - (px - 0.5) / z1, sy - (py - 0.5) / z1);
  }, { passive: false });
  let drag = null;
  monitor.addEventListener('pointerdown', (e) => { if (v.active && v.zoom > 1) { drag = { x: e.clientX, y: e.clientY, cx: v.cx, cy: v.cy }; monitor.setPointerCapture(e.pointerId); } });
  monitor.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const r = monitor.getBoundingClientRect();
    v.cx = Math.max(0, Math.min(1, drag.cx - (e.clientX - drag.x) / r.width / v.zoom));
    v.cy = Math.max(0, Math.min(1, drag.cy - (e.clientY - drag.y) / r.height / v.zoom));
  });
  monitor.addEventListener('pointerup', () => { drag = null; });
  monitor.addEventListener('dblclick', () => { if (v.active) setZoom(v.zoom > 1 ? 1 : 2.5); });

  // ------------------------------------------------------------------ Commandes
  root.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.matches('[data-var-toggle]')) { v.active ? exitVar() : enterVar(); }
    else if (b.matches('[data-go-live]')) exitVar();
    else if (b.dataset.seek) seekBy(+b.dataset.seek);
    else if (b.dataset.frame) frame(+b.dataset.frame);
    else if (b.matches('[data-play]')) play(!v.playing);
    else if (b.dataset.speed) { v.rate = parseFloat(b.dataset.speed); cur().replay.playbackRate = v.rate; setVarUi(); }
    else if (b.dataset.angle) setAngle(+b.dataset.angle);
    else if (b.dataset.zoom) { const z = +b.dataset.zoom; if (!v.active && z) enterVar(); setZoom(z === 0 ? 1 : v.zoom * (z > 0 ? 1.25 : 0.8)); }
    else if (b.dataset.programCam) { programCam = +b.dataset.programCam; store.set('program', programCam); if (!v.active) v.angle = programCam; setVarUi(); }
    else if (b.matches('[data-open-output]')) openOutput();
    else if (b.matches('[data-allow-cams]')) allowCams();
    else if (b.matches('[data-yt-start]')) startYoutube();
    else if (b.matches('[data-yt-stop]')) stopYoutube();
    else if (b.dataset.ytOffset) shiftOffset(+b.dataset.ytOffset);
  });
  $('[data-onair]').addEventListener('change', (e) => { v.onAir = e.target.checked; setVarUi(); if (v.active) sendVar(); });
  $$('[data-cam-source]').forEach((sel) => sel.addEventListener('change', () => choose(+sel.closest('[data-cam]').dataset.cam, sel.value)));
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, select, textarea')) return;
    const k = e.key;
    if (k === 'v' || k === 'V') { v.active ? exitVar() : enterVar(); }
    else if (k === 'Escape') { if (v.active) exitVar(); }
    else if (k === ' ' || k === 'k' || k === 'K') { e.preventDefault(); if (!v.active) enterVar(); else play(!v.playing); }
    else if (k === 'ArrowLeft') { e.preventDefault(); e.shiftKey ? seekBy(-1) : frame(-1); }
    else if (k === 'ArrowRight') { e.preventDefault(); e.shiftKey ? seekBy(1) : frame(1); }
    else if (k === 'j' || k === 'J') seekBy(-5);
    else if (k === 'l' || k === 'L') seekBy(5);
    else if (k === '+' || k === '=') { if (!v.active) enterVar(); setZoom(v.zoom * 1.25); }
    else if (k === '-') setZoom(v.zoom * 0.8);
    else if (k === '0') setZoom(1);
    else if (k === '1' || k === '2') setAngle(+k);
    else return;
  });

  let outputWin = null;
  function openOutput() {
    outputWin = window.open(cfg.outputUrl, 'jcl-programme-' + cfg.tatamiId, 'width=1280,height=720,menubar=no,toolbar=no,location=no,status=no');
    if (!outputWin) toast('Autorisez les fenêtres surgissantes pour ce site, puis réessayez.', 'error');
  }
  async function allowCams() {
    try { const s = await navigator.mediaDevices.getUserMedia({ video: true }); s.getTracks().forEach((t) => t.stop()); } catch (e) { toast('Accès aux caméras refusé : ' + e.message, 'error'); }
    await listDevices();
  }

  // ------------------------------------------------------------------ Direct YouTube
  async function startYoutube() {
    const link = $('[data-yt-link]').value.trim();
    if (!link) { toast('Collez le lien du direct YouTube.', 'error'); return; }
    try { await api(cfg.streamUrl, { link }); toast('Direct enregistré : les replays des combats seront disponibles dans l\'application.', 'success'); poll(); } catch (e) { toast(e.message, 'error'); }
  }
  async function stopYoutube() { try { await api(cfg.streamStopUrl, {}); poll(); } catch (e) { toast(e.message, 'error'); } }
  async function shiftOffset(d) {
    const s = state && state.video && state.video.stream;
    if (!s) return;
    try { await api(cfg.offsetUrl, { seconds: s.offset_s + d }); poll(); } catch (e) { toast(e.message, 'error'); }
  }
  function renderYoutube() {
    const s = state.video && state.video.stream;
    $('[data-yt-off]').hidden = !!s; $('[data-yt-on]').hidden = !s;
    if (s) {
      $('[data-yt-since]').textContent = new Date(s.started_at_ms - offset).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
      const a = $('[data-yt-open]'); a.href = 'https://youtu.be/' + s.youtube_id; a.textContent = 'youtu.be/' + s.youtube_id;
      $('[data-yt-offset-val]').textContent = (s.offset_s > 0 ? '+' : '') + s.offset_s + ' s';
    }
  }

  // ------------------------------------------------------------------ État serveur
  const net = $('[data-net]');
  async function poll() {
    const t0 = Date.now();
    try {
      const d = await JCL.api(cfg.stateUrl);
      offset = d.server_now_ms + (Date.now() - t0) / 2 - Date.now();
      state = d;
      const b = d.current;
      $('[data-bout-label]').textContent = b ? `${b.a.last_name} / ${b.b.last_name} · ${b.category}${b.weight_label ? ' ' + b.weight_label + ' kg' : ''} · ${b.status_label}` : cfg.competition;
      handleCameras(d.video.cameras);
      renderYoutube();
      setVarUi();
      net.className = 'vr-net ok'; $('[data-net-label]').textContent = 'Connecté';
    } catch (e) {
      net.className = 'vr-net off'; $('[data-net-label]').textContent = e.status === 403 ? 'Accès refusé' : 'Hors ligne';
    }
  }
  setInterval(poll, 1000);

  // ------------------------------------------------------------------ Démarrage : sources mémorisées
  $$('[data-phone-url]').forEach((el) => { el.textContent = cfg.phoneUrl.replace(/^https?:\/\//, ''); });
  (async () => {
    await poll();
    await listDevices();
    for (const slot of [1, 2]) {
      const saved = store.get('src' + slot, '');
      const sel = $(`[data-cam="${slot}"] [data-cam-source]`);
      if (saved === 'phone') {
        sel.value = 'phone'; tracks[slot].source = 'phone'; tracks[slot].status();
        $(`[data-cam="${slot}"] [data-cam-phone]`).hidden = false;
        $(`[data-cam="${slot}"] [data-cam-code]`).textContent = 'Code déjà donné';
        $(`[data-cam="${slot}"] [data-cam-device]`).innerHTML = '<button type="button" class="vr-btn vr-btn-ghost" data-new-code="' + slot + '">Nouveau code</button>';
      } else if (saved && [...sel.options].some((o) => o.value === saved)) { sel.value = saved; useDevice(slot, saved); }
    }
    v.angle = programCam;
    setVarUi();
  })();
  root.addEventListener('click', (e) => { const n = e.target.closest('[data-new-code]'); if (n) newPhoneCode(+n.dataset.newCode); });
  window.addEventListener('beforeunload', () => { if (v.active) navigator.sendBeacon && fetch(cfg.varUrl, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (document.querySelector('meta[name=csrf-token]') || {}).content || '' }, body: JSON.stringify({ active: false }) }); });
  if (!MIME) toast('Enregistrement vidéo impossible dans ce navigateur : utilisez Chrome ou Edge à jour.', 'error');
})();
