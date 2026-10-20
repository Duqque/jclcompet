<div class="page-head"><div><p class="eyebrow">Paramètres</p><h1>Mes tapis</h1></div></div>
<p class="muted">Choisissez le tapis sur lequel vous saisissez. Un seul tapis est actif à la fois dans l'interface de saisie ; vous pouvez revenir ici à tout moment sans interrompre l'écran TV, qui continue d'afficher le combat en cours puis le suivant.</p>
<?php if (!$tatamis): ?><div class="empty"><strong>Aucun tapis affecté</strong>L'organisation doit vous affecter à un tapis (Administration › Tapis et écrans TV).</div><?php endif; ?>
<div class="grid grid-3">
<?php foreach ($tatamis as $t): ?>
  <section class="card<?= (int) $t['is_active_for_me'] ? ' cm-active' : '' ?>">
    <p class="eyebrow"><?= e($t['competition_name']) ?></p>
    <h2 style="margin:.2rem 0 .4rem">Tapis <?= (int) $t['number'] ?></h2>
    <p class="small muted"><?= (int) $t['done'] ?> combats validés · <?= (int) $t['remaining'] ?> restants</p>
    <div class="btn-group">
      <form method="post" action="<?= e(url('/commissaire/tapis/' . $t['id'] . '/activer')) ?>"><?= csrf_field() ?><button class="btn btn-red btn-lg btn-display" type="submit"><?= (int) $t['is_active_for_me'] ? 'Reprendre la saisie' : 'Saisir sur ce tapis' ?></button></form>
      <a class="btn btn-ghost" href="<?= e(url('/commissaire/tapis/' . $t['id'] . '/historique')) ?>">Combats précédents et poules</a>
    </div>
  </section>
<?php endforeach; ?>
</div>
<section class="card" style="margin-top:1.4rem"><h2 style="font-size:1.1rem">Mon compte</h2><a class="btn btn-ghost" href="<?= e(url('/compte/mot-de-passe')) ?>">Changer mon mot de passe</a></section>
