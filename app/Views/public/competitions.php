<?php
use App\Services\CompetitionService;
$titles = ['live' => 'En cours', 'open' => 'Inscriptions ouvertes', 'upcoming' => 'À venir', 'done' => 'Terminées et archivées'];
?>
<section class="section">
  <div class="wrap">
    <h1>Compétitions</h1>
    <?php $any = false; foreach ($titles as $key => $label): if (empty($groups[$key])) continue; $any = true; ?>
      <h2 style="margin-top:2rem"><?= e($label) ?></h2>
      <div class="grid grid-2">
        <?php foreach ($groups[$key] as $c): ?>
          <a class="comp-card" href="<?= e(url('/competitions/' . $c['slug'])) ?>">
            <div class="comp-date"><div><strong><?= date('d', strtotime($c['event_date'])) ?></strong><span><?= e(mb_substr(FR_MONTHS[(int) date('n', strtotime($c['event_date'])) - 1], 0, 4)) ?>. <?= date('Y', strtotime($c['event_date'])) ?></span></div></div>
            <div class="comp-body">
              <h3><?= e($c['name']) ?></h3>
              <p class="muted" style="margin:0 0 .5rem"><?= e($c['edition_label'] ? $c['edition_label'] . ' · ' : '') ?><?= e($c['city']) ?> · <?= (int) $c['mat_count'] ?> tapis</p>
              <span class="badge <?= $key === 'live' ? 'badge-red' : ($key === 'open' ? 'badge-green' : '') ?>"><?= e(CompetitionService::statusLabel($c['status'])) ?></span>
              <?php if (in_array($key, ['done', 'live'], true)): ?><span class="badge badge-blue">Résultats</span><?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$any): ?><div class="empty"><strong>Aucune compétition publiée</strong>Les prochaines compétitions apparaîtront ici dès leur publication.</div><?php endif; ?>
  </div>
</section>
