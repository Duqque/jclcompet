<?php use App\Services\ContentService; use App\Services\Markdown; $sec = fn ($k) => $s[$k] ?? null; ?>
<section class="section">
  <div class="wrap media-split">
    <div>
      <p class="eyebrow">Depuis <?= e(ContentService::site('club_founded', '2000')) ?></p>
      <h1><?= e($sec('identity')['title'] ?? 'Notre club') ?></h1>
      <div class="prose"><?= Markdown::render($sec('identity')['body'] ?? '') ?></div>
      <div class="figures" style="margin-top:1.4rem">
        <div><strong><?= e(ContentService::site('club_licensees')) ?></strong><span>licenciés (<?= e(ContentService::site('club_stats_season')) ?>)</span></div>
        <div><strong><?= e(ContentService::site('club_black_belts')) ?></strong><span>ceintures noires</span></div>
      </div>
    </div>
    <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="Logo du Judo Club Leforest" width="640" height="425" style="max-width:420px;margin-inline:auto">
  </div>
</section>

<?php if ($staff): ?>
<section class="section section-floor"><div class="wrap">
  <h2><?= e($sec('staff_intro')['title'] ?? 'Le staff') ?></h2>
  <?php foreach ($staff as $group => $people): ?>
    <h3 style="margin-top:1.4rem"><?= e($group) ?></h3>
    <ul class="people"><?php foreach ($people as $p): ?><li><strong><?= e($p['title']) ?></strong><?= e($p['subtitle']) ?></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
</div></section>
<?php endif; ?>

<section class="section"><div class="wrap media-split">
  <div>
    <h2><?= e($sec('dojo')['title'] ?? 'Notre dojo') ?></h2>
    <div class="prose"><?= Markdown::render($sec('dojo')['body'] ?? ContentService::site('club_address')) ?></div>
    <h3 style="margin-top:1.6rem"><?= e($sec('contact')['title'] ?? 'Nous contacter') ?></h3>
    <dl class="info-list">
      <?php if ($v = ContentService::site('club_phone')): ?><dt>Téléphone</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v)) ?>"><?= e($v) ?></a></dd><?php endif; ?>
      <?php if ($v = ContentService::site('club_email')): ?><dt>E-mail</dt><dd><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a></dd><?php endif; ?>
      <?php if ($v = ContentService::site('club_facebook')): ?><dt>Facebook</dt><dd><a href="<?= e($v) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://(www\.)?#', '', $v)) ?></a></dd><?php endif; ?>
      <?php if ($v = ContentService::site('club_instagram')): ?><dt>Instagram</dt><dd><a href="<?= e($v) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://(www\.)?#', '', $v)) ?></a></dd><?php endif; ?>
    </dl>
    <div class="prose" style="margin-top:1rem"><?= Markdown::render($sec('contact')['body'] ?? '') ?></div>
  </div>
  <?php if (!empty($sec('dojo')['image_document_id'])): ?><img src="<?= e(url('/media/' . $sec('dojo')['image_document_id'])) ?>" alt="Plan d'accès au dojo" loading="lazy"><?php endif; ?>
</div>
<?php if ($photos): ?><div class="wrap photo-row" style="margin-top:1.5rem"><?php foreach ($photos as $p): ?><figure><img src="<?= e(url('/media/' . $p['image_document_id'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy"><figcaption><?= e($p['title']) ?></figcaption></figure><?php endforeach; ?></div><?php endif; ?>
</section>

<?php if ($courses): ?>
<section class="section section-floor"><div class="wrap">
  <h2><?= e($sec('courses')['title'] ?? 'Horaires des cours') ?></h2>
  <div class="table-wrap"><table>
    <thead><tr><th scope="col">Catégorie / année de naissance</th><th scope="col">Cours</th></tr></thead>
    <tbody><?php foreach ($courses as $c): ?><tr><th scope="row" style="text-transform:none;letter-spacing:0;color:var(--ink);font-size:.95rem;background:none"><?= e($c['title']) ?></th><td><?= nl2br(e($c['body'])) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <div class="prose" style="margin-top:1rem"><?= Markdown::render($sec('courses')['body'] ?? '') ?></div>
  <p><a class="btn btn-red" href="<?= e(url('/taiso')) ?>">Nouveau : le Taïso, le lundi soir</a></p>
</div></section>
<?php endif; ?>

<?php if ($results): ?>
<section class="section"><div class="wrap">
  <h2><?= e($sec('results')['title'] ?? 'Résultats de la saison') ?></h2>
  <div class="prose"><?= Markdown::render($sec('results')['body'] ?? '') ?></div>
  <ul class="people"><?php foreach ($results as $r): ?><li><strong><?= e($r['title']) ?></strong><?= Markdown::inline((string) $r['body']) ?></li><?php endforeach; ?></ul>
</div></section>
<?php endif; ?>

<?php if ($alumni): ?>
<section class="section section-ink"><div class="wrap">
  <h2><?= e($sec('alumni')['title'] ?? 'Formés au club') ?></h2>
  <p><?= Markdown::inline($sec('alumni')['body'] ?? '') ?></p>
  <div class="grid grid-3">
    <?php foreach ($alumni as $a): ?>
      <article class="card" style="color:var(--ink)">
        <div style="display:flex;gap:.8rem;align-items:center;margin-bottom:.6rem">
          <?php if ($a['image_document_id']): ?><a href="<?= e($a['link_url'] ?: '#') ?>" target="_blank" rel="noopener"><img src="<?= e(url('/media/' . $a['image_document_id'])) ?>" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:contain"></a><?php endif; ?>
          <h3 style="margin:0"><?= e($a['title']) ?></h3>
        </div>
        <p style="margin:0"><?= Markdown::inline((string) $a['body']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($history): ?>
<section class="section" id="historique"><div class="wrap"><h2>L'historique de notre compétition</h2><?php include BASE_PATH . '/app/Views/public/_history.php'; ?></div></section>
<?php endif; ?>
