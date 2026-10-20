<?php /** @var array $c @var string $heading @var ?string $actions */ ?>
<div class="page-head"><div><p class="eyebrow"><a href="<?= e(url('/admin/competitions/' . $c['id'])) ?>"><?= e($c['name']) ?></a></p><h1><?= e($heading) ?></h1></div><?php if (!empty($actions)): ?><div class="btn-group"><?= $actions ?></div><?php endif; ?></div>
<?php if ($c['status'] === 'archived'): ?><div class="alert alert-warning">Compétition archivée : lecture seule.</div><?php endif; ?>
