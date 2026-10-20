<header class="att-head"><div class="row"><h1>Accueil — choisir la compétition</h1><a class="btn btn-ghost btn-sm" style="--fg:#fff" href="<?= e(url(\App\Core\Auth::homePath())) ?>">Retour</a></div></header>
<main style="padding:16px">
<?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
<?php if (!$competitions): ?><div class="empty"><strong>Aucune compétition active</strong></div><?php endif; ?>
<ul class="att-list" style="padding:0"><?php foreach ($competitions as $c): ?><li class="att-item"><a href="<?= e(url('/accueil/' . $c['id'])) ?>" style="display:block;text-decoration:none;color:inherit"><strong><?= e($c['name']) ?></strong><div class="meta"><?= e(fr_date($c['event_date'], true)) ?></div></a></li><?php endforeach; ?></ul>
</main>
