<?php $b = '/espace-club/competitions/' . $c['id']; ?>
<div class="page-head">
  <div><p class="eyebrow"><?= e($c['name']) ?></p><h1>Photos</h1><p class="muted" style="margin:.2rem 0 0"><?= count($photos) ?> photo(s) dans l'album de la compétition</p></div>
  <?php if ($canUpload): ?><div class="btn-group"><button class="btn btn-lg" type="button" data-dialog-open="add-photos"><?= icon('plus') ?>Ajouter des photos</button></div><?php endif; ?>
</div>
<div class="alert alert-info small">Les photos sont visibles par les organisateurs et les clubs connectés<?= $hasPassword ? ', et sur le site public avec le mot de passe de l\'album' : '' ?>. Elles sont réservées à un usage privé : ne les republiez pas sans l'accord des familles.</div>
<?php if (!$photos): ?>
  <div class="empty"><strong>Aucune photo pour l'instant</strong><?= $canUpload ? 'Partagez les vôtres avec « Ajouter des photos ».' : '' ?></div>
<?php else: ?>
  <div class="club-gallery" data-gallery>
    <?php foreach ($photos as $ph): ?>
      <a href="<?= e(url('/media/' . $ph['id'])) ?>" target="_blank" rel="noopener" title="<?= e($ph['title']) ?>"><img src="<?= e(url('/media/' . $ph['id'], ['mini' => 1])) ?>" alt="<?= e($ph['title']) ?>" loading="lazy"></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($canUpload): ?>
<dialog class="add-dialog" id="add-photos" aria-labelledby="add-photos-t">
  <div class="cd-head"><h2 id="add-photos-t">Ajouter des photos</h2><span style="flex:1"></span><button type="button" class="icon-btn" data-dialog-close aria-label="Fermer"><?= icon('x') ?></button></div>
  <form class="add-body stack" method="post" enctype="multipart/form-data" action="<?= e(url($b . '/photos')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="photos">Photos (JPEG, PNG ou WebP, plusieurs à la fois)</label><input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required></div>
    <div class="field"><label for="caption">Légende (facultative)</label><input id="caption" name="caption" type="text" maxlength="120" placeholder="ex. Podium des poussins"></div>
    <div class="btn-group" style="justify-content:flex-end"><button class="btn btn-ghost" type="button" data-dialog-close>Annuler</button><button class="btn" type="submit"><?= icon('plus') ?>Envoyer les photos</button></div>
  </form>
</dialog>
<?php endif; ?>
