<?php use App\Services\RegistrationService; $heading = $reg ? $reg['first_name'] . ' ' . $reg['last_name'] : 'Nouvelle inscription'; include dirname(__DIR__) . '/_head.php'; $base = '/admin/competitions/' . $c['id']; ?>
<?php if ($reg): ?>
  <div class="btn-group" style="margin-bottom:1rem">
    <span class="badge <?= $reg['status'] === 'confirmed' ? 'badge-green' : ($reg['status'] === 'pending' ? 'badge-orange' : 'badge-red') ?>"><?= e(RegistrationService::STATUS[$reg['status']]) ?></span>
    <span class="badge"><?= e(RegistrationService::ATTENDANCE[$reg['attendance_status']]) ?></span>
    <?php if ($reg['is_late']): ?><span class="badge badge-orange">tardive</span><?php endif; ?><?php if ($reg['is_onsite']): ?><span class="badge badge-yellow">sur place</span><?php endif; ?>
    <?php if ($inPool): ?><span class="badge badge-blue">placé(e) en poule</span><?php endif; ?>
  </div>
  <?php if ($inPool): ?><div class="alert alert-info small">Athlète placé(e) dans une poule : pour modifier sa catégorie, retirez-le de sa poule depuis « Poules et tapis ».</div><?php endif; ?>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url($reg ? $base . '/inscriptions/' . $reg['id'] : $base . '/inscriptions')) ?>">
  <?= csrf_field() ?>
  <fieldset><legend>Club</legend><div class="form-grid">
    <div class="field"><label for="club_id">Club</label><select id="club_id" name="club_id"><option value="">— Sans club enregistré (exception) —</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"<?= selected(old('club_id', $reg['club_id'] ?? ''), $cl['id']) ?>><?= e($cl['name']) ?></option><?php endforeach; ?></select></div>
    <?php if (!$reg): ?><div class="field"><label for="club_exception_label">Exception autorisée (club non enregistré)</label><input id="club_exception_label" name="club_exception_label" maxlength="150" value="<?= e(old('club_exception_label')) ?>" placeholder="Nom du club ou « individuel »"></div><?php endif; ?>
  </div></fieldset>
  <fieldset><legend>Athlète</legend><?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?>
    <div class="field" style="margin-top:1rem"><label for="admin_comment">Commentaire administratif (non visible par le club)</label><textarea id="admin_comment" name="admin_comment" rows="2" maxlength="500"><?= e(old('admin_comment', $reg['admin_comment'] ?? '')) ?></textarea></div>
  </fieldset>
  <div class="btn-group"><button class="btn btn-red btn-lg" type="submit"><?= $reg ? 'Enregistrer' : 'Inscrire' ?></button><?php if (!$reg): ?><button class="btn btn-ghost btn-lg" type="submit" name="again" value="1">Inscrire et saisir un autre</button><?php endif; ?><a class="btn btn-ghost btn-lg" href="<?= e(url($base . '/inscriptions')) ?>">Retour à la liste</a></div>
</form>
<?php if ($reg): ?>
<div class="grid grid-2" style="margin-top:1.4rem">
  <section class="card"><h2 style="font-size:1.1rem">Actions</h2><div class="btn-group">
    <?php $act = fn ($op, $label, $cls, $confirm = null, $reason = false) => '<form method="post" action="' . e(url($base . '/inscriptions/' . $reg['id'] . '/action')) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . ($reason ? ' data-reason' : '') . '>' . csrf_field() . '<input type="hidden" name="op" value="' . $op . '"><button class="btn btn-sm ' . $cls . '" type="submit">' . $label . '</button></form>'; ?>
    <?php if ($reg['needs_validation']): ?><?= $act('validate', 'Valider l\'inscription tardive', 'btn-green') ?><?= $act('reject', 'Refuser', 'btn-danger', 'Refuser cette inscription ?', true) ?><?php endif; ?>
    <?php if (in_array($reg['status'], ['confirmed', 'pending'], true)): ?><?= $act('withdraw', 'Retirer l\'inscription', 'btn-danger', 'Retirer cette inscription ?', true) ?><?php else: ?><?= $act('restore', 'Rétablir l\'inscription', 'btn-ghost') ?><?php endif; ?>
  </div></section>
  <section class="card"><h2 style="font-size:1.1rem">Historique des présences</h2>
    <?php if (empty($history)): ?><p class="muted small">Aucun pointage.</p><?php else: ?><ul class="small" style="padding-left:1rem;margin:0"><?php foreach ($history as $h): ?><li><?= e(short_datetime($h['recorded_at'])) ?> — <?= e(RegistrationService::ATTENDANCE[$h['status']]) ?><?= $h['measured_weight_kg'] ? ' · ' . e(kg($h['measured_weight_kg'])) : '' ?> · <?= e($h['display_name'] ?? '—') ?><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
<?php endif; ?>
