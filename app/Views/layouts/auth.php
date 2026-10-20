<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? 'Connexion') ?> · <?= e($clubName) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/backoffice.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-page">
<?php if (!empty($split)): ?>
  <?= $content ?>
<?php else: ?>
<main class="auth-shell">
  <a class="auth-brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="<?= e($clubName) ?> — retour au site" width="110" height="73"></a>
  <div class="auth-card"><?php include BASE_PATH . '/app/Views/partials/flash.php'; ?><?= $content ?></div>
</main>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/auth.js')) ?>"></script>
</body>
</html>
