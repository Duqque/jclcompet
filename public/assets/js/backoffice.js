/* Espaces privés : menu latéral mobile, copie de liens. */
(function () {
  'use strict';
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-side-toggle]');
    if (t) {
      const side = document.getElementById(t.getAttribute('aria-controls'));
      const open = side.classList.toggle('open');
      t.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    const side = document.querySelector('.bo-side.open');
    if (side && !e.target.closest('.bo-side')) side.classList.remove('open');
    const copy = e.target.closest('[data-copy]');
    if (copy) {
      navigator.clipboard.writeText(copy.dataset.copy).then(() => JCL.toast('Lien copié.', 'success'), () => JCL.toast('Copie impossible : sélectionnez le lien.', 'error'));
    }
  });
})();
