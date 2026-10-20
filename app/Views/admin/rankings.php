<?php use App\Services\CategoryService; $heading = 'Classements'; $actions = '<a class="btn btn-ghost" href="' . e(url('/classement/' . $c['slug'])) . '" target="_blank">Écran public ↗</a><a class="btn btn-ghost" href="' . e(url('/admin/competitions/' . $c['id'] . '/bareme')) . '">Barème</a>'; include __DIR__ . '/_head.php'; $u = url('/admin/competitions/' . $c['id'] . '/classements'); ?>
<div class="btn-group" style="margin-bottom:1rem">
  <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="recompute"><button class="btn" type="submit">Recalculer tous les classements</button></form>
  <span class="small muted">Dernier calcul : <?= e($lastUpdate ? short_datetime($lastUpdate) : '—') ?> · <?= (int) $incomplete ?> poule(s) non terminée(s) (aucune médaille attribuée tant qu'une poule est incomplète)</span>
</div>
<?php foreach ($tiebreaks as $p): ?>
  <section class="card" style="border-left:6px solid var(--orange);margin-bottom:1rem">
    <h2 style="font-size:1.1rem">Égalité à départager — <?= e($p['label']) ?></h2>
    <p class="small muted">Victoires et points de valeur identiques, confrontation directe insuffisante. Saisissez l'ordre final ou ajoutez un combat de barrage.</p>
    <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="tiebreak"><input type="hidden" name="pool_id" value="<?= (int) $p['id'] ?>">
      <table class="table-compact"><thead><tr><th>Rang calculé</th><th>Athlète</th><th>V</th><th>Pts</th><th>Rang décidé</th></tr></thead><tbody>
      <?php foreach ($p['calc']['rows'] as $row): $m = $p['members'][$row['registration_id']] ?? []; ?><tr<?= $row['tied'] ? ' style="background:var(--orange-soft)"' : '' ?>><td><?= (int) $row['rank'] ?></td><td><?= e(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></td><td><?= (int) $row['wins'] ?></td><td><?= (int) $row['value_points'] ?></td>
        <td><input aria-label="Rang" name="rank[<?= (int) $row['registration_id'] ?>]" type="number" min="1" max="10" value="<?= e($row['manual_rank'] ?? ($row['tied'] ? '' : $row['rank'])) ?>" style="width:80px;min-height:34px"></td></tr><?php endforeach; ?>
      </tbody></table>
      <div class="field" style="margin-top:.6rem"><label>Motif</label><input name="reason" required minlength="5" placeholder="Décision de la table centrale / arbitrage"></div>
      <button class="btn btn-red btn-sm" type="submit" style="margin-top:.6rem">Enregistrer le départage</button>
    </form>
    <form method="post" action="<?= e($u) ?>" class="btn-group" style="margin-top:.8rem"><?= csrf_field() ?><input type="hidden" name="op" value="barrage"><input type="hidden" name="pool_id" value="<?= (int) $p['id'] ?>">
      <?php $tied = array_filter($p['calc']['rows'], fn ($x) => $x['tied']); foreach (['a', 'b'] as $k): ?><select name="<?= $k ?>" aria-label="Athlète" style="max-width:220px"><?php foreach ($tied as $row): $m = $p['members'][$row['registration_id']] ?? []; ?><option value="<?= (int) $row['registration_id'] ?>"><?= e(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></option><?php endforeach; ?></select><?php endforeach; ?>
      <button class="btn btn-ghost btn-sm" type="submit">Ajouter un combat de barrage</button></form>
  </section>
<?php endforeach; ?>
<div class="grid grid-2">
  <section class="card"><h2 style="font-size:1.15rem">Clubs — par points</h2><?php $rows = $byPoints; $by = 'points'; include __DIR__ . '/_club_table.php'; ?></section>
  <section class="card"><h2 style="font-size:1.15rem">Clubs — par médailles</h2><?php $rows = $byMedals; $by = 'medals'; include __DIR__ . '/_club_table.php'; ?></section>
</div>
<section class="card" style="margin-top:1.2rem"><h2 style="font-size:1.15rem">Classement individuel (poules terminées)</h2>
  <?php if (!$individual): ?><p class="muted">Aucune poule terminée.</p><?php else: ?>
  <div class="table-wrap"><table class="table-compact"><thead><tr><th>Catégorie</th><th>Poule</th><th>Place</th><th>Athlète</th><th>Club</th><th>Points</th><th>Médaille</th><th></th></tr></thead><tbody>
  <?php foreach ($individual as $i): ?><tr><td><?= e(CategoryService::label(['name' => $i['age_name'], 'name_m' => $i['name_m'], 'name_f' => $i['name_f']], $i['sex'])) ?> <?= e($i['weight_label'] ? $i['weight_label'] . ' kg' : '') ?></td><td><?= e($i['pool_label']) ?></td><td class="num"><?= (int) $i['place'] ?></td><td><?= e($i['first_name'] . ' ' . $i['last_name']) ?></td><td><?= e($i['club_name']) ?></td><td class="num"><?= (int) $i['points'] ?></td><td><?= $i['medal'] ? '<span class="medal medal-' . e($i['medal']) . '"></span>' : '' ?></td>
    <td><form method="post" action="<?= e($u) ?>" data-confirm="Exclure ce résultat des classements (points et médailles) ?" data-reason><?= csrf_field() ?><input type="hidden" name="op" value="exclude"><input type="hidden" name="pool_id" value="<?= (int) $i['pool_id'] ?>"><input type="hidden" name="registration_id" value="<?= (int) $i['registration_id'] ?>"><button class="btn-link small" type="submit">Exclure</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
