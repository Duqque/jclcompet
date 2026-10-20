<?php use App\Services\CompetitionService; ?>
<div class="page-head"><div><p class="eyebrow"><?= e($club['name']) ?></p><h1>Mes compétitions</h1></div></div>
<?php if ($notifications): ?><section class="card" style="margin-bottom:1rem"><h2 style="font-size:1.05rem">Messages de l'organisation</h2><ul style="margin:0;padding-left:1.1rem"><?php foreach ($notifications as $n): ?><li><?= e($n['message']) ?> <span class="small muted"><?= e(short_datetime($n['created_at'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if (!$competitions): ?><div class="empty"><strong>Aucune compétition publiée</strong>Les compétitions ouvertes aux inscriptions apparaîtront ici.</div><?php endif; ?>
<div class="grid grid-2">
<?php foreach ($competitions as $c): ?>
  <article class="card">
    <p class="eyebrow"><?= e(fr_date($c['event_date'], true)) ?> · <?= e($c['city']) ?></p>
    <h2 style="font-size:1.25rem;margin-bottom:.4rem"><?= e($c['name']) ?></h2>
    <p class="small"><span class="badge <?= $c['window_open'] ? 'badge-green' : '' ?>"><?= $c['window_open'] ? 'Inscriptions ouvertes' : e(CompetitionService::statusLabel($c['status'])) ?></span>
      <?= $c['registration_closes_at'] ? ' Date limite : ' . e(fr_datetime($c['registration_closes_at'])) : '' ?></p>
    <p><strong><?= (int) $c['my_regs'] ?></strong> judoka(s) inscrit(s)</p>
    <div class="btn-group"><a class="btn btn-red" href="<?= e(url('/espace-club/competitions/' . $c['id'])) ?>"><?= $c['window_open'] ? 'Inscrire mes judokas' : 'Voir mes inscriptions' ?></a>
      <a class="btn btn-ghost" href="<?= e(url('/espace-club/competitions/' . $c['id'] . '/poules')) ?>">Poules</a>
      <a class="btn btn-ghost" href="<?= e(url('/espace-club/competitions/' . $c['id'] . '/resultats')) ?>">Résultats</a></div>
  </article>
<?php endforeach; ?>
</div>
