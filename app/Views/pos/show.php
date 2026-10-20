<?php $isAdmin = \App\Core\Auth::hasRole('admin'); ?>
<header class="att-bar">
  <a class="icon-btn" href="<?= e(url($isAdmin ? '/admin/competitions/' . $c['id'] . '/ventes' : '/')) ?>" aria-label="Retour"><?= icon('arrow-left') ?></a>
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="42" height="28">
  <span class="att-comp"><?= e($c['name']) ?></span>
  <span class="att-sync pos-today" title="Total net encaissé aujourd'hui"><span class="dot"></span><span data-today>Aujourd'hui : <?= e(money((int) $today['net'])) ?></span></span>
  <?php if (!$isAdmin): ?><form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="icon-btn" type="submit" title="Déconnexion" aria-label="Déconnexion"><?= icon('logout') ?></button></form><?php endif; ?>
</header>
<main class="att-page pos-page" id="contenu">
  <div class="pos">
    <section class="pos-catalog" aria-label="Produits">
      <div class="att-title"><p class="eyebrow">Buvette &amp; boutique</p><h1>Caisse</h1></div>
      <div class="segmented pos-cats" role="tablist" data-cats></div>
      <div class="pos-products" data-products></div>
    </section>
    <aside class="pos-cart" aria-label="Vente en cours">
      <header><h2>Vente en cours</h2><span class="badge" data-count>0 article</span></header>
      <ul data-cart></ul>
      <div class="pos-sum"><span>Total</span><strong data-total>0,00 €</strong></div>
      <p class="pos-lbl">Paiement</p>
      <div class="pos-pay" role="radiogroup" aria-label="Moyen de paiement" data-payment>
        <button type="button" role="radio" aria-checked="true" data-pm="cash"><?= icon('bag') ?>Espèces</button>
        <button type="button" role="radio" aria-checked="false" data-pm="card"><?= icon('id') ?>Carte</button>
        <button type="button" role="radio" aria-checked="false" data-pm="check"><?= icon('clipboard') ?>Chèque</button>
        <button type="button" role="radio" aria-checked="false" data-pm="other"><?= icon('sliders') ?>Autre</button>
      </div>
      <button class="btn btn-lg pos-go" type="button" data-pay disabled><?= icon('check') ?><span>Encaisser</span></button>
      <button class="btn btn-ghost pos-clear" type="button" data-clear>Vider la vente</button>
      <p class="small muted" style="margin:.8rem 0 0">Registre des ventes : aucun paiement n'est traité par l'application.</p>
    </aside>
  </div>
</main>
<?= json_script('pos-config', $config) ?>
