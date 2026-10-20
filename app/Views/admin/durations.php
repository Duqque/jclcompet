<?php use App\Services\CategoryService; $heading = 'Durées de combat'; include __DIR__ . '/_head.php'; $u = url('/admin/competitions/' . $c['id'] . '/durees');
$gs = ['none' => 'Pas de golden score', 'limited' => 'Golden score limité', 'unlimited' => 'Golden score illimité']; ?>
<p class="muted">Chaque combat copie la règle applicable au moment de sa création : modifier une règle ne change pas les combats existants. Règle la plus spécifique d'abord (catégorie, sexe, phase), puis priorité.</p>
<?php foreach ($issues as $i): ?><div class="alert alert-<?= $i['level'] === 'error' ? 'error' : 'warning' ?> small"><?= e($i['message']) ?></div><?php endforeach; ?>
<div class="table-wrap" style="margin-bottom:1.2rem"><table>
  <thead><tr><th>Catégorie</th><th>Sexe</th><th>Phase</th><th>Durée</th><th>Golden score</th><th>Récupération</th><th>Note</th><th>Priorité</th><th></th></tr></thead>
  <tbody><?php foreach ($rules as $rl): ?>
    <tr><td><?= e($rl['age_name'] ?? 'Par défaut') ?><?= $rl['is_example'] ? ' <span class="badge badge-orange">exemple à confirmer</span>' : '' ?><?= $rl['label'] ? '<br><span class="small muted">' . e($rl['label']) . '</span>' : '' ?></td>
      <td><?= e($rl['sex'] ? sex_label($rl['sex']) : 'Tous') ?></td><td><?= e(['all' => 'Toutes', 'pool' => 'Poules', 'final' => 'Barrage / classement'][$rl['phase']]) ?></td>
      <td class="num"><strong><?= e(duration_label((int) $rl['duration_seconds'])) ?></strong></td>
      <td><?= e($gs[$rl['golden_score_mode']]) ?><?= $rl['golden_score_seconds'] ? ' (' . e(duration_label((int) $rl['golden_score_seconds'])) . ')' : '' ?></td>
      <td class="num"><?= (int) $rl['recovery_seconds'] ?> s</td><td class="small"><?= e($rl['overtime_rule']) ?></td><td class="num"><?= (int) $rl['priority'] ?></td>
      <td class="actions"><details><summary class="btn btn-ghost btn-sm">Modifier</summary><?php $rule = $rl; include __DIR__ . '/_duration_form.php'; ?></details>
        <form method="post" action="<?= e($u) ?>" data-confirm="Supprimer cette règle ?" data-danger style="margin-top:.3rem"><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="rule_id" value="<?= (int) $rl['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Supprimer</button></form></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<div class="grid grid-2">
  <section class="card"><h2 style="font-size:1.15rem">Ajouter une règle</h2><?php $rule = null; include __DIR__ . '/_duration_form.php'; ?></section>
  <section class="card"><h2 style="font-size:1.15rem">Combats déjà créés</h2>
    <p><?= (int) $scheduled ?> combat(s) à venir. Après une modification des règles, vous pouvez leur appliquer explicitement les nouvelles durées (les combats commencés ne sont jamais modifiés).</p>
    <form method="post" action="<?= e($u) ?>" data-confirm="Appliquer les règles actuelles aux <?= (int) $scheduled ?> combats à venir ?"><?= csrf_field() ?><input type="hidden" name="op" value="reapply"><button class="btn btn-yellow" type="submit"<?= $scheduled ? '' : ' disabled' ?>>Réappliquer aux combats à venir</button></form>
  </section>
</div>
