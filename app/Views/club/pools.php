<?php $b = '/espace-club/competitions/' . $c['id']; $vq = fn (bool $allPools) => '?' . http_build_query(array_filter(['vue' => $allPools ? 'toutes' : null, 'q' => $q])); ?>
<div class="page-head" data-live-stamp="<?= e(url('/api/public/competitions/' . $c['slug'] . '/classement/etat')) ?>">
  <div><p class="eyebrow"><?= e($c['name']) ?></p><h1>Poules en direct</h1><p class="muted" style="margin:.2rem 0 0"><span class="live-dot"></span>Mise à jour automatique dès qu'un résultat est validé</p></div>
  <a class="btn btn-ghost" href="<?= e(url('/competitions/' . $c['slug'])) ?>#horaires" target="_blank" rel="noopener">Horaires prévisionnels ↗</a>
</div>
<?php if (!$published): ?>
  <div class="empty"><strong>Poules non publiées</strong>Les poules seront visibles ici dès leur publication par l'organisation, le jour de la compétition.</div>
<?php else: ?>
  <form method="get" class="toolbar" role="search">
    <?php if ($all): ?><input type="hidden" name="vue" value="toutes"><?php endif; ?>
    <label class="search"><?= icon('search') ?><span class="sr-only">Retrouver une poule</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom d'un judoka ou n° de poule…"></label>
    <span style="flex:1"></span>
    <div class="segmented" role="group" aria-label="Poules affichées"><a href="<?= e($vq(false)) ?>"<?= !$all ? ' aria-current="page"' : '' ?>>Mes poules <span class="count"><?= count($mine) ?></span></a><a href="<?= e($vq(true)) ?>"<?= $all ? ' aria-current="page"' : '' ?>>Toutes les poules <span class="count"><?= count($pools) ?></span></a></div>
  </form>
  <?php if (!$shown): ?>
    <div class="empty"><strong>Aucune poule</strong><?= $q !== '' ? 'Vérifiez l\'orthographe ou cherchez par numéro de poule.' : 'Aucun de vos judokas n\'est placé en poule pour l\'instant.' ?></div>
  <?php else: ?>
    <div class="sheets"><?php $boutHref = fn (array $bt) => null; $highlightClub = (int) $club['id']; foreach ($shown as $p): include BASE_PATH . '/app/Views/partials/pool_sheet.php'; endforeach; ?></div>
    <p class="small muted">Vos judokas sont surlignés. Les scores sont au format IWY-shido (ippon, waza-ari, yuko, puis nombre de pénalités).</p>
  <?php endif; ?>
<?php endif; ?>
