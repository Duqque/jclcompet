<?php
/** @var list<array> $clubs */
$steps = [
    ['Votre demande', 'Nom, club, téléphone et mot de passe.'],
    ['Validation', "L'organisation du JC Leforest vérifie et active votre compte."],
    ['Vos inscriptions', 'Sur le site et dans l\'application JCL Live, en direct.'],
];
$newClub = old('club_name') !== '';
?>
<main class="login signup-page">
  <div class="login-card">
    <section class="login-visual">
      <a class="login-brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="60" height="40"><span><?= e($clubName) ?></span></a>
      <div class="login-pitch">
        <span class="login-pill">Espace coach · Création de compte</span>
        <h1>Inscrivez vos judokas, où que vous soyez</h1>
        <p>Un seul compte pour le site et l'application iPhone : ajoutez vos combattants, validez votre liste, pointez présences et pesées le jour J. Tout se met à jour en direct pour l'organisation.</p>
      </div>
      <ol class="signup-steps">
        <?php foreach ($steps as $i => [$t, $d]): ?>
          <li><span class="n"><?= $i + 1 ?></span><strong><?= e($t) ?></strong><small><?= e($d) ?></small></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <section class="login-form">
      <div class="login-form-inner signup-inner">
        <h2>Créer un compte coach</h2>
        <p class="muted login-for">Déjà inscrit ? <a href="<?= e(url('/connexion?profil=club')) ?>">Se connecter</a></p>
        <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
        <form method="post" action="<?= e(url('/creer-un-compte')) ?>" class="stack" novalidate>
          <?= csrf_field() ?>
          <div class="signup-2">
            <div class="field"><label for="first_name">Prénom</label><input id="first_name" name="first_name" autocomplete="given-name" required value="<?= e(old('first_name')) ?>"></div>
            <div class="field"><label for="last_name">Nom</label><input id="last_name" name="last_name" autocomplete="family-name" required value="<?= e(old('last_name')) ?>"><?= field_error('last_name') ?></div>
          </div>
          <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>" placeholder="prenom.nom@club.fr"><span class="hint">Ce sera votre identifiant.</span><?= field_error('email') ?></div>
          <div class="field"><label for="phone">Téléphone</label><input id="phone" name="phone" type="tel" autocomplete="tel" required value="<?= e(old('phone')) ?>" placeholder="06 12 34 56 78"><?= field_error('phone') ?></div>
          <div class="field signup-club"><label>Votre club</label>
            <?php $pickerClubs = $clubs; $pickerCurrent = old('club_id') ? (int) old('club_id') : null; $pickerId = 'signup-clubs'; $pickerMode = 'field'; $pickerName = 'club_id'; $pickerAllLabel = 'Choisir mon club'; $pickerTotal = null; include BASE_PATH . '/app/Views/partials/club_picker.php'; ?>
            <?= field_error('club_id') ?>
            <details class="signup-newclub"<?= $newClub ? ' open' : '' ?>><summary>Mon club n'est pas dans la liste</summary>
              <div class="signup-2">
                <div class="field"><label for="club_name">Nom du club</label><input id="club_name" name="club_name" maxlength="150" value="<?= e(old('club_name')) ?>"></div>
                <div class="field"><label for="club_city">Ville</label><input id="club_city" name="club_city" maxlength="120" value="<?= e(old('club_city')) ?>"></div>
              </div>
            </details>
          </div>
          <div class="field"><label for="password">Mot de passe</label>
            <div class="pw"><input id="password" name="password" type="password" autocomplete="new-password" required placeholder="••••••••••••">
              <button type="button" class="pw-toggle" data-pw-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><?= icon('eye') ?></button></div>
            <span class="hint">Au moins 12 caractères, avec des lettres et des chiffres.</span></div>
          <div class="field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="••••••••••••"></div>
          <div class="field"><label for="message">Message pour l'organisation <span class="muted">(facultatif)</span></label><textarea id="message" name="message" rows="2" maxlength="500" placeholder="Ex. : je suis l'entraîneur des poussins."><?= e(old('message')) ?></textarea></div>
          <button class="btn btn-lg btn-block" type="submit">Envoyer ma demande</button>
        </form>
        <p class="small muted login-help">Vos coordonnées servent uniquement à l'organisation de la compétition.<br><a href="<?= e(url('/')) ?>">Retour au site</a></p>
      </div>
    </section>
  </div>
</main>
