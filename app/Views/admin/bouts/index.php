<?php use App\Services\BoutService; $base = '/admin/competitions/' . $c['id']; $heading = 'Combats'; $sub = 'Archive de tous les combats de la compétition'; include dirname(__DIR__) . '/_head.php'; ?>
<form method="get" class="filters card" style="padding:.8rem 1rem">
  <div class="field"><label>Recherche</label><input type="search" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Nom d'un combattant"></div>
  <div class="field"><label>Tapis</label><select name="tatami_id" data-autosubmit><option value="">Tous</option><?php foreach ($tatamis as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($f['tatami_id'] ?? '', $t['id']) ?>>Tapis <?= (int) $t['number'] ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Catégorie</label><select name="age_category_id" data-autosubmit><option value="">Toutes</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($f['age_category_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Sexe</label><select name="sex" data-autosubmit><option value="">Tous</option><option value="F"<?= selected($f['sex'] ?? '', 'F') ?>>Filles</option><option value="M"<?= selected($f['sex'] ?? '', 'M') ?>>Garçons</option></select></div>
  <div class="field"><label>Poids</label><select name="weight_category_id" data-autosubmit><option value="">Tous</option><?php foreach ($weights as $w): ?><option value="<?= (int) $w['id'] ?>"<?= selected($f['weight_category_id'] ?? '', $w['id']) ?>><?= e($w['label']) ?> (<?= e($w['sex']) ?>)</option><?php endforeach; ?></select></div>
  <div class="field"><label>Poule</label><select name="pool_id" data-autosubmit><option value="">Toutes</option><?php foreach ($poolsList as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($f['pool_id'] ?? '', $p['id']) ?>><?= e($p['label']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>État</label><select name="status" data-autosubmit><option value="">Tous</option><?php foreach (BoutService::STATUS as $k => $l): ?><option value="<?= $k ?>"<?= selected($f['status'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ghost" type="submit">Filtrer</button>
</form>
<p class="small muted"><?= (int) $total ?> combat(s)</p>
<?php if (!$rows): ?><div class="empty"><strong>Aucun combat</strong>Les combats sont créés à la validation des poules.</div><?php else: ?>
<div class="table-wrap"><table class="table-compact">
  <thead><tr><th>N°</th><th>Tapis</th><th>Poule</th><th>Combattant A</th><th>Score</th><th>Combattant B</th><th>État</th><th>Résultat</th><th>Validation</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $b): ?>
    <tr><td class="num"><?= (int) $b['id'] ?></td><td class="num"><?= e($b['tatami_number'] ?? '—') ?> · <?= (int) $b['order_on_tatami'] ?></td><td class="small"><a href="<?= e(url($base . '/poules', ['vue' => 'feuilles', 'q' => preg_replace('/^.*Poule /u', '', $b['pool_label'])]) . '#poule-' . $b['pool_id']) ?>" title="Ouvrir la feuille de poule"><?= e($b['pool_label']) ?></a> · combat <?= (int) $b['order_in_pool'] ?><?= $b['phase'] === 'final' ? ' <span class="badge badge-yellow">barrage</span>' : '' ?></td>
      <td<?= $b['winner_side'] === 'a' ? ' style="font-weight:700"' : '' ?>><?= e($b['a_name']) ?></td>
      <td class="num nowrap"><?= (int) $b['a_ippon'] . (int) $b['a_wazaari'] . (int) $b['a_yuko'] ?> <span class="muted">s<?= (int) $b['a_shido'] ?></span> – <?= (int) $b['b_ippon'] . (int) $b['b_wazaari'] . (int) $b['b_yuko'] ?> <span class="muted">s<?= (int) $b['b_shido'] ?></span></td>
      <td<?= $b['winner_side'] === 'b' ? ' style="font-weight:700"' : '' ?>><?= e($b['b_name']) ?></td>
      <td><span class="badge <?= ['validated' => 'badge-green', 'in_progress' => 'badge-red', 'finished' => 'badge-orange', 'cancelled' => 'badge-ink'][$b['status']] ?? '' ?>"><?= e(BoutService::STATUS[$b['status']]) ?></span></td>
      <td class="small"><?= $b['win_method'] ? e(BoutService::METHODS[$b['win_method']]) : '' ?></td>
      <td class="small"><?= $b['validated_at'] ? e(short_datetime($b['validated_at'])) . '<br>' . e($b['validator']) : '' ?></td>
      <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/combats/' . $b['id'])) ?>">Détail</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php $query = $f; include BASE_PATH . '/app/Views/partials/pagination.php'; ?>
<?php endif; ?>
