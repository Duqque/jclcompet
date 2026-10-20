/* Caisse : panier local, envoi idempotent (référence client), total recalculé côté serveur. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('pos-config').textContent);
  const cart = new Map();
  const $ = (s) => document.querySelector(s);
  const money = (c) => (c / 100).toFixed(2).replace('.', ',') + ' €';
  let ref = JCL.uid();

  const byCat = {};
  cfg.products.forEach((p) => { (byCat[p.category] = byCat[p.category] || []).push(p); });
  $('[data-products]').innerHTML = Object.entries(byCat).map(([cat, ps]) => '<h3>' + JCL.esc(cfg.categories[cat] || cat) + '</h3>' +
    ps.map((p) => '<button type="button" data-add="' + p.id + '"><span>' + JCL.esc(p.name) + (p.desc ? ' <small class="muted">' + JCL.esc(p.desc) + '</small>' : '') + '</span><span class="price">' + money(p.price) + '</span></button>').join('')).join('') ||
    '<div class="empty"><strong>Aucun produit actif</strong>Ajoutez des produits dans Administration › Buvette et boutique.</div>';

  function render() {
    const ul = $('[data-cart]');
    let total = 0;
    const items = [...cart.entries()].map(([id, qty]) => { const p = cfg.products.find((x) => x.id === id); total += p.price * qty; return { p, qty }; });
    ul.innerHTML = items.length ? items.map(({ p, qty }) => '<li><span>' + JCL.esc(p.name) + '</span><button class="icon-btn" type="button" data-dec="' + p.id + '" aria-label="Retirer un">−</button><strong>' + qty + '</strong><button class="icon-btn" type="button" data-add="' + p.id + '" aria-label="Ajouter un">+</button><span class="num" style="flex:none;min-width:70px;text-align:right">' + money(p.price * qty) + '</span></li>').join('') : '<li class="muted small">Touchez un produit pour l\'ajouter.</li>';
    $('[data-total]').textContent = money(total);
    $('[data-pay]').disabled = !items.length;
  }

  document.addEventListener('click', async (e) => {
    const add = e.target.closest('[data-add]'), dec = e.target.closest('[data-dec]');
    if (add) { const id = +add.dataset.add; cart.set(id, (cart.get(id) || 0) + 1); render(); return; }
    if (dec) { const id = +dec.dataset.dec; const n = (cart.get(id) || 0) - 1; if (n <= 0) cart.delete(id); else cart.set(id, n); render(); return; }
    if (e.target.closest('[data-clear]')) { cart.clear(); render(); return; }
    if (e.target.closest('[data-pay]')) {
      const btn = $('[data-pay]');
      btn.disabled = true;
      try {
        const res = await JCL.api(cfg.storeUrl, { method: 'POST', body: { items: [...cart.entries()].map(([product_id, quantity]) => ({ product_id, quantity })), payment: $('[data-payment]').value, client_ref: ref } });
        JCL.toast('Vente enregistrée : ' + $('[data-total]').textContent, 'success');
        $('[data-today]').textContent = 'Aujourd\'hui : ' + money(res.today.net) + ' net';
        cart.clear(); ref = JCL.uid(); render();
      } catch (err) { JCL.toast(err.message + (err.offline ? ' La vente sera renvoyée sans doublon : réessayez.' : ''), 'error', 6000); btn.disabled = false; }
    }
  });
  render();
})();
