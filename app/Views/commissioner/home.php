<?php
/**
 * Paramètres du commissaire : 1. choix de la compétition, 2. choix du tapis.
 * @var list<array> $competitions @var ?array $competition @var list<array> $tatamis
 */
use App\Services\CompetitionService;
?>
<?php if (!$competition): ?>
  <div class="page-head"><div><p class="eyebrow">Paramètres</p><h1>Ma compétition</h1></div></div>
  <p class="muted">Choisissez la compétition sur laquelle vous êtes commissaire aujourd'hui, puis votre tapis.</p>
  <?php if (!$competitions): ?>
    <div class="empty"><strong>Aucune compétition en cours</strong>Les compétitions apparaissent ici le jour J, dès que l'organisation a ouvert les tapis.</div>
  <?php endif; ?>
  <div class="grid grid-3 cm-choices">
    <?php foreach ($competitions as $c): ?>
      <a class="card cm-choice" href="<?= e(url('/commissaire?competition=' . (int) $c['id'])) ?>">
        <span class="badge<?= $c['status'] === 'in_progress' ? ' badge-red' : '' ?>"><?= e(CompetitionService::STATUSES[$c['status']] ?? $c['status']) ?></span>
        <strong><?= e($c['name']) ?></strong>
        <span class="small muted"><?= e(fr_date($c['event_date'], true)) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?></span>
        <span class="small muted"><?= (int) $c['mats'] ?> tapis · <?= (int) $c['remaining'] ?> combats à disputer<?= (int) $c['mine'] ? ' · déjà mon tapis ici' : '' ?></span>
        <span class="cm-choice-go"><?= icon('arrow') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="page-head">
    <div><p class="eyebrow"><?= e($competition['name']) ?></p><h1>Mes tapis</h1></div>
    <?php if (count($competitions) > 1): ?>
      <a class="btn btn-ghost" href="<?= e(url('/commissaire?competition=0')) ?>"><?= icon('arrow-left') ?>Changer de compétition</a>
    <?php endif; ?>
  </div>
  <p class="muted">Choisissez le tapis sur lequel vous saisissez. Un seul tapis est actif à la fois dans l'interface de saisie ; vous pouvez revenir ici à tout moment sans interrompre l'écran TV, qui continue d'afficher le combat en cours puis le suivant.</p>
  <?php if (!$tatamis): ?><div class="empty"><strong>Aucun tapis ouvert</strong>L'organisation n'a pas encore ouvert de tapis pour cette compétition.</div><?php endif; ?>
  <div class="grid grid-3">
  <?php foreach ($tatamis as $t): ?>
    <section class="card<?= (int) $t['is_active_for_me'] ? ' cm-active' : '' ?>">
      <p class="eyebrow"><?= (int) $t['is_active_for_me'] ? 'Mon tapis actif' : ((int) $t['is_mine'] ? 'Tapis affecté' : 'Tapis disponible') ?></p>
      <h2 style="margin:.2rem 0 .4rem">Tapis <?= (int) $t['number'] ?></h2>
      <p class="small muted"><?= (int) $t['done'] ?> combats validés · <?= (int) $t['remaining'] ?> restants</p>
      <?php if (!empty($t['others'])): ?><p class="small cm-others">Aussi en saisie : <?= e($t['others']) ?></p><?php endif; ?>
      <div class="btn-group">
        <form method="post" action="<?= e(url('/commissaire/tapis/' . $t['id'] . '/activer')) ?>"><?= csrf_field() ?><button class="btn btn-red btn-lg btn-display" type="submit"><?= (int) $t['is_active_for_me'] ? 'Reprendre la saisie' : 'Saisir sur ce tapis' ?></button></form>
        <?php if ((int) $t['is_active_for_me'] || \App\Core\Auth::can('results.correct')): ?>
          <a class="btn btn-ghost" href="<?= e(url('/commissaire/tapis/' . $t['id'] . '/historique')) ?>">Combats précédents et poules</a>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<section class="card" style="margin-top:1.4rem"><h2 style="font-size:1.1rem">Mon compte</h2><a class="btn btn-ghost" href="<?= e(url('/compte/mot-de-passe')) ?>">Changer mon mot de passe</a></section>
