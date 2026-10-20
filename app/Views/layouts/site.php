<?php
use App\Services\ContentService;
$nav = ['/competitions' => 'Compétitions', '/photos' => 'Photos', '/club' => 'Le club', '/taiso' => 'Taïso', '/partenaires' => 'Partenaires'];
$current = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' . $clubName : $clubName . ' · Compétitions de judo') ?></title>
<meta name="description" content="<?= e($description ?? 'Compétitions de judo du Judo Club Leforest : inscriptions des clubs, horaires, poules, résultats et photos.') ?>">
<meta name="theme-color" content="#f6f6f4">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="site">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="site-header">
  <div class="wrap">
    <a class="brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="54" height="36"><span><?= e(ContentService::site('club_full_name', $clubName)) ?></span></a>
    <button class="btn btn-ghost btn-sm nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle><?= icon('menu') ?> Menu</button>
    <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
      <?php foreach ($nav as $href => $label): ?><a href="<?= e(url($href)) ?>"<?= str_starts_with($current, $href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
      <a class="nav-mobile-only" href="<?= e(url($user ? \App\Core\Auth::homePath() : '/connexion')) ?>" style="display:none">Espace privé</a>
    </nav>
    <a class="btn btn-sm btn-espace" href="<?= e(url($user ? \App\Core\Auth::homePath() : '/connexion')) ?>"><?= $user ? 'Mon espace' : 'Espace privé' ?></a>
  </div>
</header>
<main id="contenu">
<?php if (!empty($flash)): ?><div class="wrap" style="padding-top:.5rem"><?php include BASE_PATH . '/app/Views/partials/flash.php'; ?></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="wrap row">
    <span>© <?= date('Y') ?> <?= e(ContentService::site('club_full_name', $clubName)) ?> · <?= e(ContentService::site('club_address')) ?></span>
    <span class="links">
      <?php if ($mail = ContentService::site('club_email')): ?><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php endif; ?>
      <?php if ($fb = ContentService::site('club_facebook')): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
      <?php if ($ig = ContentService::site('club_instagram')): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
      <a href="<?= e(url('/mentions-legales')) ?>">Mentions légales</a><a href="<?= e(url('/confidentialite')) ?>">Confidentialité</a>
    </span>
  </div>
</footer>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
