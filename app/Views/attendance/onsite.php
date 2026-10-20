<header class="att-bar">
  <a class="icon-btn" href="<?= e(url('/accueil/' . $c['id'])) ?>" aria-label="Retour aux présences"><?= icon('arrow-left') ?></a>
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="42" height="28">
  <span class="att-comp"><?= e($c['name']) ?></span>
</header>
<main class="att-page onsite" id="contenu">
  <div class="att-title"><p class="eyebrow">Accueil · jour J</p><h1>Inscription sur place</h1><p class="muted">Le judoka est inscrit et pointé présent en une seule fois.</p></div>
  <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <?php if (!$lateAllowed): ?><div class="alert alert-warning">Les inscriptions tardives ne sont pas autorisées pour cette compétition (réservé à l'administration).</div><?php endif; ?>
  <?php if ($needsValidation): ?><div class="alert alert-info small">L'inscription devra être confirmée par l'administration avant la génération des poules.</div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="<?= e(url('/accueil/' . $c['id'] . '/sur-place')) ?>"><?= csrf_field() ?>
    <fieldset>
      <legend>Club</legend>
      <div class="onsite-club">
        <?php $pickerClubs = $clubs; $pickerCurrent = old('club_id') ? (int) old('club_id') : null; $pickerId = 'onsite-clubs'; $pickerMode = 'field'; $pickerName = 'club_id'; $pickerAllLabel = 'Choisir un club'; $pickerTotal = null; include BASE_PATH . '/app/Views/partials/club_picker.php'; ?>
        <details class="onsite-exception"<?= old('club_exception_label') ? ' open' : '' ?>><summary>Club absent de la liste ?</summary>
          <div class="field" style="margin-top:.6rem"><label for="club_exception_label">Exception autorisée</label><input id="club_exception_label" name="club_exception_label" maxlength="150" value="<?= e(old('club_exception_label')) ?>" placeholder="Nom du club ou « individuel »"></div>
        </details>
      </div>
    </fieldset>
    <fieldset>
      <legend>Judoka</legend>
      <?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?>
    </fieldset>
    <?php if ($weightInput === 'weight'): ?>
    <fieldset>
      <legend>Pesée</legend>
      <div class="form-grid"><div class="field"><label for="measured_weight_kg">Poids mesuré (kg)</label><input id="measured_weight_kg" name="measured_weight_kg" type="number" step="0.1" min="10" max="250" inputmode="decimal" placeholder="ex. 32,4"><span class="hint">Laisser vide s'il n'y a pas de pesée : le poids déclaré est alors utilisé.</span></div></div>
    </fieldset>
    <?php endif; ?>
    <div class="onsite-submit">
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/accueil/' . $c['id'])) ?>">Annuler</a>
      <button class="btn btn-lg" type="submit"><?= icon('check') ?>Inscrire et pointer présent</button>
    </div>
  </form>
</main>
