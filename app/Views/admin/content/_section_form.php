<?php /** @var ?array $sec @var ?int $cid @var string $pageKey */ $sv = $sec ?? []; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/site')) ?>" class="stack" style="margin-top:.6rem">
  <?= csrf_field() ?><input type="hidden" name="op" value="section_save"><?php if ($cid): ?><input type="hidden" name="competition_id" value="<?= (int) $cid ?>"><?php endif; ?>
  <?php if ($sec): ?><input type="hidden" name="section_id" value="<?= (int) $sec['id'] ?>"><?php else: ?><input type="hidden" name="page_key" value="<?= e($pageKey) ?>"><div class="field"><label>Clé technique (facultatif)</label><input name="section_key" maxlength="60" placeholder="ex. infos_parking"></div><?php endif; ?>
  <div class="form-grid">
    <div class="field"><label>Titre</label><input name="title" maxlength="190" value="<?= e($sv['title'] ?? '') ?>"></div>
    <div class="field"><label>Ordre</label><input name="sort_order" type="number" value="<?= (int) ($sv['sort_order'] ?? 0) ?>"></div>
    <div class="field"><label>Image</label><input name="image" type="file" accept="image/png,image/jpeg,image/webp">
      <?php if (!empty($sv['image_document_id'])): ?><img src="<?= e(url('/media/' . $sv['image_document_id'])) ?>" alt="" style="max-width:140px;margin-top:.3rem"><label class="check"><input type="checkbox" name="remove_image" value="1"> Retirer l'image</label><?php endif; ?></div>
    <label class="check"><input type="checkbox" name="is_published" value="1"<?= checked($sv['is_published'] ?? 1) ?>> Publiée</label>
  </div>
  <div class="field"><label>Texte</label><textarea name="body" rows="6"><?= e($sv['body'] ?? '') ?></textarea><span class="hint">**gras** · *italique* · « - » en début de ligne pour une liste · [texte](https://lien) · ^e^ pour un exposant · ligne vide = nouveau paragraphe.</span></div>
  <div class="btn-group"><button class="btn btn-red btn-sm" type="submit">Enregistrer</button></div>
</form>
<?php if ($sec): ?><form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer la section « <?= e($sec['title'] ?: $sec['section_key']) ?> » ?" data-danger style="margin-top:.4rem"><?= csrf_field() ?><input type="hidden" name="op" value="section_delete"><input type="hidden" name="section_id" value="<?= (int) $sec['id'] ?>"><?php if ($cid): ?><input type="hidden" name="competition_id" value="<?= (int) $cid ?>"><?php endif; ?><button class="btn-link small" type="submit">Supprimer la section</button></form><?php endif; ?>
