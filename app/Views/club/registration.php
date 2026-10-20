<?php $b = '/espace-club/competitions/' . $c['id']; ?>
<div class="page-head"><div><p class="eyebrow"><a href="<?= e(url($b)) ?>"><?= e($c['name']) ?></a></p><h1><?= e($reg['first_name'] . ' ' . $reg['last_name']) ?></h1></div></div>
<?php if (!$canEdit): ?><div class="alert alert-warning">La date limite est dépassée : modification impossible.</div><?php endif; ?>
<form method="post" action="<?= e(url($b . '/inscriptions/' . $reg['id'])) ?>" class="card"><?= csrf_field() ?>
  <?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?>
  <div class="btn-group" style="margin-top:1rem"><button class="btn btn-red" type="submit"<?= $canEdit ? '' : ' disabled' ?>>Enregistrer</button><a class="btn btn-ghost" href="<?= e(url($b)) ?>">Annuler</a></div>
</form>
