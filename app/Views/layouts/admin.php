<?php
use App\Controllers\Admin\AdminController;
use App\Core\Database;
use App\Services\CompetitionService;
use App\Services\NotificationService;
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$nc = $competition ?? AdminController::current();
$base = $nc ? '/admin/competitions/' . $nc['id'] : null;
$adminPaths = $base ? array_map(fn ($p) => $base . $p, ['', '/modifier', '/parametres', '/categories', '/durees', '/bareme', '/tapis', '/contenu', '/clubs-presents', '/documents-pdf', '/journal']) : [];
$isAdminTab = in_array($path, $adminPaths, true) || ($base && str_starts_with($path, $base . '/pdf'));
$link = function (string $href, string $label, string $ic, bool $active, ?string $extra = null) {
    return '<a href="' . e(url($href)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span>' . ($extra ?? '') . '</a>';
};
$starts = fn (string $p) => $path === $p || str_starts_with($path, $p . '/');
$regCount = $nc ? (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')", [$nc['id']]) : 0;
$pending = $nc ? (int) Database::value('SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND needs_validation = 1', [$nc['id']]) : 0;
$toValidate = $nc ? (int) Database::value("SELECT COUNT(*) FROM bouts WHERE competition_id = ? AND status = 'finished'", [$nc['id']]) : 0;
$comps = Database::all("SELECT id, name, edition_label, event_date, status FROM competitions ORDER BY status = 'archived', event_date DESC LIMIT 30");
$unread = $user ? NotificationService::unreadCount($user) : 0;
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
<div class="bo"><div class="bo-frame">
  <aside class="bo-side" id="bo-side" aria-label="Navigation de l'administration">
    <a class="bo-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="44" height="29"><span><?= e($appName) ?><small>Administration</small></span></a>
    <form class="bo-switch" method="get" action="<?= e(url('/admin/basculer')) ?>">
      <label for="sw">Compétition</label>
      <select id="sw" name="id" data-autosubmit>
        <?php foreach ($comps as $co): ?><option value="<?= (int) $co['id'] ?>"<?= $nc && (int) $nc['id'] === (int) $co['id'] ? ' selected' : '' ?>><?= e($co['name'] . ($co['edition_label'] ? ' · ' . $co['edition_label'] : ' · ' . date('Y', strtotime($co['event_date'])))) ?></option><?php endforeach; ?>
        <option value="0">Toutes les compétitions…</option>
      </select>
    </form>
    <nav class="bo-nav">
      <?php if ($base): ?>
        <h2>Compétition</h2>
        <?= $link($base . '/inscriptions', 'Inscrits', 'users', $starts($base . '/inscriptions'), $pending ? '<span class="count">' . $pending . '</span>' : '<span class="step">' . $regCount . '</span>') ?>
        <?= $link($base . '/poules', 'Poules', 'grid', $starts($base . '/poules')) ?>
        <?= $link($base . '/combats', 'Combats', 'timer', $starts($base . '/combats'), $toValidate ? '<span class="count">' . $toValidate . '</span>' : null) ?>
        <?= $link($base . '/resultats', 'Résultats', 'trophy', $starts($base . '/resultats')) ?>
        <?= $link($base . '/classements', 'Classements', 'chart', $starts($base . '/classements')) ?>
      <?php endif; ?>
      <h2>Gestion</h2>
      <?php if ($base): ?><?= $link($base, 'Administration', 'sliders', $isAdminTab) ?><?php endif; ?>
      <?= $link('/admin/utilisateurs', 'Profils', 'id', $starts('/admin/utilisateurs') || $starts('/admin/clubs'), $unread ? '<span class="count">' . $unread . '</span>' : null) ?>
      <?php if ($base): ?><?= $link($base . '/ventes', 'Boutique', 'bag', $starts($base . '/ventes')) ?><?php endif; ?>
      <?= $link('/admin/site', 'Site & photos', 'image', $starts('/admin/site')) ?>
      <?php if ($nc): ?>
        <h2>Jour J</h2>
        <?= $link('/accueil/' . $nc['id'], 'Présences', 'clipboard', false) ?>
        <?= $link('/commissaire', 'Saisie des combats', 'whistle', false) ?>
        <?= $link('/caisse/' . $nc['id'], 'Caisse', 'bag', false) ?>
      <?php endif; ?>
    </nav>
    <div class="bo-user">
      <span class="avatar-mono"><?= e(initials($user['display_name'])) ?></span>
      <span class="who"><strong><?= e($user['display_name']) ?></strong><span><?= e($user['email']) ?></span></span>
      <a class="icon-btn" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="Voir le site" aria-label="Voir le site"><?= icon('globe') ?></a>
      <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button class="icon-btn" type="submit" title="Déconnexion" aria-label="Déconnexion"><?= icon('logout') ?></button></form>
    </div>
  </aside>
  <div class="bo-main">
    <?php if (!empty($user['is_demo'])): ?><div class="bo-demo">Compte de démonstration — données fictives, à désactiver avant toute utilisation réelle</div><?php endif; ?>
    <div class="bo-top"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="45" height="30"><strong><?= e($nc['name'] ?? $appName) ?></strong><button class="btn btn-ghost btn-sm" type="button" data-side-toggle aria-controls="bo-side" aria-expanded="false"><?= icon('menu') ?> Menu</button></div>
    <main class="bo-content" id="contenu">
      <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div></div>
<div class="toasts" aria-live="polite" data-toasts></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/backoffice.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
</body>
</html>
