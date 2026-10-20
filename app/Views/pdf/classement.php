<?php use App\Services\CategoryService; $groups = []; foreach ($rows as $r) { $groups[$r['pool_label']][] = $r; } ?>
<?php if (!$rows): ?><p>Aucune poule terminée.</p><?php endif; ?>
<?php foreach ($groups as $label => $list): $f = $list[0]; ?>
  <h2><?= e($label) ?> <span class="muted small"><?= e(CategoryService::label(['name' => $f['age_name'], 'name_m' => $f['name_m'], 'name_f' => $f['name_f']], $f['sex'])) ?> <?= e($f['weight_label'] ? $f['weight_label'] . ' kg' : '') ?></span></h2>
  <table><thead><tr><th style="width:8%">Place</th><th>Judoka</th><th>Club</th><th style="width:10%">Médaille</th><th style="width:9%">Points</th></tr></thead><tbody>
  <?php foreach ($list as $r): ?><tr><td class="num"><?= (int) $r['place'] ?></td><td><?= e($r['last_name'] . ' ' . $r['first_name']) ?></td><td><?= e($r['club_name']) ?></td><td><?= e(['gold' => 'Or', 'silver' => 'Argent', 'bronze' => 'Bronze'][$r['medal']] ?? '') ?></td><td class="num"><?= (int) $r['points'] ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endforeach; ?>
