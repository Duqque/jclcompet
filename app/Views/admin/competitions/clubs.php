<?php $base = '/admin/competitions/' . $c['id']; ?>
<?php $heading = 'Clubs présents'; include dirname(__DIR__) . '/_head.php'; ?>
<p class="muted">Liste publiée dans le programme (« Merci aux clubs présents »), indépendante des inscriptions en ligne. Logos et liens Facebook se modifient dans la fiche de chaque club.</p>
<div class="grid grid-2">
  <section class="card">
    <h2 style="font-size:1.15rem"><?= count($present) ?> clubs affichés</h2>
    <?php if (!$present): ?><div class="empty"><strong>Aucun club</strong>Ajoutez les clubs présents à cette édition.</div><?php else: ?>
    <div class="table-wrap"><table class="table-compact"><tbody>
      <?php foreach ($present as $p): ?><tr>
        <td style="width:48px"><?php $logo = $p['logo_path']; $name = $p['name']; $size = 36; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?></td>
        <td><a href="<?= e(url('/admin/clubs/' . $p['club_id'])) ?>"><?= e($p['name']) ?></a><?= $p['department'] ? ' (' . e($p['department']) . ')' : '' ?><?= $p['is_host'] ? ' <span class="badge badge-red">hôte</span>' : '' ?></td>
        <td class="actions">
          <?php if (!$p['is_host']): ?><form method="post" action="<?= e(url($base . '/clubs-presents')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="op" value="host"><input type="hidden" name="club_id" value="<?= (int) $p['club_id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Club hôte</button></form><?php endif; ?>
          <form method="post" action="<?= e(url($base . '/clubs-presents')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="club_id" value="<?= (int) $p['club_id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Retirer</button></form>
        </td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <section class="card">
    <h2 style="font-size:1.15rem">Ajouter des clubs</h2>
    <?php if ($registered): ?>
      <form method="post" action="<?= e(url($base . '/clubs-presents')) ?>" style="margin-bottom:1rem"><?= csrf_field() ?><input type="hidden" name="op" value="add_registered"><button class="btn" type="submit">Ajouter les <?= count($registered) ?> clubs ayant des inscrits</button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url($base . '/clubs-presents')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="add">
      <div class="field"><label for="club_ids">Clubs existants</label><select id="club_ids" name="club_ids[]" multiple size="12"><?php foreach ($all as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?><?= $a['city'] ? ' — ' . e($a['city']) : '' ?></option><?php endforeach; ?></select><span class="hint">Ctrl/Cmd + clic pour en choisir plusieurs. Nouveau club : <a href="<?= e(url('/admin/clubs/nouveau')) ?>">créer un club</a>.</span></div>
      <button class="btn btn-red" type="submit" style="margin-top:.6rem">Ajouter la sélection</button>
    </form>
  </section>
</div>
