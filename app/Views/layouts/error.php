<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Erreur') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body style="background:var(--floor)">
<main style="min-height:100vh;display:grid;place-items:center;padding:24px"><?= $content ?></main>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
