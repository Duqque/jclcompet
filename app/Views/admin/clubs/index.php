<?php $profileActions = '<a class="btn" href="' . e(url('/admin/clubs/nouveau')) . '">' . icon('plus') . 'Créer un club</a>'; include dirname(__DIR__) . '/_profiles_tabs.php'; ?>
<form method="get" class="toolbar"><label class="search"><?= icon('search') ?><span class="sr-only">Rechercher</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un club ou une ville…"></label></form>
<div class="table-wrap"><table>
  <thead><tr><th></th><th>Club</th><th>Ville</th><th>Contact</th><th>Comptes</th><th>Athlètes</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $cl): ?>
    <tr<?= $cl['is_active'] ? '' : ' style="opacity:.55"' ?>><td style="width:48px"><?php $logo = $cl['logo_path']; $name = $cl['name']; $size = 36; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?></td>
      <td><a href="<?= e(url('/admin/clubs/' . $cl['id'])) ?>"><strong><?= e($cl['name']) ?></strong></a><?= $cl['is_demo'] ? ' <span class="badge badge-yellow">démo</span>' : '' ?></td>
      <td><?= e($cl['city']) ?><?= $cl['department'] ? ' (' . e($cl['department']) . ')' : '' ?></td><td class="small"><?= e($cl['contact_name']) ?><?= $cl['email'] ? '<br>' . e($cl['email']) : '' ?></td>
      <td class="num"><?= (int) $cl['accounts'] ?></td><td class="num"><?= (int) $cl['athletes'] ?></td><td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/clubs/' . $cl['id'])) ?>">Modifier</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
