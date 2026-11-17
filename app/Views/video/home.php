<?php
/**
 * Vidéo-arbitrage : 1. choix de la compétition, 2. choix du tapis (ouvre la régie sur l'ordinateur du tapis).
 * @var list<array> $competitions @var ?array $competition @var list<array> $tatamis
 */
use App\Services\CompetitionService;
?>
<?php if (!$competition): ?>
  <div class="page-head"><div><p class="eyebrow">Vidéo-arbitrage</p><h1>Ma compétition</h1></div></div>
  <p class="muted">Choisissez la compétition filmée aujourd'hui, puis ouvrez la régie du tapis sur l'ordinateur installé au bord de ce tapis.</p>
  <?php if (!$competitions): ?>
    <div class="empty"><strong>Aucune compétition en cours</strong>Les compétitions apparaissent ici le jour J, dès que l'organisation a ouvert les tapis.</div>
  <?php endif; ?>
  <div class="grid grid-3 cm-choices">
    <?php foreach ($competitions as $c): ?>
      <a class="card cm-choice" href="<?= e(url('/video?competition=' . (int) $c['id'])) ?>">
        <span class="badge<?= $c['status'] === 'in_progress' ? ' badge-red' : '' ?>"><?= e(CompetitionService::STATUSES[$c['status']] ?? $c['status']) ?></span>
        <strong><?= e($c['name']) ?></strong>
        <span class="small muted"><?= e(fr_date($c['event_date'], true)) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?> · <?= (int) $c['mats'] ?> tapis</span>
        <span class="cm-choice-go"><?= icon('arrow') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="page-head">
    <div><p class="eyebrow"><?= e($competition['name']) ?></p><h1>Régies vidéo</h1></div>
    <?php if (count($competitions) > 1): ?>
      <a class="btn btn-ghost" href="<?= e(url('/video?competition=0')) ?>"><?= icon('arrow-left') ?>Changer de compétition</a>
    <?php endif; ?>
  </div>
  <p class="muted">Une régie par tapis, ouverte dans Chrome ou Edge sur l'ordinateur du tapis : deux caméras dans l'axe (côté table et côté opposé), incrustation du combat en haut à gauche, retours vidéo, ralentis et zoom. La sortie programme est envoyée sur YouTube par OBS.</p>
  <div class="grid grid-3">
  <?php foreach ($tatamis as $t): ?>
    <section class="card<?= (int) $t['var_active'] ? ' cm-active' : '' ?>">
      <p class="eyebrow"><?= (int) $t['regie_online'] ? 'Régie ouverte' : 'Régie fermée' ?><?= $t['youtube_video_id'] ? ' · en direct sur YouTube' : '' ?></p>
      <h2 style="margin:.2rem 0 .4rem">Tapis <?= (int) $t['number'] ?></h2>
      <?php if ((int) $t['var_active']): ?><p class="small cm-others">Vidéo-arbitrage en cours</p><?php endif; ?>
      <div class="btn-group"><a class="btn btn-red btn-lg btn-display" href="<?= e(url('/video/tapis/' . $t['id'])) ?>">Ouvrir la régie</a></div>
    </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<section class="card" style="margin-top:1.4rem"><h2 style="font-size:1.1rem">Mon compte</h2><a class="btn btn-ghost" href="<?= e(url('/compte/mot-de-passe')) ?>">Changer mon mot de passe</a></section>
