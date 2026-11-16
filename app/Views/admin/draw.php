<?php
/** Tirage au sort en direct. @var array $c @var ?array $generation @var list<array> $categories */
$base = '/admin/competitions/' . $c['id'];
$stateLabel = ['todo' => ['', 'À tirer'], 'validated' => ['badge-green', 'Tirage validé'], 'started' => ['badge-ink', 'Combats commencés']];
?>
<div class="draw" data-draw data-base="<?= e(url($base . '/tirage')) ?>">
  <header class="draw-head">
    <a class="draw-back" href="<?= e(url($base . '/poules')) ?>" aria-label="Retour aux poules"><?= icon('arrow-left') ?></a>
    <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="66" height="44">
    <div class="draw-title"><p class="eyebrow"><?= e($c['name']) ?></p><h1 data-draw-title>Tirage au sort</h1></div>
    <button type="button" class="icon-btn" data-draw-fullscreen aria-label="Plein écran"><?= icon('expand') ?></button>
  </header>

  <!-- 1. Choix de la catégorie -->
  <section class="draw-step" data-step="categories">
    <?php if (!$generation): ?>
      <div class="empty"><p>Générez d'abord les poules et tableaux (page Poules), puis revenez ici pour les tirages au sort.</p></div>
    <?php elseif (!$categories): ?>
      <div class="empty"><p>Aucun tableau à tirer : les catégories de 9 combattants et plus en formule « Tableaux officiels » apparaissent ici.</p></div>
    <?php else: ?>
      <p class="draw-lead">Choisissez la catégorie à tirer au sort.</p>
      <div class="draw-cats">
        <?php foreach ($categories as $cat): [$cls, $txt] = $stateLabel[$cat['state']]; ?>
          <button type="button" class="draw-cat" data-cat="<?= (int) $cat['id'] ?>" data-state="<?= e($cat['state']) ?>"<?= $cat['state'] === 'started' ? ' disabled' : '' ?>>
            <strong><?= e($cat['label']) ?></strong>
            <span><?= (int) $cat['count'] ?> judokas · tableau de <?= (int) $cat['size'] ?></span>
            <span class="badge <?= $cls ?>" data-cat-state><?= $txt ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 2. Liste des combattants -->
  <section class="draw-step" data-step="names" hidden>
    <div class="draw-bar">
      <button type="button" class="btn btn-ghost" data-draw-to="categories"><?= icon('arrow-left') ?>Catégories</button>
      <span class="draw-count" data-draw-count></span>
      <button type="button" class="btn btn-ghost" data-dialog-open="draw-seeds"><?= icon('flag') ?>Têtes de série</button>
      <button type="button" class="btn btn-red btn-lg" data-draw-run>Lancer le tirage</button>
    </div>
    <ol class="draw-names" data-draw-names></ol>
  </section>

  <!-- 3. Tirage animé -->
  <section class="draw-step" data-step="bracket" hidden>
    <div class="draw-bar">
      <span class="draw-count" data-draw-progress></span>
      <button type="button" class="btn btn-ghost" data-draw-again hidden>Refaire le tirage</button>
      <button type="button" class="btn btn-red btn-lg" data-draw-confirm hidden><?= icon('check') ?>Valider le tirage</button>
    </div>
    <div class="draw-stage">
      <ol class="draw-pool" data-draw-pool aria-label="Noms à placer"></ol>
      <div class="draw-bracket" data-draw-bracket></div>
    </div>
  </section>

  <dialog class="club-dialog draw-seeds" id="draw-seeds" aria-labelledby="draw-seeds-t">
    <div class="cd-head"><h2 id="draw-seeds-t">Têtes de série</h2><button type="button" class="icon-btn" data-dialog-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <p class="muted">Jusqu'à 8 têtes de série. En quart de finale : 1 contre 8, 2 contre 7, 3 contre 6, 4 contre 5. Les autres combattants sont placés au hasard.</p>
    <div class="draw-seed-list" data-seed-list></div>
    <div class="draw-bar"><button type="button" class="btn btn-ghost" data-seed-clear>Aucune tête de série</button><button type="button" class="btn btn-red" data-seed-save>Enregistrer</button></div>
  </dialog>
</div>
