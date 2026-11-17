<?php /** Téléphone / iPad utilisé comme caméra : image envoyée à la régie du tapis (WebRTC). @var array $cam @var array $config */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex">
<meta name="apple-mobile-web-app-capable" content="yes">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/camera.css')) ?>">
</head>
<body class="cam">
<video class="cam-video" data-video muted playsinline autoplay></video>
<header class="cam-bar">
  <span class="cam-dot" data-dot></span>
  <div><strong>Tapis <?= (int) $cam['tatami_number'] ?> · <?= e($config['slotLabel']) ?></strong><span data-status>Démarrage de la caméra…</span></div>
  <button type="button" class="cam-btn" data-flip aria-label="Changer de caméra">⟲</button>
</header>
<div class="cam-start" data-start>
  <p>Posez l'appareil dans l'axe du tapis, à l'horizontale, branché sur secteur.</p>
  <button type="button" data-go>Démarrer la caméra</button>
</div>
<p class="cam-turn" role="status">Tournez l'appareil à l'horizontale pour filmer tout le tapis.</p>
<?= json_script('cam-config', $config) ?>
<script src="<?= e(asset('js/camera.js')) ?>"></script>
</body>
</html>
