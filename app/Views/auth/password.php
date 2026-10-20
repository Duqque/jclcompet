<h1 style="font-size:1.6rem">Changer mon mot de passe</h1>
<?php if (!empty($user['must_change_password'])): ?><div class="alert alert-warning">Pour des raisons de sécurité, choisissez un nouveau mot de passe avant de continuer.</div><?php endif; ?>
<form method="post" action="<?= e(url('/compte/mot-de-passe')) ?>" class="stack">
  <?= csrf_field() ?>
  <div class="field"><label for="current_password">Mot de passe actuel</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
  <div class="field"><label for="password">Nouveau mot de passe</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required><span class="hint">12 caractères minimum, avec des lettres et des chiffres.</span></div>
  <div class="field"><label for="password_confirmation">Confirmation</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
  <button class="btn btn-red btn-block" type="submit">Enregistrer le mot de passe</button>
</form>
<form method="post" action="<?= e(url('/deconnexion')) ?>" style="margin-top:1rem"><?= csrf_field() ?><button class="btn-link" type="submit">Se déconnecter</button></form>
