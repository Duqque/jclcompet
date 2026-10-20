<?php
$logos = array_values(array_filter($partners, fn ($p) => $p['logo_document_id']));
$names = array_values(array_filter($partners, fn ($p) => !$p['logo_document_id']));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Classement des clubs · <?= e($c['name']) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/board.css')) ?>">
</head>
<body class="board" data-refresh="<?= (int) $refresh ?>" data-url="<?= e(url('/api/public/competitions/' . $c['slug'] . '/classement')) ?>">
<div class="board-shell">
  <header class="board-head">
    <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="JC Leforest" width="84" height="56">
    <div class="board-title">
      <p class="eyebrow">Classement général des clubs</p>
      <h1><?= e($c['name']) ?><?= $c['edition_label'] ? ' · ' . e($c['edition_label']) : '' ?></h1>
    </div>
    <div class="board-meta">
      <span class="board-sync" title="Mis à jour dès qu'un résultat est validé"><span class="dot"></span><span data-updated>—</span><small>· en direct</small></span>
      <button class="btn btn-ghost btn-sm" type="button" data-refresh-now><?= icon('refresh') ?>Actualiser</button>
      <button class="btn btn-sm" type="button" data-fullscreen aria-pressed="false"><?= icon('expand') ?><span>Plein écran</span></button>
    </div>
  </header>

  <main class="board-grid">
    <section class="board-col" aria-labelledby="by-points"><h2 id="by-points">Par points</h2><ol class="board-list" data-list="points"></ol></section>
    <section class="board-col" aria-labelledby="by-medals"><h2 id="by-medals">Par médailles</h2><ol class="board-list" data-list="medals"></ol></section>
  </main>

  <p class="board-note">Seuls les résultats validés par les commissaires sont comptés · mise à jour en direct · <a href="<?= e(url('/competitions/' . $c['slug'] . '/resultats')) ?>">Tous les résultats</a></p>
</div>

<?php if ($partners): ?>
<footer class="board-partners" aria-label="Partenaires du tournoi">
  <span class="lbl">Merci à nos partenaires</span>
  <div class="marquee"><div class="track">
    <?php for ($k = 0; $k < 2; $k++): ?>
      <ul<?= $k ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($logos as $p): ?><li class="logo"><img src="<?= e(url('/media/' . $p['logo_document_id'])) ?>" alt="<?= $k ? '' : e($p['name']) ?>" loading="lazy"></li><?php endforeach; ?>
        <?php foreach ($names as $p): ?><li class="name"><?= e($p['name']) ?></li><?php endforeach; ?>
      </ul>
    <?php endfor; ?>
  </div></div>
</footer>
<?php endif; ?>
<dialog class="podium" data-podium aria-label="Résultats de la poule">
  <div class="pod-head">
    <span class="pod-weight" data-pod-weight></span>
    <div class="pod-title"><p class="eyebrow">Résultats en direct · <span data-pod-pool></span></p><h2 data-pod-cat></h2></div>
    <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="JC Leforest" width="78" height="52">
  </div>
  <div class="pod-cards" data-pod-cards></div>
  <ul class="pod-rest" data-pod-rest hidden></ul>
  <button type="button" class="icon-btn pod-close" data-pod-close aria-label="Fermer"><?= icon('x') ?></button>
  <span class="pod-timer" data-pod-timer></span>
</dialog>
<?= json_script('board-data', $board) ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/board.js')) ?>"></script>
</body>
</html>
