<?php /** Téléphone / iPad : saisie du code caméra donné par la régie vidéo. */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex">
<title>Caméra · JCL Compét</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/camera.css')) ?>">
</head>
<body class="cam cam-code">
<main class="cam-box">
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="96" height="64">
  <h1>Caméra de tapis</h1>
  <p>Saisissez le code à 6 caractères affiché par la régie vidéo. Le téléphone doit être sur le même Wi-Fi que l'ordinateur du tapis.</p>
  <form method="post" action="<?= e(url('/camera')) ?>">
    <label class="sr-only" for="code">Code caméra</label>
    <input id="code" name="code" inputmode="text" autocomplete="off" autocapitalize="characters" maxlength="6" placeholder="ABC123" required pattern="[A-Za-z0-9]{6}">
    <button type="submit">Connecter la caméra</button>
  </form>
</main>
</body>
</html>
