/* Téléphone / iPad caméra d'un tapis : capture (caméra arrière, 720p) et envoi direct à la régie en WebRTC.
   Signalisation par le serveur (offre / réponse complètes, sans compte : code caméra dans l'adresse). Reconnexion automatique. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('cam-config').textContent);
  const $ = (s) => document.querySelector(s);
  const video = $('[data-video]'), status = $('[data-status]'), dot = $('[data-dot]');
  let stream = null, pc = null, session = null, facing = 'environment', timer = null, lost = null, attempt = 0;

  const say = (text, mode) => { status.textContent = text; dot.className = 'cam-dot' + (mode ? ' ' + mode : ''); };
  const rand = () => Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 6);
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body), credentials: 'omit' })
    .then(async (r) => { if (!r.ok) { const d = await r.json().catch(() => ({})); throw new Error(d.error || ('Erreur ' + r.status)); } return r.json(); });

  async function camera() {
    if (stream) stream.getTracks().forEach((t) => t.stop());
    stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } } });
    video.srcObject = stream;
    video.play().catch(() => {});
  }

  const iceDone = (p, ms) => new Promise((res) => {
    if (p.iceGatheringState === 'complete') return res();
    const t = setTimeout(res, ms);
    p.addEventListener('icegatheringstatechange', () => { if (p.iceGatheringState === 'complete') { clearTimeout(t); res(); } });
  });

  async function connect() {
    clearTimeout(timer); clearTimeout(lost);
    if (pc) { try { pc.close(); } catch (e) { /* rien */ } }
    attempt++;
    session = rand();
    const mine = session;
    pc = new RTCPeerConnection({ iceServers: cfg.ice || [] });
    stream.getTracks().forEach((t) => pc.addTrack(t, stream));
    pc.onconnectionstatechange = async () => {
      if (mine !== session) return;
      const st = pc.connectionState;
      if (st === 'connected') {
        say('En direct vers la régie du tapis ' + cfg.tatami, 'on');
        attempt = 0;
        try { const s = pc.getSenders()[0]; const p = s.getParameters(); p.encodings = p.encodings && p.encodings.length ? p.encodings : [{}]; p.encodings[0].maxBitrate = 3000000; await s.setParameters(p); } catch (e) { /* facultatif */ }
      } else if (st === 'disconnected') {
        say('Connexion perdue, nouvel essai…', 'wait');
        lost = setTimeout(() => { if (mine === session) connect(); }, 4000);
      } else if (st === 'failed') { say('Connexion impossible, nouvel essai…', 'wait'); setTimeout(connect, 1500); }
    };
    try {
      await pc.setLocalDescription(await pc.createOffer());
      await iceDone(pc, 3000);
      await post(cfg.offerUrl, { session: mine, sdp: pc.localDescription.sdp, device: (navigator.userAgentData && navigator.userAgentData.platform) || (/iPad/.test(navigator.userAgent) ? 'iPad' : /iPhone/.test(navigator.userAgent) ? 'iPhone' : /Android/.test(navigator.userAgent) ? 'Android' : 'Navigateur') });
      say('En attente de la régie… (ouvrez la régie du tapis ' + cfg.tatami + ')', 'wait');
      waitAnswer(mine, Date.now());
    } catch (e) { say(e.message + ' — nouvel essai…', 'wait'); timer = setTimeout(connect, Math.min(10000, 2000 * attempt)); }
  }

  async function waitAnswer(mine, since) {
    if (mine !== session) return;
    try {
      const r = await fetch(cfg.answerUrl + '?session=' + mine, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'omit' }).then((x) => x.json());
      if (r.replaced) { say('Une autre caméra a pris cette place. Demandez un nouveau code à la régie.', 'off'); return; }
      if (r.answer && pc.signalingState === 'have-local-offer') { await pc.setRemoteDescription({ type: 'answer', sdp: r.answer }); say('Connexion à la régie…', 'wait'); }
    } catch (e) { /* réseau : on réessaie */ }
    if (mine !== session) return;
    // Pas de réponse (régie fermée ou rechargée) : nouvelle offre après 25 s
    if (pc.connectionState !== 'connected' && Date.now() - since > 25000) { connect(); return; }
    timer = setTimeout(() => waitAnswer(mine, since), pc.connectionState === 'connected' ? 5000 : 1000);
  }

  async function start() {
    $('[data-start]').hidden = true;
    try { await camera(); } catch (e) { say('Caméra refusée : autorisez l\'accès à la caméra dans les réglages du navigateur.', 'off'); $('[data-start]').hidden = false; return; }
    wake();
    connect();
  }
  async function wake() { try { if ('wakeLock' in navigator) await navigator.wakeLock.request('screen'); } catch (e) { /* non disponible */ } }
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && stream) wake(); });
  $('[data-go]').addEventListener('click', start);
  $('[data-flip]').addEventListener('click', async () => {
    if (!stream) return;
    facing = facing === 'environment' ? 'user' : 'environment';
    await camera();
    const s = pc && pc.getSenders().find((x) => x.track && x.track.kind === 'video');
    if (s) s.replaceTrack(stream.getVideoTracks()[0]);
  });
  say('Prêt', '');
})();
