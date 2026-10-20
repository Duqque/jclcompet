<?php if ($c): $heading = 'Journal'; $actions = '<a class="btn btn-ghost" href="?verifier=1">Vérifier l\'intégrité du journal</a>'; include __DIR__ . '/_head.php'; else: ?>
<div class="page-head"><div><p class="eyebrow">Organisation</p><h1>Journal d'activité</h1></div><a class="btn btn-ghost" href="?verifier=1">Vérifier l'intégrité du journal</a></div><?php endif; ?>
<?php if ($chain): ?><div class="alert alert-<?= $chain['ok'] ? 'success' : 'error' ?>"><?= $chain['ok'] ? 'Journal intègre : ' . (int) $chain['checked'] . ' entrées vérifiées, chaîne de hachage valide.' : 'Anomalie détectée à l\'entrée n° ' . (int) $chain['broken_at'] . ' : le journal a été modifié en dehors de l\'application.' ?></div><?php endif; ?>
<p class="small muted">Journal en ajout seul, non modifiable depuis l'interface. Chaque entrée est chaînée à la précédente par un hachage : toute modification directe en base est détectable.</p>
<form method="get" class="filters"><div class="field wide"><label for="q">Recherche</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Action (ex. bout.correct), utilisateur, motif"></div><button class="btn btn-ghost" type="submit">Rechercher</button></form>
<p class="small muted"><?= (int) $total ?> entrée(s)</p>
<?php include __DIR__ . '/_audit_rows.php'; $query = ['q' => $q]; include BASE_PATH . '/app/Views/partials/pagination.php'; ?>
