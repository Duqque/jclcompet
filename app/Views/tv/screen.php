<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tapis <?= (int) $number ?> · Affichage</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tv.css')) ?>">
</head>
<body class="tv" data-api="<?= e($apiUrl) ?>" data-logo="<?= e($organizerLogo) ?>">
<main class="tv-screen" aria-live="off">
  <section class="tv-top">
    <img class="tv-top-logo" src="<?= e($organizerLogo) ?>" alt="">
    <div class="tv-top-text"><p class="tv-cat" data-cat>TAPIS <?= (int) $number ?></p><p class="tv-sub" data-sub><?= e($state['competition']['name']) ?></p></div>
    <div class="tv-clock-wrap"><span class="tv-clock-label" data-clock-label></span><span class="tv-clock" data-clock>–:––</span></div>
  </section>
  <?php foreach (['a', 'b'] as $side): ?>
  <section class="tv-row tv-row-<?= $side ?>" data-row="<?= $side ?>">
    <div class="tv-logo" data-logo-slot></div>
    <div class="tv-who"><p class="tv-name" data-name>&nbsp;</p><p class="tv-club" data-club>&nbsp;</p></div>
    <div class="tv-score" data-score><span class="d" data-d="ippon">0</span><span class="d" data-d="wazaari">0</span><span class="d" data-d="yuko">0</span></div>
    <div class="tv-victory" data-victory hidden>VICTOIRE</div>
    <div class="tv-shidos" data-shidos></div>
    <div class="tv-osae" data-osae hidden>
      <div class="tv-osae-head"><strong>OSAEKOMI</strong><span data-osae-sec>0</span></div>
      <div class="tv-osae-track"><span class="fill" data-osae-fill></span><i class="tick" data-tick="yuko"><b>Y</b></i><i class="tick" data-tick="wazaari"><b>W</b></i><i class="tick end" data-tick="ippon"><b>I</b></i></div>
    </div>
  </section>
  <?php endforeach; ?>
  <section class="tv-next" data-next></section>
</main>
<div class="tv-status" data-status hidden></div>
<button class="tv-fs" type="button" data-fullscreen>Plein écran</button>
<?= json_script('tv-initial', $state) ?>
<script src="<?= e(asset('js/tv.js')) ?>"></script>
</body>
</html>
