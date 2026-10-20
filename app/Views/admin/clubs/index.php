<div class="page-head"><div><p class="eyebrow">CRM</p><h1>Clubs</h1></div><a class="btn btn-red" href="<?= e(url('/admin/clubs/nouveau')) ?>">Créer un club</a></div>
<form method="get" class="filters"><div class="field wide"><label for="q">Recherche</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom ou ville"></div><button class="btn btn-ghost" type="submit">Rechercher</button></form>
<div class="table-wrap"><table>
  <thead><tr><th></th><th>Club</th><th>Ville</th><th>Contact</th><th>Comptes</th><th>Athlètes</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $cl): ?>
    <tr<?= $cl['is_active'] ? '' : ' style="opacity:.55"' ?>><td style="width:48px"><?php $logo = $cl['logo_path']; $name = $cl['name']; $size = 36; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?></td>
      <td><a href="<?= e(url('/admin/clubs/' . $cl['id'])) ?>"><strong><?= e($cl['name']) ?></strong></a><?= $cl['is_demo'] ? ' <span class="badge badge-yellow">démo</span>' : '' ?></td>
      <td><?= e($cl['city']) ?><?= $cl['department'] ? ' (' . e($cl['department']) . ')' : '' ?></td><td class="small"><?= e($cl['contact_name']) ?><?= $cl['email'] ? '<br>' . e($cl['email']) : '' ?></td>
      <td class="num"><?= (int) $cl['accounts'] ?></td><td class="num"><?= (int) $cl['athletes'] ?></td><td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/clubs/' . $cl['id'])) ?>">Modifier</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
