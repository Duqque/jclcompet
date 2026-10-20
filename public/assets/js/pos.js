/* Caisse : panier local, envoi idempotent (référence client), total recalculé côté serveur. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('pos-config').textContent);
  const cart = new Map();
  const $ = (s) => document.querySelector(s);
  const esc = JCL.esc;
  const money = (c) => (c / 100).toFixed(2).replace('.', ',') + ' €';
  const svg = (d) => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  const CAT_ICON = {
    food: svg('<path d="M7 3v7a2 2 0 0 0 2 2v9"/><path d="M11 3v7a2 2 0 0 1-2 2"/><path d="M9 3v6"/><path d="M17 21V3c-2 1.5-3 4-3 7h3"/>'),
    drink: svg('<path d="M6 4h12l-1.5 15a2 2 0 0 1-2 1.8h-5a2 2 0 0 1-2-1.8z"/><path d="M6.5 9h11"/>'),
    shop: svg('<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>'),
  };
  const catIcon = (cat) => CAT_ICON[/boiss|drink/i.test(cat) ? 'drink' : /bout|shop|merch/i.test(cat) ? 'shop' : 'food'];
  let ref = JCL.uid();
  let payment = 'cash';
  let filter = '';

  const cats = [...new Set(cfg.products.map((p) => p.category))];
  $('[data-cats]').innerHTML = '<button type="button" aria-pressed="true" data-cat="">Tout</button>' +
    cats.map((c) => '<button type="button" aria-pressed="false" data-cat="' + esc(c) + '">' + esc(cfg.categories[c] || c) + '</button>').join('');

  function renderProducts() {
    const list = cfg.products.filter((p) => !filter || p.category === filter);
    $('[data-products]').innerHTML = list.length ? list.map((p) => {
      const qty = cart.get(p.id) || 0;
      return '<button type="button" class="pos-item cat-' + esc(p.category) + (qty ? ' in-cart' : '') + '" data-add="' + p.id + '">' +
        '<span class="pi-ic">' + catIcon(p.category) + '</span>' + (qty ? '<span class="pi-qty">' + qty + '</span>' : '') +
        '<span class="pi-name">' + esc(p.name) + (p.desc ? '<small>' + esc(p.desc) + '</small>' : '') + '</span>' +
        '<span class="pi-price">' + money(p.price) + '</span></button>';
    }).join('') : '<div class="empty"><strong>Aucun produit actif</strong>Ajoutez des produits dans Administration › Boutique.</div>';
  }

  function render() {
    let total = 0;
    let count = 0;
    const items = [...cart.entries()].map(([id, qty]) => { const p = cfg.products.find((x) => x.id === id); total += p.price * qty; count += qty; return { p, qty }; });
    $('[data-cart]').innerHTML = items.length ? items.map(({ p, qty }) => '<li><span class="ci-name">' + esc(p.name) + '<small>' + money(p.price) + ' l\'unité</small></span>' +
      '<span class="ci-step"><button type="button" data-dec="' + p.id + '" aria-label="Retirer un ' + esc(p.name) + '">−</button><strong>' + qty + '</strong><button type="button" data-add="' + p.id + '" aria-label="Ajouter un ' + esc(p.name) + '">+</button></span>' +
      '<span class="ci-total">' + money(p.price * qty) + '</span></li>').join('') : '<li class="ci-empty">Touchez un produit pour l\'ajouter à la vente.</li>';
    $('[data-total]').textContent = money(total);
    $('[data-count]').textContent = count + ' article' + (count > 1 ? 's' : '');
    $('[data-pay]').disabled = !items.length;
    $('[data-pay] span').textContent = items.length ? 'Encaisser ' + money(total) : 'Encaisser';
    renderProducts();
  }

  document.addEventListener('click', async (e) => {
    const add = e.target.closest('[data-add]'), dec = e.target.closest('[data-dec]');
    if (add) { const id = +add.dataset.add; cart.set(id, (cart.get(id) || 0) + 1); render(); return; }
    if (dec) { const id = +dec.dataset.dec; const n = (cart.get(id) || 0) - 1; if (n <= 0) cart.delete(id); else cart.set(id, n); render(); return; }
    const cat = e.target.closest('[data-cat]');
    if (cat) { filter = cat.dataset.cat; document.querySelectorAll('[data-cat]').forEach((b) => b.setAttribute('aria-pressed', String(b === cat))); renderProducts(); return; }
    const pm = e.target.closest('[data-pm]');
    if (pm) { payment = pm.dataset.pm; document.querySelectorAll('[data-pm]').forEach((b) => b.setAttribute('aria-checked', String(b === pm))); return; }
    if (e.target.closest('[data-clear]')) { cart.clear(); render(); return; }
    if (e.target.closest('[data-pay]')) {
      const btn = $('[data-pay]');
      btn.disabled = true;
      try {
        const res = await JCL.api(cfg.storeUrl, { method: 'POST', body: { items: [...cart.entries()].map(([product_id, quantity]) => ({ product_id, quantity })), payment, client_ref: ref } });
        JCL.toast('Vente enregistrée : ' + $('[data-total]').textContent, 'success');
        $('[data-today]').textContent = 'Aujourd\'hui : ' + money(res.today.net);
        cart.clear(); ref = JCL.uid(); render();
      } catch (err) { JCL.toast(err.message + (err.offline ? ' La vente sera renvoyée sans doublon : réessayez.' : ''), 'error', 6000); btn.disabled = false; }
    }
  });
  render();
})();
