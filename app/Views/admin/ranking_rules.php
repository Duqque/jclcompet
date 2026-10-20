<?php $heading = 'Barème de points'; include __DIR__ . '/_head.php'; ?>
<p class="muted">Points et médaille attribués selon la place finale dans la poule. Les valeurs initiales sont un <strong>exemple modifiable</strong>, non officiel. Le classement par médailles est indépendant du classement par points. Toute modification recalcule les classements.</p>
<form method="post" action="<?= e(url('/admin/competitions/' . $c['id'] . '/bareme')) ?>"><?= csrf_field() ?>
<div class="table-wrap" style="max-width:640px"><table>
  <thead><tr><th>Place</th><th>Points</th><th>Médaille</th><th>Compter dans le classement</th></tr></thead>
  <tbody>
  <?php $rows = array_values($rules); for ($i = 0; $i < max(8, count($rows) + 2); $i++): $rr = $rows[$i] ?? ['place' => $i + 1, 'points' => '', 'medal' => null, 'counts_in_table' => 1]; ?>
    <tr><td><input aria-label="Place" name="rules[<?= $i ?>][place]" type="number" min="1" max="50" value="<?= e($rr['place']) ?>" style="width:80px"></td>
      <td><input aria-label="Points" name="rules[<?= $i ?>][points]" type="number" min="0" value="<?= e($rr['points']) ?>" style="width:100px"></td>
      <td><select aria-label="Médaille" name="rules[<?= $i ?>][medal]"><option value="">—</option><option value="gold"<?= selected($rr['medal'], 'gold') ?>>Or</option><option value="silver"<?= selected($rr['medal'], 'silver') ?>>Argent</option><option value="bronze"<?= selected($rr['medal'], 'bronze') ?>>Bronze</option></select></td>
      <td><label class="check"><input type="checkbox" name="rules[<?= $i ?>][excluded]" value="1"<?= checked(!(int) $rr['counts_in_table']) ?>> Exclure les points</label></td></tr>
  <?php endfor; ?>
  </tbody>
</table></div>
<button class="btn btn-red" type="submit" style="margin-top:1rem">Enregistrer et recalculer</button>
</form>
