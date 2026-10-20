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
<section class="card"><h2 style="font-size:1.1rem">Poules du tapis</h2>
  <div class="grid grid-3"><?php foreach ($pools as $p): ?><div class="pool"><header><strong><?= e($p['number'] . ' · ' . $p['label']) ?></strong><span class="badge"><?= (int) $p['bouts_done'] ?>/<?= (int) $p['bouts_total'] ?></span></header>
    <ol><?php foreach ($p['athletes'] as $a): ?><li><?= e($a['last_name'] . ' ' . $a['first_name']) ?> <span class="club">· <?= e($a['club_short'] ?: $a['club_name']) ?></span><?= $a['final_rank'] ? ' — <strong>' . (int) $a['final_rank'] . '<sup>e</sup></strong>' : ($a['wins'] ? ' — ' . (int) $a['wins'] . ' V' : '') ?></li><?php endforeach; ?></ol></div><?php endforeach; ?></div>
</section>
