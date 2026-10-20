<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex"><meta name="theme-color" content="#0b0d12">
<title><?= e($title ?? 'Accueil') ?> · <?= e($appName) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/backoffice.css')) ?>">
</head>
<body class="att-shell">
<?= $content ?>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
