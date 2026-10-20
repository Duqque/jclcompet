<p class="eyebrow">Espace privé</p>
<h1 style="font-size:1.7rem">Connexion</h1>
<p class="muted">Clubs et entraîneurs, organisation, accueil, commissaires sportifs et buvette.</p>
<form method="post" action="<?= e(url('/connexion')) ?>" class="stack" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="username" required value="<?= e(old('email')) ?>" autofocus></div>
  <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
  <button class="btn btn-red btn-lg btn-block btn-display" type="submit">Se connecter</button>
</form>
<p class="small muted" style="margin-top:1.2rem">Votre club n'a pas encore de compte ? Les comptes clubs sont créés par l'organisation : contactez le club organisateur.</p>
<?php if ($isDemoEnv): ?>
<details class="alert alert-info small" style="margin-top:1rem"><summary><strong>Environnement de démonstration</strong> — comptes fictifs</summary>
<ul style="margin:.5rem 0 0;padding-left:1.1rem">
  <?php foreach (\Database\Seeds\DemoSeeder::ACCOUNTS as [$mail, $pwd, $label]): ?><li><code><?= e($mail) ?></code> / <code><?= e($pwd) ?></code> — <?= e($label) ?></li><?php endforeach; ?>
</ul></details>
<?php endif; ?>
