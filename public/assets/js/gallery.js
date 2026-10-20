/* Galerie : visionneuse plein écran (flèches, Échap, balayage). */
(function () {
  'use strict';
  const links = [...document.querySelectorAll('[data-gallery] a')];
  if (!links.length) return;
  let i = 0, box = null, startX = null;
  function show(n) {
    i = (n + links.length) % links.length;
    const a = links[i];
    box.querySelector('img').src = a.href;
    box.querySelector('img').alt = a.dataset.caption || '';
    box.querySelector('p').textContent = (a.dataset.caption ? a.dataset.caption + ' · ' : '') + (i + 1) + ' / ' + links.length;
  }
  function open(n) {
    box = document.createElement('div');
    box.className = 'lightbox'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Photo');
    box.innerHTML = '<figure style="margin:0"><img alt=""><p></p></figure><button class="close" type="button" aria-label="Fermer">×</button><button class="prev" type="button" aria-label="Photo précédente">‹</button><button class="next" type="button" aria-label="Photo suivante">›</button>';
    document.body.appendChild(box);
    box.querySelector('.close').addEventListener('click', close);
    box.querySelector('.prev').addEventListener('click', () => show(i - 1));
    box.querySelector('.next').addEventListener('click', () => show(i + 1));
    box.addEventListener('click', (e) => { if (e.target === box) close(); });
    box.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', (e) => { if (startX === null) return; const d = e.changedTouches[0].clientX - startX; if (Math.abs(d) > 40) show(i + (d < 0 ? 1 : -1)); startX = null; });
    show(n);
    box.querySelector('.close').focus();
  }
  function close() { if (box) { box.remove(); box = null; links[i].focus(); } }
  links.forEach((a, n) => a.addEventListener('click', (e) => { e.preventDefault(); open(n); }));
  document.addEventListener('keydown', (e) => {
    if (!box) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') show(i + 1);
    if (e.key === 'ArrowLeft') show(i - 1);
  });
})();
