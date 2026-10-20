<?php use App\Services\SalesService; $t = $summary['totals']; $base = '/admin/competitions/' . $c['id'];
$heading = 'Boutique'; $actions = '<a class="btn btn-red" href="' . e(url('/caisse/' . $c['id'])) . '">Ouvrir la caisse</a><a class="btn btn-ghost" href="' . e(url($base . '/ventes/export.csv')) . '">Exporter (CSV)</a>'; include __DIR__ . '/_head.php'; $u = url($base . '/ventes'); ?>
<p class="small muted">Registre des ventes, sans encaissement bancaire. Le chiffre d'affaires n'est pas un bénéfice : les coûts d'achat ne sont pas connus de l'application.</p>
<form method="get" class="btn-group" style="margin-bottom:1rem"><label for="jour">Journée</label><select id="jour" name="jour" data-autosubmit style="width:auto"><option value="">Toute la compétition</option><?php foreach ($summary['by_day'] as $d): ?><option value="<?= e($d['day']) ?>"<?= selected($day, $d['day']) ?>><?= e(fr_date($d['day'], true)) ?></option><?php endforeach; ?></select></form>
<div class="stats">
  <div class="stat accent"><strong><?= e(money((int) $t['gross'])) ?></strong><span>chiffre d'affaires brut</span></div>
  <div class="stat"><strong><?= e(money((int) $t['refunds'])) ?></strong><span>remboursements</span></div>
  <div class="stat"><strong><?= e(money((int) $t['net'])) ?></strong><span>recettes nettes</span></div>
  <div class="stat"><strong><?= (int) $t['sales_count'] ?></strong><span>ventes</span></div>
  <?php foreach ($summary['by_payment'] as $p): ?><div class="stat"><strong><?= e(money((int) $p['net'])) ?></strong><span><?= e(SalesService::PAYMENTS[$p['payment_method']] ?? $p['payment_method']) ?> (net)</span></div><?php endforeach; ?>
</div>
<div class="grid grid-2">
  <section class="card"><h2 style="font-size:1.1rem">Ventes par produit</h2>
    <?php if (!$summary['by_product']): ?><p class="muted">Aucune vente.</p><?php else: ?><table class="table-compact"><thead><tr><th>Produit</th><th>Rubrique</th><th>Qté nette</th><th>Brut</th><th>Net</th></tr></thead><tbody>
    <?php foreach ($summary['by_product'] as $p): ?><tr><td><?= e($p['product_name']) ?></td><td class="small"><?= e(SalesService::CATEGORIES[$p['category']] ?? $p['category']) ?></td><td class="num"><?= (int) $p['qty'] ?></td><td class="num"><?= e(money((int) $p['gross'])) ?></td><td class="num"><?= e(money((int) $p['net'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card"><h2 style="font-size:1.1rem">Dernières ventes</h2>
    <?php if (!$recent): ?><p class="muted">Aucune vente enregistrée.</p><?php else: ?><div class="table-wrap" style="max-height:420px;overflow:auto"><table class="table-compact"><tbody>
    <?php foreach ($recent as $s): ?><tr><td class="nowrap small"><?= e(substr($s['sold_at'], 11, 5)) ?></td><td class="small"><?= e($s['items']) ?><?= $s['kind'] === 'refund' ? ' <span class="badge badge-red">remboursement</span>' : '' ?><?= $s['note'] ? '<br><span class="muted">' . e($s['note']) . '</span>' : '' ?></td><td class="num"><?= e(money((int) $s['total_cents'])) ?></td><td class="small"><?= e(SalesService::PAYMENTS[$s['payment_method']]) ?></td>
      <td><?php if ($s['kind'] === 'sale' && !$s['refunded']): ?><form method="post" action="<?= e($u) ?>" data-confirm="Rembourser cette vente de <?= e(money((int) $s['total_cents'])) ?> ?" data-reason><?= csrf_field() ?><input type="hidden" name="op" value="refund"><input type="hidden" name="sale_id" value="<?= (int) $s['id'] ?>"><button class="btn-link small" type="submit">Rembourser</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
</div>
<section class="card" style="margin-top:1.2rem"><h2 style="font-size:1.1rem">Produits et tarifs</h2>
  <p class="small muted">Ces tarifs sont aussi affichés sur la page publique de la compétition (rubrique Buvette).</p>
  <div class="table-wrap"><table class="table-compact"><thead><tr><th>Rubrique</th><th>Produit</th><th>Précision</th><th>Prix (€)</th><th>Ordre</th><th>Actif</th><th></th></tr></thead><tbody>
  <?php foreach (array_merge($products, [null]) as $p): ?>
    <tr><td colspan="7" style="padding:0"><form method="post" action="<?= e($u) ?>" style="display:grid;grid-template-columns:130px 1.5fr 1fr 90px 70px 60px auto;gap:.4rem;padding:.35rem .5rem;align-items:center"><?= csrf_field() ?><input type="hidden" name="op" value="product_save"><?php if ($p): ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><?php endif; ?>
      <select name="category" aria-label="Rubrique" style="min-height:34px"><?php foreach (SalesService::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"<?= selected($p['category'] ?? 'restauration', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <input name="name" aria-label="Produit" value="<?= e($p['name'] ?? '') ?>" placeholder="Nouveau produit" required style="min-height:34px">
      <input name="description" aria-label="Précision" value="<?= e($p['description'] ?? '') ?>" placeholder="33 cl" style="min-height:34px">
      <input name="price" aria-label="Prix" type="number" step="0.01" min="0" value="<?= $p ? e(number_format($p['price_cents'] / 100, 2, '.', '')) : '' ?>" required style="min-height:34px">
      <input name="sort_order" aria-label="Ordre" type="number" value="<?= (int) ($p['sort_order'] ?? 0) ?>" style="min-height:34px">
      <input name="is_active" aria-label="Actif" type="checkbox" value="1"<?= checked($p['is_active'] ?? 1) ?>>
      <button class="btn btn-sm <?= $p ? 'btn-ghost' : 'btn-red' ?>" type="submit"><?= $p ? 'Enregistrer' : 'Ajouter' ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
