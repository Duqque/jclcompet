<?php use App\Services\BoutService; use App\Services\CategoryService; ?>
<div class="page-head"><div><p class="eyebrow"><?= e($c['name']) ?></p><h1>Résultats du club</h1></div><a class="btn btn-ghost" href="<?= e(url('/classement/' . $c['slug'])) ?>" target="_blank">Classement général ↗</a></div>
<div class="stats">
  <div class="stat accent"><strong><?= $rank ? (int) $rank['rank_points'] : '—' ?><small style="font-size:.5em">/<?= (int) $clubCount ?></small></strong><span>rang aux points</span></div>
  <div class="stat"><strong><?= (int) ($rank['points'] ?? 0) ?></strong><span>points</span></div>
  <div class="stat"><strong><?= $rank ? (int) $rank['rank_medals'] : '—' ?></strong><span>rang aux médailles</span></div>
  <div class="stat"><strong><?= (int) ($rank['gold'] ?? 0) ?> · <?= (int) ($rank['silver'] ?? 0) ?> · <?= (int) ($rank['bronze'] ?? 0) ?></strong><span>or · argent · bronze</span></div>
</div>
<section class="card" style="margin-bottom:1.2rem"><h2 style="font-size:1.1rem">Places de mes judokas</h2>
<?php if (!$individual): ?><p class="muted">Aucune poule terminée pour l'instant.</p><?php else: ?><table class="table-compact"><thead><tr><th>Judoka</th><th>Catégorie</th><th>Poule</th><th>Place</th><th>Points</th></tr></thead><tbody>
<?php foreach ($individual as $i): ?><tr><td><strong><?= e($i['first_name'] . ' ' . $i['last_name']) ?></strong></td><td><?= e(CategoryService::label(['name' => $i['age_name'], 'name_m' => $i['name_m'], 'name_f' => $i['name_f']], $i['sex'])) ?> <?= e($i['weight_label'] ? $i['weight_label'] . ' kg' : '') ?></td><td><?= e($i['pool_label']) ?></td><td><span class="place-pill<?= $i['medal'] ? ' medal-' . e($i['medal']) : '' ?>"><?= (int) $i['place'] ?></span></td><td class="num"><?= (int) $i['points'] ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?></section>
<section class="card"><h2 style="font-size:1.1rem">Combats de mes judokas</h2>
<?php if (!$bouts): ?><p class="muted">Aucun combat programmé.</p><?php else: ?><div class="table-wrap"><table class="table-compact"><thead><tr><th>Tapis</th><th>Ordre</th><th>Poule</th><th>Combat</th><th>Score</th><th>État</th></tr></thead><tbody>
<?php foreach ($bouts as $b): ?><tr><td class="num"><?= e($b['tatami_number']) ?></td><td class="num"><?= (int) $b['order_on_tatami'] ?></td><td class="small"><?= e($b['pool_label']) ?></td>
  <td><span<?= $b['winner_side'] === 'a' ? ' style="font-weight:700"' : '' ?>><?= e($b['a_name']) ?></span> / <span<?= $b['winner_side'] === 'b' ? ' style="font-weight:700"' : '' ?>><?= e($b['b_name']) ?></span></td>
  <td class="num nowrap"><?= $b['status'] === 'scheduled' ? '' : (int) $b['a_ippon'] . (int) $b['a_wazaari'] . (int) $b['a_yuko'] . ' – ' . (int) $b['b_ippon'] . (int) $b['b_wazaari'] . (int) $b['b_yuko'] ?></td>
  <td class="small"><?= e(BoutService::STATUS[$b['status']]) ?><?= $b['win_method'] && $b['status'] === 'validated' ? ' · ' . e(BoutService::METHODS[$b['win_method']]) : '' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
