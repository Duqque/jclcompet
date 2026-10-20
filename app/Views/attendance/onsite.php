<header class="att-head"><div class="row"><a class="btn btn-sm btn-outline-white" href="<?= e(url('/accueil/' . $c['id'])) ?>" aria-label="Retour">←</a><h1>Inscription sur place</h1></div></header>
<main style="padding:16px 16px 40px;max-width:760px;margin:0 auto">
<?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
<?php if (!$lateAllowed): ?><div class="alert alert-warning">Les inscriptions tardives ne sont pas autorisées pour cette compétition (réservé à l'administration).</div><?php endif; ?>
<?php if ($needsValidation): ?><div class="alert alert-info small">L'inscription sera enregistrée et la présence validée ; elle devra être confirmée par l'administration avant la génération des poules.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('/accueil/' . $c['id'] . '/sur-place')) ?>" class="card"><?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label for="club_id">Club</label><select id="club_id" name="club_id"><option value="">— Club non enregistré —</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"<?= selected(old('club_id'), $cl['id']) ?>><?= e($cl['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="club_exception_label">ou exception autorisée</label><input id="club_exception_label" name="club_exception_label" maxlength="150" value="<?= e(old('club_exception_label')) ?>" placeholder="Nom du club"></div>
  </div>
  <div style="margin-top:1rem"><?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?></div>
  <?php if ($weightInput === 'weight'): ?><div class="field" style="margin-top:1rem"><label for="measured_weight_kg">Poids mesuré à la pesée (kg)</label><input id="measured_weight_kg" name="measured_weight_kg" type="number" step="0.1" inputmode="decimal"></div><?php endif; ?>
  <button class="btn btn-red btn-lg btn-block btn-display" type="submit" style="margin-top:1.2rem">Inscrire et pointer présent</button>
</form>
</main>
