<?php /** @var string $listKey @var array $def @var array $items @var ?int $cid */
$hidden = csrf_field() . ($cid ? '<input type="hidden" name="competition_id" value="' . (int) $cid . '">' : ''); ?>
<?php if (!$items): ?><p class="muted small">Liste vide.</p><?php endif; ?>
<?php foreach ($items as $i => $it): ?>
  <details style="border-bottom:1px solid var(--line);padding:.4rem 0">
    <summary style="cursor:pointer;display:flex;gap:.5rem;align-items:center"><?php if (!empty($it['group_label'])): ?><span class="badge"><?= e($it['group_label']) ?></span><?php endif; ?><strong><?= e($it['title']) ?></strong> <span class="small muted"><?= e(mb_strimwidth((string) ($it['subtitle'] ?: $it['body']), 0, 80, '…')) ?></span><?= $it['is_published'] ? '' : ' <span class="badge">masqué</span>' ?></summary>
    <?php $item = $it; include __DIR__ . '/_item_form.php'; ?>
    <div class="btn-group" style="margin-top:.3rem">
      <form method="post" action="<?= e(url('/admin/site')) ?>"><?= $hidden ?><input type="hidden" name="op" value="item_up"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>"><button class="icon-btn" type="submit" aria-label="Monter">↑</button></form>
      <form method="post" action="<?= e(url('/admin/site')) ?>"><?= $hidden ?><input type="hidden" name="op" value="item_down"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>"><button class="icon-btn" type="submit" aria-label="Descendre">↓</button></form>
      <form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer « <?= e($it['title']) ?> » ?" data-danger><?= $hidden ?><input type="hidden" name="op" value="item_delete"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>"><button class="btn-link small" type="submit">Supprimer</button></form>
    </div>
  </details>
<?php endforeach; ?>
<details style="margin-top:.6rem"><summary class="btn btn-ghost btn-sm">Ajouter un élément</summary><?php $item = null; include __DIR__ . '/_item_form.php'; ?></details>
