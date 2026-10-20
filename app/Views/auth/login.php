<?php
$profiles = [
    'club' => ['Club', 'Inscrire mes judokas, suivre poules et résultats', 'users'],
    'commissaire' => ['Commissaire', 'Saisir les combats de mon tapis', 'whistle'],
    'accueil' => ['Accueil & buvette', 'Présences, caisse', 'clipboard'],
    'organisation' => ['Organisation', 'Administration de la compétition', 'shield'],
];
$current = array_key_exists($_GET['profil'] ?? '', $profiles) ? $_GET['profil'] : 'club';
?>
<main class="login">
  <div class="login-card">
    <section class="login-visual">
      <a class="login-brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="60" height="40"><span><?= e($clubName) ?></span></a>
      <div class="login-pitch">
        <span class="login-pill">Espace privé · Compétitions</span>
        <h1>Bienvenue sur le tatami</h1>
        <p>Choisissez votre profil, puis connectez-vous avec l'identifiant fourni par l'organisation.</p>
      </div>
      <div class="login-profiles" role="radiogroup" aria-label="Type de profil">
        <?php foreach ($profiles as $key => [$label, $desc, $ic]): ?>
          <button type="button" class="login-profile" role="radio" aria-checked="<?= $key === $current ? 'true' : 'false' ?>" data-profile="<?= e($key) ?>" data-label="<?= e($label) ?>">
            <span class="ic"><?= icon($ic) ?></span>
            <strong><?= e($label) ?></strong>
            <small><?= e($desc) ?></small>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="login-form">
      <div class="login-form-inner">
        <h2>Connexion</h2>
        <p class="muted login-for" data-profile-label>Profil : <strong><?= e($profiles[$current][0]) ?></strong></p>
        <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
        <form method="post" action="<?= e(url('/connexion')) ?>" class="stack" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="profil" value="<?= e($current) ?>" data-profile-input>
          <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="username" required value="<?= e(old('email')) ?>" placeholder="prenom.nom@club.fr" autofocus></div>
          <div class="field"><label for="password">Mot de passe</label>
            <div class="pw"><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••••••">
              <button type="button" class="pw-toggle" data-pw-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><?= icon('eye') ?></button></div>
            <span class="hint">Au moins 12 caractères, avec des lettres et des chiffres.</span></div>
          <button class="btn btn-lg btn-block" type="submit">Se connecter</button>
        </form>
        <p class="small muted login-help">Pas encore de compte ? Les comptes sont créés par l'organisation du JC Leforest.<br><a href="<?= e(url('/')) ?>">Retour au site</a></p>
        <?php if ($isDemoEnv): ?>
          <details class="login-demo"><summary>Environnement de démonstration — comptes fictifs</summary>
            <ul><?php foreach (\Database\Seeds\DemoSeeder::ACCOUNTS as [$mail, $pwd, $label]): ?><li><code><?= e($mail) ?></code> · <code><?= e($pwd) ?></code></li><?php endforeach; ?></ul></details>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
