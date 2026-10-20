<?php
/** @var array $c @var string $heading @var ?string $actions @var ?string $sub */
use App\Services\CompetitionService;
$base = '/admin/competitions/' . $c['id'];
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$adminTabs = [
    '' => 'Général', '/modifier' => 'Informations', '/parametres' => 'Paramètres', '/categories' => 'Catégories', '/durees' => 'Combats',
    '/bareme' => 'Barème', '/tapis' => 'Tapis & TV', '/contenu' => 'Programme', '/clubs-presents' => 'Clubs présents', '/documents-pdf' => 'PDF', '/journal' => 'Journal',
];
$inAdmin = isset($adminTabs[substr($path, strlen($base))]) && str_starts_with($path, $base);
?>
<div class="page-head">
  <div><p class="eyebrow"><?= e($c['name']) ?> · <?= e(CompetitionService::statusLabel($c['status'])) ?></p><h1><?= e($inAdmin ? 'Administration' : $heading) ?></h1><?php if (!empty($sub)): ?><p class="sub"><?= e($sub) ?></p><?php endif; ?></div>
  <?php if (!empty($actions)): ?><div class="btn-group"><?= $actions ?></div><?php endif; ?>
</div>
<?php if ($inAdmin): ?>
  <nav class="subnav" aria-label="Rubriques de l'administration"><?php foreach ($adminTabs as $p => $label): ?><a href="<?= e(url($base . $p)) ?>"<?= $path === $base . $p ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if ($c['status'] === 'archived'): ?><div class="alert alert-warning"><span>Compétition archivée : lecture seule.</span></div><?php endif; ?>
