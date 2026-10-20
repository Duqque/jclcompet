<?php use App\Services\CompetitionService; ?>
<div class="page-head">
  <div><p class="eyebrow">Administration</p><h1>Tableau de bord</h1></div>
  <div class="btn-group"><a class="btn btn-red" href="<?= e(url('/admin/competitions/nouvelle')) ?>">Créer une compétition</a><a class="btn btn-ghost" href="<?= e(url('/admin/clubs')) ?>">Clubs</a></div>
</div>
<?php if ($current): $s = $stats; ?>
  <section class="card" style="margin-bottom:1.4rem">
    <div class="card-head"><div><p class="eyebrow"><?= e(CompetitionService::statusLabel($current['status'])) ?> · <?= e(fr_date($current['event_date'], true)) ?></p><h2 style="margin:0"><?= e($current['name']) ?></h2></div>
      <div class="btn-group"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/competitions/' . $current['id'])) ?>">Ouvrir</a><a class="btn btn-ghost btn-sm" href="<?= e(url('/accueil/' . $current['id'])) ?>">Présences</a><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/competitions/' . $current['id'] . '/poules')) ?>">Poules</a><a class="btn btn-ghost btn-sm" href="<?= e(url('/commissaire')) ?>">Saisie</a></div></div>
    <div class="stats">
      <div class="stat accent"><strong><?= $s['clubs'] ?></strong><span>clubs inscrits</span></div>
      <div class="stat"><strong><?= $s['registered'] ?></strong><span>athlètes inscrits</span></div>
      <div class="stat"><strong><?= $s['present'] ?></strong><span>présents</span></div>
      <div class="stat <?= $s['absent'] ? 'warn' : '' ?>"><strong><?= $s['absent'] ?></strong><span>forfaits / absents</span></div>
      <div class="stat"><strong><?= $s['pools'] ?></strong><span>poules générées</span></div>
      <div class="stat"><strong><?= $s['bouts_done'] ?></strong><span>combats terminés</span></div>
      <div class="stat"><strong><?= $s['bouts_remaining'] ?></strong><span>combats restants</span></div>
      <?php if ($s['bouts_awaiting']): ?><div class="stat warn"><strong><?= $s['bouts_awaiting'] ?></strong><span>à valider</span></div><?php endif; ?>
    </div>
    <?php if ($progress): ?><h3>Avancement par tapis</h3><?php include __DIR__ . '/_progress.php'; ?><?php endif; ?>
  </section>
<?php endif; ?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2 style="font-size:1.2rem;margin:0">Dernières actions</h2><?php if ($current): ?><a class="small" href="<?= e(url('/admin/competitions/' . $current['id'] . '/journal')) ?>">Tout le journal</a><?php endif; ?></div>
    <?php $rows = $recent ?? []; include __DIR__ . '/_audit_rows.php'; ?>
  </section>
  <div class="stack">
    <section class="card">
      <div class="card-head"><h2 style="font-size:1.2rem;margin:0">Notifications</h2>
        <?php if ($notifications): ?><form method="post" action="<?= e(url('/admin/notifications/lues')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Tout marquer comme lu</button></form><?php endif; ?></div>
      <?php if (!$notifications): ?><p class="muted">Aucune notification.</p><?php else: ?>
        <ul style="list-style:none;padding:0;margin:0"><?php foreach ($notifications as $n): ?><li style="padding:.4rem 0;border-bottom:1px solid var(--line)<?= $n['read_at'] ? ';opacity:.6' : '' ?>"><?php if ($n['link']): ?><a href="<?= e(url($n['link'])) ?>"><?= e($n['message']) ?></a><?php else: ?><?= e($n['message']) ?><?php endif; ?><br><span class="small muted"><?= e(short_datetime($n['created_at'])) ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2 style="font-size:1.2rem">Compétitions</h2>
      <ul style="list-style:none;padding:0;margin:0"><?php foreach ($competitions as $c): ?><li style="display:flex;justify-content:space-between;gap:.5rem;padding:.4rem 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('/admin/competitions/' . $c['id'])) ?>"><?= e($c['name']) ?></a><span class="small muted nowrap"><?= e(date('d/m/Y', strtotime($c['event_date']))) ?></span></li><?php endforeach; ?></ul>
      <p class="small muted" style="margin:.8rem 0 0"><?= $counts['clubs'] ?> clubs · <?= $counts['users'] ?> comptes actifs · <?= $counts['competitions'] ?> compétitions</p>
    </section>
  </div>
</div>
