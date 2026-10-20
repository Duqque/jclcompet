/* Connexion : choix du profil (cartes) et affichage du mot de passe. */
(function () {
  'use strict';
  const input = document.querySelector('[data-profile-input]');
  const label = document.querySelector('[data-profile-label] strong');
  const cards = document.querySelectorAll('[data-profile]');
  cards.forEach((c) => c.addEventListener('click', () => {
    cards.forEach((x) => x.setAttribute('aria-checked', x === c ? 'true' : 'false'));
    if (input) input.value = c.dataset.profile;
    if (label) label.textContent = c.dataset.label;
    try { localStorage.setItem('jcl-profile', c.dataset.profile); } catch (e) { /* stockage indisponible */ }
    const email = document.getElementById('email');
    if (email) email.focus();
  }));
  try {
    const saved = localStorage.getItem('jcl-profile');
    const card = saved && !location.search.includes('profil=') && document.querySelector('[data-profile="' + saved + '"]');
    if (card) card.click();
  } catch (e) { /* stockage indisponible */ }
  document.querySelectorAll('[data-pw-toggle]').forEach((b) => b.addEventListener('click', () => {
    const f = b.parentElement.querySelector('input');
    const show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    b.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
  }));
})();
