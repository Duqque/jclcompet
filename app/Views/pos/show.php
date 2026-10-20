<header class="att-head"><div class="row"><a class="btn btn-sm btn-outline-white" href="<?= e(url(\App\Core\Auth::hasRole('admin') ? '/admin/competitions/' . $c['id'] . '/ventes' : '/')) ?>" aria-label="Retour">←</a><h1>Buvette · <?= e($c['name']) ?></h1>
  <span class="small" data-today>Aujourd'hui : <?= e(money((int) $today['net'])) ?> net</span>
  <?php if (!\App\Core\Auth::hasRole('admin')): ?><form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-white" type="submit">Déconnexion</button></form><?php endif; ?></div></header>
<main style="padding:16px">
  <div class="pos">
    <div class="pos-products" data-products></div>
    <aside class="pos-cart" aria-label="Vente en cours">
      <h2 style="font-size:1.1rem">Vente en cours</h2>
      <ul data-cart><li class="muted small">Touchez un produit pour l'ajouter.</li></ul>
      <div class="pos-total" data-total>0,00 €</div>
      <div class="field"><label for="pay">Paiement</label><select id="pay" data-payment><option value="cash">Espèces</option><option value="card">Carte bancaire</option><option value="check">Chèque</option><option value="other">Autre</option></select></div>
      <div class="btn-group" style="margin-top:.8rem"><button class="btn btn-green btn-lg btn-block btn-display" type="button" data-pay>Encaisser</button><button class="btn btn-ghost btn-block" type="button" data-clear>Vider</button></div>
      <p class="small muted" style="margin-top:.8rem">Registre des ventes : aucun paiement n'est traité par l'application.</p>
    </aside>
  </div>
</main>
<?= json_script('pos-config', $config) ?>
