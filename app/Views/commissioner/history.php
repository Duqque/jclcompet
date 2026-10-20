<?php use App\Services\BoutService; ?>
<div class="page-head"><div><p class="eyebrow"><?= e($c['name']) ?></p><h1>Tapis <?= (int) $t['number'] ?> — historique et poules</h1></div><a class="btn btn-red" href="<?= e(url('/commissaire/tapis/' . $t['id'])) ?>">Retour à la saisie</a></div>
<section class="card" style="margin-bottom:1.2rem"><h2 style="font-size:1.1rem">Combats terminés</h2>
<?php if (!$bouts): ?><p class="muted">Aucun combat terminé sur ce tapis.</p><?php else: ?>
<div class="table-wrap"><table class="table-compact"><thead><tr><th>Ordre</th><th>Catégorie</th><th>Combattant 1</th><th>Score</th><th>Combattant 2</th><th>Résultat</th><th></th></tr></thead><tbody>
<?php foreach ($bouts as $b): ?><tr><td class="num"><?= (int) $b['order_on_tatami'] ?></td><td class="small"><?= e($b['category']) ?> · <?= e($b['pool_label']) ?></td>
  <td<?= $b['winner_side'] === 'a' ? ' style="font-weight:700"' : '' ?>><?= e($b['a']['first_name'] . ' ' . $b['a']['last_name']) ?></td>
  <td class="num nowrap"><?= $b['a']['ippon'] . $b['a']['wazaari'] . $b['a']['yuko'] ?> – <?= $b['b']['ippon'] . $b['b']['wazaari'] . $b['b']['yuko'] ?></td>
  <td<?= $b['winner_side'] === 'b' ? ' style="font-weight:700"' : '' ?>><?= e($b['b']['first_name'] . ' ' . $b['b']['last_name']) ?></td>
  <td class="small"><?= e($b['win_method_label']) ?> · <?= e($b['status_label']) ?></td>
  <td><?php if ($b['status'] === 'validated'): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/commissaire/combats/' . $b['id'] . '/corriger')) ?>">Corriger</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<section style="margin-top:1.4rem">
  <div class="card-head"><h2 style="font-size:1.2rem;margin:0">Feuilles de poule du tapis</h2>
    <form method="get" class="toolbar" role="search" style="margin:0"><label class="search"><?= icon('search') ?><span class="sr-only">Retrouver une poule</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom d'un judoka ou n° de poule…"></label><?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="?">Effacer</a><?php endif; ?></form></div>
  <?php if (!$pools): ?><div class="empty"><strong><?= $q !== '' ? 'Aucune poule trouvée' : 'Aucune poule sur ce tapis' ?></strong><?= $q !== '' ? 'Vérifiez l\'orthographe du nom ou cherchez par numéro de poule.' : '' ?></div><?php else: ?>
  <div class="sheets"><?php $boutHref = fn (array $b) => $b['status'] === 'validated' ? url('/commissaire/combats/' . $b['id'] . '/corriger') : null;
    foreach ($pools as $p): include BASE_PATH . '/app/Views/partials/pool_sheet.php'; endforeach; ?></div>
  <p class="small muted">Cliquez sur une case d'un combat validé pour corriger son résultat.</p>
  <?php endif; ?>
</section>
