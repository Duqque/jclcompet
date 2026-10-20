<?php
use App\Services\ContentService;
$nav = [
    '/' => 'Accueil', '/competitions' => 'Compétitions', '/club' => 'Notre club', '/taiso' => 'Taïso', '/partenaires' => 'Partenaires',
];
$current = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' . $clubName : $clubName . ' · Compétitions de judo') ?></title>
<meta name="description" content="<?= e($description ?? 'Compétitions de judo du Judo Club Leforest : inscriptions des clubs, horaires, poules et résultats en direct.') ?>">
<meta name="theme-color" content="#c21a2c">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="site-header">
  <div class="wrap">
    <a class="brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="70" height="46">
      <span><?= e(ContentService::site('club_full_name', $clubName)) ?><small><?= e(ContentService::site('club_tagline')) ?></small></span>
    </a>
    <button class="btn btn-ghost btn-sm nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>Menu</button>
    <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
      <?php foreach ($nav as $href => $label): ?>
        <a href="<?= e(url($href)) ?>"<?= ($href === '/' ? $current === '/' : str_starts_with($current, $href)) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <a class="btn btn-red btn-sm" href="<?= e(url(\App\Core\Auth::homePath())) ?>">Mon espace</a>
      <?php else: ?>
        <a class="btn btn-red btn-sm" href="<?= e(url('/connexion')) ?>">Espace clubs</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="contenu">
<?php if (!empty($flash)): ?><div class="wrap" style="padding-top:1rem"><?php include BASE_PATH . '/app/Views/partials/flash.php'; ?></div><?php endif; ?>
<?= $content ?>
</main>
<div class="stripe" aria-hidden="true"></div>
<footer class="site-footer">
  <div class="wrap">
    <div class="cols">
      <div>
        <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="<?= e($clubName) ?>" width="120" height="80" style="width:120px;margin-bottom:1rem">
        <p><?= e(ContentService::site('club_full_name', $clubName)) ?><br><?= e(ContentService::site('club_address')) ?></p>
      </div>
      <div>
        <h3>Contact</h3>
        <ul>
          <?php if ($phone = ContentService::site('club_phone')): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($mail = ContentService::site('club_email')): ?><li><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></li><?php endif; ?>
          <?php if ($fb = ContentService::site('club_facebook')): ?><li><a href="<?= e($fb) ?>" target="_blank" rel="noopener">Facebook</a></li><?php endif; ?>
          <?php if ($ig = ContentService::site('club_instagram')): ?><li><a href="<?= e($ig) ?>" target="_blank" rel="noopener">Instagram</a></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h3>Accès rapides</h3>
        <ul>
          <li><a href="<?= e(url('/competitions')) ?>">Compétitions et résultats</a></li>
          <li><a href="<?= e(url('/connexion')) ?>">Espace clubs et organisation</a></li>
          <li><a href="<?= e(url('/club')) ?>">Horaires des cours</a></li>
          <li><a href="<?= e(url('/taiso')) ?>">Taïso</a></li>
        </ul>
      </div>
    </div>
    <div class="legal">
      <span>© <?= date('Y') ?> <?= e(ContentService::site('club_full_name', $clubName)) ?></span>
      <span><a href="<?= e(url('/mentions-legales')) ?>">Mentions légales</a> · <a href="<?= e(url('/confidentialite')) ?>">Confidentialité</a></span>
    </div>
  </div>
</footer>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
