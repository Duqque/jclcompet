<section class="section" data-live-url="<?= e(url('/api/public/competitions/' . $c['slug'] . '/direct')) ?>">
  <div class="wrap">
    <p class="eyebrow"><a href="<?= e(url('/competitions/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></p>
    <div class="section-title"><h1>En direct des tapis</h1><a class="btn btn-ghost" href="<?= e(url('/competitions/' . $c['slug'] . '/resultats')) ?>">Résultats et classements</a></div>
    <p class="muted small" data-live-status>Actualisation automatique…</p>
    <div class="grid grid-2" data-live-grid><div class="empty"><span class="spinner"></span> Chargement des tapis…</div></div>
  </div>
</section>
