/* Sortie programme : affiche l'image composée par la régie (fenêtre d'origine) ; OBS capture cette fenêtre. */
(function () {
  'use strict';
  const video = document.querySelector('[data-out]');
  const msg = document.querySelector('[data-msg]');
  let current = null;
  function attach() {
    let s = null;
    try { s = window.opener && !window.opener.closed && window.opener.JCLVideo && window.opener.JCLVideo.programStream(); } catch (e) { s = null; }
    if (s && s !== current) { current = s; video.srcObject = s; video.play().catch(() => {}); }
    msg.hidden = !!s;
    if (!s) { current = null; video.srcObject = null; }
  }
  attach();
  setInterval(attach, 1000);
  document.addEventListener('dblclick', () => { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {}); });
})();
