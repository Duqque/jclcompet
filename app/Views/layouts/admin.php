<?php
use App\Core\Auth;
use App\Services\CompetitionService;
use App\Services\NotificationService;
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$is = fn (string $href, bool $exact = false) => $exact ? $path === $href : ($path === $href || str_starts_with($path, $href . '/'));
$link = function (string $href, string $label, bool $exact = false, ?string $count = null) use ($is) {
    return '<a href="' . e(url($href)) . '"' . ($is($href, $exact) ? ' aria-current="page"' : '') . '><span>' . e($label) . '</span>' . ($count !== null ? '<span class="count">' . e($count) . '</span>' : '') . '</a>';
};
$unread = $user ? NotificationService::unreadCount($user) : 0;
$cc = $competition ?? null;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Administration') ?> · <?= e($appName) ?></title>
<link rel="icon" href="<?= e(asset('img/logo-jcl.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/backoffice.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<div class="bo">
  <aside class="bo-side" id="bo-side" aria-label="Navigation de l'administration">
    <a class="bo-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="58" height="38"><span><?= e($appName) ?><small>Administration</small></span></a>
    <nav class="bo-nav">
      <?= $link('/admin', 'Tableau de bord', true, $unread ? (string) $unread : null) ?>
      <?= $link('/admin/competitions', 'Compétitions', true) ?>
      <?php if ($cc): $base = '/admin/competitions/' . $cc['id']; ?>
        <div class="comp-context"><strong><?= e($cc['name']) ?></strong><span><?= e(CompetitionService::statusLabel($cc['status'])) ?></span></div>
        <?= $link($base, 'Vue d\'ensemble', true) ?>
        <h2>Préparation</h2>
        <?= $link($base . '/modifier', 'Informations') ?>
        <?= $link($base . '/parametres', 'Paramètres sportifs') ?>
        <?= $link($base . '/categories', 'Catégories') ?>
        <?= $link($base . '/durees', 'Durées de combat') ?>
        <?= $link($base . '/tapis', 'Tapis et écrans TV') ?>
        <?= $link($base . '/contenu', 'Programme (contenus)') ?>
        <?= $link($base . '/clubs-presents', 'Clubs présents') ?>
        <h2>Inscriptions et jour J</h2>
        <?= $link($base . '/inscriptions', 'Inscriptions') ?>
        <?= $link('/accueil/' . $cc['id'], 'Contrôle des présences') ?>
        <?= $link($base . '/poules', 'Poules et tapis') ?>
        <?= $link($base . '/combats', 'Combats et résultats') ?>
        <?= $link('/commissaire', 'Saisie des combats') ?>
        <?= $link($base . '/classements', 'Classements') ?>
        <?= $link($base . '/bareme', 'Barème de points') ?>
        <h2>Gestion</h2>
        <?= $link($base . '/ventes', 'Buvette et boutique') ?>
        <?= $link($base . '/documents-pdf', 'Documents PDF') ?>
        <?= $link($base . '/journal', 'Journal de la compétition') ?>
      <?php endif; ?>
      <h2>Organisation</h2>
      <?= $link('/admin/clubs', 'Clubs') ?>
      <?= $link('/admin/utilisateurs', 'Comptes') ?>
      <?= $link('/admin/site', 'Site vitrine') ?>
      <?= $link('/admin/journal', 'Journal d\'activité', true) ?>
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><span>Voir le site ↗</span></a>
    </nav>
    <div class="bo-user">
      <strong><?= e($user['display_name']) ?></strong><?= e($user['role_name']) ?><br>
      <a class="btn-link" href="<?= e(url('/compte/mot-de-passe')) ?>" style="color:var(--yellow)">Mot de passe</a> ·
      <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="btn-link" type="submit">Déconnexion</button></form>
    </div>
  </aside>
  <div class="bo-main">
    <?php if (!empty($user['is_demo'])): ?><div class="bo-demo">Compte de démonstration — données fictives, à supprimer avant toute utilisation réelle</div><?php endif; ?>
    <div class="bo-top"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="52" height="34"><strong><?= e($appName) ?></strong><button class="btn btn-yellow btn-sm" type="button" data-side-toggle aria-controls="bo-side" aria-expanded="false">Menu</button></div>
    <main class="bo-content" id="contenu">
      <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/backoffice.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
