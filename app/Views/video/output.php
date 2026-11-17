<?php /** Sortie programme d'un tapis : fenêtre capturée par OBS (« Capture de fenêtre ») pour le direct YouTube. @var array $t */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Programme · Tapis <?= (int) $t['number'] ?></title>
<style>
  html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
  video { width: 100vw; height: 100vh; object-fit: contain; display: block; background: #000; }
  .msg { position: fixed; inset: 0; display: grid; place-content: center; text-align: center; gap: .6rem; color: #fff; font: 600 18px/1.4 system-ui, sans-serif; padding: 24px; }
  .msg strong { font: italic 800 34px/1.1 system-ui, sans-serif; }
  .msg[hidden] { display: none; }
</style>
</head>
<body>
<video data-out muted autoplay playsinline></video>
<div class="msg" data-msg><strong>Tapis <?= (int) $t['number'] ?> · Sortie programme</strong><span>Ouvrez cette fenêtre depuis la régie vidéo (bouton « Sortie programme »).<br>Laissez la régie ouverte : cette fenêtre affiche son image.</span></div>
<script src="<?= e(asset('js/video-output.js')) ?>"></script>
</body>
</html>
