<?php
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$role = $_GET['role'] ?? '';
$tabs = [
    ['/admin/utilisateurs', '', 'Tous'], ['/admin/utilisateurs', 'commissioner', 'Commissaires'], ['/admin/clubs', null, 'Clubs & entraîneurs'],
    ['/admin/utilisateurs', 'reception', 'Accueil'], ['/admin/utilisateurs', 'seller', 'Buvette'], ['/admin/utilisateurs', 'admin', 'Administrateurs'],
];
?>
<div class="page-head"><div><p class="eyebrow">Gestion</p><h1>Profils</h1><p class="sub">Comptes d'accès par rôle : chaque profil ne voit que son espace.</p></div><?= $profileActions ?? '' ?></div>
<nav class="subnav" aria-label="Types de profils"><?php foreach ($tabs as [$href, $r, $label]):
  $active = $r === null ? str_starts_with($path, '/admin/clubs') : ($path === '/admin/utilisateurs' && $role === $r); ?>
  <a href="<?= e(url($href, $r ? ['role' => $r] : [])) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
