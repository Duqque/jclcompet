<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex">
<title><?= e($title ?? 'Commissaire') ?> · <?= e($appName) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/backoffice.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/commissioner.css')) ?>">
</head>
<body class="cm">
<?php if (empty($fullApp)): ?>
<header class="cm-bar">
  <a class="cm-brand" href="<?= e(url('/commissaire')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="54" height="36"><span>Commissaire sportif</span></a>
  <span class="cm-user"><?= e($user['display_name']) ?></span>
  <?php if (\App\Core\Auth::hasRole('admin')): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin')) ?>">Administration</a><?php endif; ?>
  <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Déconnexion</button></form>
</header>
<main class="cm-page"><?php include BASE_PATH . '/app/Views/partials/flash.php'; ?><?= $content ?></main>
<?php else: ?><?= $content ?><?php endif; ?>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
