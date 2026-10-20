<?php
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$link = fn (string $href, string $label, bool $exact = false) => '<a href="' . e(url($href)) . '"' . (($exact ? $path === $href : str_starts_with($path, $href)) ? ' aria-current="page"' : '') . '><span>' . e($label) . '</span></a>';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex">
<title><?= e($title ?? 'Espace club') ?> · <?= e($appName) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/backoffice.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<div class="bo">
  <aside class="bo-side" id="bo-side" aria-label="Navigation de l'espace club">
    <a class="bo-brand" href="<?= e(url('/espace-club')) ?>"><?php $logo = $club['logo_path']; $name = $club['name']; $size = 46; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?><span><?= e($club['short_name'] ?: $club['name']) ?><small>Espace club</small></span></a>
    <nav class="bo-nav">
      <?= $link('/espace-club', 'Mes compétitions', true) ?>
      <?php if (!empty($c)): $b = '/espace-club/competitions/' . $c['id']; ?>
        <div class="comp-context"><strong><?= e($c['name']) ?></strong><span><?= e(\App\Services\CompetitionService::statusLabel($c['status'])) ?></span></div>
        <?= $link($b, 'Mes inscriptions', true) ?>
        <?= $link($b . '/poules', 'Poules et horaires') ?>
        <?= $link($b . '/resultats', 'Résultats du club') ?>
      <?php endif; ?>
      <h2>Club</h2>
      <?= $link('/espace-club/profil', 'Informations du club') ?>
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><span>Site du tournoi ↗</span></a>
    </nav>
    <div class="bo-user"><strong><?= e($user['display_name']) ?></strong><a class="btn-link" href="<?= e(url('/compte/mot-de-passe')) ?>" style="color:var(--yellow)">Mot de passe</a> · <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="btn-link" type="submit">Déconnexion</button></form></div>
  </aside>
  <div class="bo-main">
    <?php if (!empty($user['is_demo'])): ?><div class="bo-demo">Compte de démonstration — données fictives</div><?php endif; ?>
    <div class="bo-top"><strong><?= e($club['name']) ?></strong><button class="btn btn-yellow btn-sm" type="button" data-side-toggle aria-controls="bo-side" aria-expanded="false">Menu</button></div>
    <main class="bo-content" id="contenu"><?php include BASE_PATH . '/app/Views/partials/flash.php'; ?><?= $content ?></main>
  </div>
</div>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/backoffice.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
