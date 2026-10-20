<?php /** @var ?array $item @var string $listKey @var array $def @var ?int $cid */ $iv = $item ?? []; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/site')) ?>" style="margin:.5rem 0">
  <?= csrf_field() ?><input type="hidden" name="op" value="item_save"><input type="hidden" name="list_key" value="<?= e($listKey) ?>"><?php if ($cid): ?><input type="hidden" name="competition_id" value="<?= (int) $cid ?>"><?php endif; ?>
  <?php if ($item): ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <?php if (isset($def['group'])): ?><div class="field"><label><?= e($def['group']) ?></label><input name="group_label" maxlength="120" value="<?= e($iv['group_label'] ?? '') ?>"></div><?php endif; ?>
    <div class="field"><label class="required"><?= e($def['title']) ?></label><input name="title" maxlength="255" required value="<?= e($iv['title'] ?? '') ?>"></div>
    <?php if (isset($def['subtitle'])): ?><div class="field"><label><?= e($def['subtitle']) ?></label><input name="subtitle" maxlength="255" value="<?= e($iv['subtitle'] ?? '') ?>"></div><?php endif; ?>
    <?php if (isset($def['link'])): ?><div class="field"><label><?= e($def['link']) ?></label><input name="link_url" maxlength="255" value="<?= e($iv['link_url'] ?? '') ?>" placeholder="https://"></div><?php endif; ?>
    <?php if (isset($def['image'])): ?><div class="field"><label><?= e($def['image']) ?></label><input name="image" type="file" accept="image/png,image/jpeg,image/webp"><?php if (!empty($iv['image_document_id'])): ?><img src="<?= e(url('/media/' . $iv['image_document_id'])) ?>" alt="" style="max-width:100px;margin-top:.3rem"><label class="check"><input type="checkbox" name="remove_image" value="1"> Retirer</label><?php endif; ?></div><?php endif; ?>
    <?php if (isset($def['highlight'])): ?><label class="check"><input type="checkbox" name="is_highlight" value="1"<?= checked($iv['is_highlight'] ?? 0) ?>> <?= e($def['highlight']) ?> (mis en évidence)</label><?php endif; ?>
    <label class="check"><input type="checkbox" name="is_published" value="1"<?= checked($iv['is_published'] ?? 1) ?>> Publié</label>
  </div>
  <?php if (isset($def['body'])): ?><div class="field" style="margin-top:.6rem"><label><?= e($def['body']) ?></label><textarea name="body" rows="3"><?= e($iv['body'] ?? '') ?></textarea></div><?php endif; ?>
  <button class="btn btn-red btn-sm" type="submit" style="margin-top:.5rem"><?= $item ? 'Enregistrer' : 'Ajouter' ?></button>
</form>
