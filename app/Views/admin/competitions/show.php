<?php use App\Services\CategoryService; use App\Services\CompetitionService; $s = $stats; $base = '/admin/competitions/' . $c['id']; $archived = $c['status'] === 'archived'; ?>
<div class="page-head">
  <div><p class="eyebrow"><?= e(fr_date($c['event_date'], true)) ?> · <?= e($c['city']) ?></p><h1><?= e($c['name']) ?></h1></div>
  <div class="btn-group">
    <?php if ($c['status'] !== 'draft'): ?><a class="btn btn-ghost" href="<?= e(url('/competitions/' . $c['slug'])) ?>" target="_blank">Page publique ↗</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('/classement/' . $c['slug'])) ?>" target="_blank">Classement public ↗</a>
  </div>
</div>
<?php if ($archived): ?><div class="alert alert-warning"><strong>Compétition archivée</strong> le <?= e(short_datetime($c['archived_at'])) ?> : toutes les données sont en lecture seule.</div><?php endif; ?>
<?php foreach ($issues as $i): ?><div class="alert alert-<?= $i['level'] === 'error' ? 'error' : 'warning' ?> small"><?= e($i['message']) ?> <a href="<?= e(url($base . '/durees')) ?>">Durées de combat</a></div><?php endforeach; ?>

<section class="card" style="margin-bottom:1.2rem">
  <div class="card-head"><h2 style="margin:0;font-size:1.2rem">Statut : <?= e(CompetitionService::statusLabel($c['status'])) ?></h2></div>
  <?php if (!$archived): ?>
  <form method="post" action="<?= e(url($base . '/statut')) ?>" class="btn-group">
    <?= csrf_field() ?>
    <?php foreach (CompetitionService::STATUSES as $k => $label): if ($k === 'archived') continue; ?>
      <button class="btn btn-sm <?= $c['status'] === $k ? 'btn-red' : 'btn-ghost' ?>" name="status" value="<?= e($k) ?>" type="submit"<?= $c['status'] === $k ? ' aria-pressed="true"' : '' ?>><?= e($label) ?></button>
    <?php endforeach; ?>
  </form>
  <?php endif; ?>
  <div class="btn-group" style="margin-top:.8rem">
    <?php if ($archived): ?>
      <form method="post" action="<?= e(url($base . '/rouvrir')) ?>" data-confirm="Rouvrir la compétition archivée ? Elle redeviendra modifiable." data-reason data-reason-label="Motif de la réouverture"><?= csrf_field() ?><button class="btn btn-yellow btn-sm" type="submit">Rouvrir la compétition</button></form>
    <?php else: ?>
      <form method="post" action="<?= e(url($base . '/archiver')) ?>" data-confirm="Archiver la compétition ? Résultats, poules et journal sont conservés ; tout passe en lecture seule et les écrans TV sont désactivés." data-danger><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Archiver</button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url($base . '/dupliquer')) ?>" data-confirm="Préparer l'édition suivante ? Paramètres, catégories (années +1), durées, barème, buvette et contenus seront copiés dans une nouvelle compétition en brouillon."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Préparer l'édition suivante</button></form>
  </div>
</section>

<div class="stats">
  <div class="stat accent"><strong><?= $s['clubs'] ?></strong><span>clubs inscrits</span></div>
  <div class="stat"><strong><?= $s['registered'] ?></strong><span>athlètes inscrits</span></div>
  <div class="stat"><strong><?= $s['present'] ?></strong><span>présents</span></div>
  <div class="stat"><strong><?= $s['absent'] ?></strong><span>absents / forfaits</span></div>
  <div class="stat"><strong><?= $s['to_check'] ?></strong><span>à vérifier</span></div>
  <?php if ($s['pending_validation']): ?><div class="stat warn"><strong><?= $s['pending_validation'] ?></strong><span>à valider (tardives)</span></div><?php endif; ?>
  <div class="stat"><strong><?= $s['pools'] ?></strong><span>poules</span></div>
  <div class="stat"><strong><?= $s['bouts_done'] ?>/<?= $s['bouts_total'] ?></strong><span>combats terminés</span></div>
</div>

<div class="grid grid-2" style="margin-bottom:1.2rem">
  <section class="card"><h2 style="font-size:1.15rem">Accès rapides</h2>
    <div class="btn-group">
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/inscriptions')) ?>">Inscriptions</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/accueil/' . $c['id'])) ?>">Contrôle des présences</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/poules')) ?>">Générer / gérer les poules</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/tapis')) ?>">Tapis et écrans TV</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/commissaire')) ?>">Saisie des combats</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/combats')) ?>">Résultats</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/classements')) ?>">Classements</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/documents-pdf')) ?>">PDF</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/ventes')) ?>">Buvette</a>
    </div>
  </section>
  <section class="card"><h2 style="font-size:1.15rem">Inscrits par catégorie</h2>
    <?php if (!$byCategory): ?><p class="muted">Aucune inscription pour l'instant.</p><?php else: ?>
    <table class="table-compact"><tbody><?php foreach ($byCategory as $b): ?><tr><td><?= e(CategoryService::label($b, $b['sex'])) ?><?= $b['format'] === 'animation' ? ' <span class="badge badge-yellow">animation</span>' : '' ?></td><td class="num"><?= (int) $b['n'] ?> inscrits</td><td class="num"><?= (int) $b['present'] ?> présents</td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
</div>
<section class="card" style="margin-bottom:1.2rem"><div class="card-head"><h2 style="font-size:1.15rem;margin:0">Avancement par tapis</h2><a class="small" href="<?= e(url($base . '/tapis')) ?>">Gérer les écrans TV</a></div><?php include dirname(__DIR__) . '/_progress.php'; ?></section>
<section class="card"><div class="card-head"><h2 style="font-size:1.15rem;margin:0">Dernières actions</h2><a class="small" href="<?= e(url($base . '/journal')) ?>">Journal complet</a></div><?php $rows = $recent; include dirname(__DIR__) . '/_audit_rows.php'; ?></section>
