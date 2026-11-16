/* Page « en direct » : se recharge dès que l'empreinte des résultats change (validation d'un combat), en gardant la position. */
(function () {
  'use strict';
  const el = document.querySelector('[data-live-stamp]');
  if (!el) return;
  const url = el.dataset.liveStamp;
  const KEY = 'jcl-live-scroll:' + location.pathname;
  let stamp = null;
  try { const y = sessionStorage.getItem(KEY); if (y) { window.scrollTo(0, +y); sessionStorage.removeItem(KEY); } } catch (e) { /* stockage indisponible */ }
  // Pas de rechargement pendant une saisie (champ actif, formulaire modifié) ni fenêtre ouverte.
  let lastInput = 0;
  document.addEventListener('input', (e) => { if (e.target.closest('form')) lastInput = Date.now(); });
  document.addEventListener('submit', () => { lastInput = 0; });
  async function check() {
    const a = document.activeElement;
    if (document.hidden || document.querySelector('dialog[open]') || Date.now() - lastInput < 20000 || (a && a.matches('input, textarea, select'))) return;
    try {
      const s = (await JCL.api(url)).stamp;
      if (stamp !== null && s !== stamp) {
        try { sessionStorage.setItem(KEY, String(window.scrollY)); } catch (e) { /* stockage indisponible */ }
        location.reload();
      }
      stamp = s;
    } catch (e) { /* hors ligne : nouvel essai au prochain passage */ }
  }
  check();
  setInterval(check, +(el.dataset.liveInterval || 4000));
})();
