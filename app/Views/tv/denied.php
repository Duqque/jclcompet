<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Affichage indisponible</title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/tv.css')) ?>"></head>
<body class="tv"><main class="tv-denied"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="240" height="160"><h1>Tapis <?= (int) $number ?></h1><p><?= e($message) ?></p></main></body></html>
