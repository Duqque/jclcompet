/* Page « en direct » : se recharge dès que l'empreinte des résultats change (validation d'un combat), en gardant la position. */
(function () {
  'use strict';
  const el = document.querySelector('[data-live-stamp]');
  if (!el) return;
  const url = el.dataset.liveStamp;
  const KEY = 'jcl-live-scroll:' + location.pathname;
  let stamp = null;
  try { const y = sessionStorage.getItem(KEY); if (y) { window.scrollTo(0, +y); sessionStorage.removeItem(KEY); } } catch (e) { /* stockage indisponible */ }
  async function check() {
    if (document.hidden || document.querySelector('dialog[open]')) return;
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
  setInterval(check, 6000);
})();
