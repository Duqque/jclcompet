<?php
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\ContentService;
use App\Services\Markdown;
$logo = asset('img/logo-jcl.png');
?>
<?php if ($featured): ?>
<section class="scoreboard" aria-labelledby="hero-title">
  <div class="wrap">
    <div class="sb-row sb-top">
      <img class="sb-logo" src="<?= e($logo) ?>" alt="" width="80" height="53">
      <div>
        <p class="sb-label"><?= e($featured['edition_label'] ?: CompetitionService::statusLabel($featured['status'])) ?></p>
        <p class="sb-sub"><?= e(fr_date($featured['event_date'], true)) ?> | <?= $mats ?> tapis</p>
      </div>
      <div class="sb-clock" aria-label="Début à <?= e(fr_time($featured['start_time'])) ?>"><?= e(date('G:i', strtotime($featured['start_time']))) ?></div>
    </div>
    <div class="sb-row sb-red">
      <img class="sb-logo" src="<?= e($logo) ?>" alt="" width="120" height="80">
      <div>
        <h1 class="sb-name" id="hero-title"><?= e($featured['name']) ?></h1>
        <p class="sb-club"><?= e($featured['city']) ?><?= $featured['venue'] ? ' — ' . e($featured['venue']) : '' ?></p>
      </div>
      <div class="sb-figure"><strong><?= $clubsCount ?: '—' ?></strong><span>clubs</span></div>
    </div>
    <div class="sb-row sb-white">
      <img class="sb-logo" src="<?= e($logo) ?>" alt="" width="120" height="80">
      <div>
        <p class="sb-name" style="font-size:clamp(1.3rem,3.4vw,2.6rem)">
          <?= e(implode(' · ', array_map(fn ($a) => $a['name'], array_filter($ages, fn ($a) => $a['format'] === 'pools')))) ?>
        </p>
        <p class="sb-club" style="color:var(--ink)">
          <?= $featured['tagline'] ? e($featured['tagline']) : 'Combats debout avec classement' ?>
        </p>
        <div class="sb-actions">
          <?php if ($live): ?>
            <a class="btn btn-red btn-lg btn-display" href="<?= e(url('/competitions/' . $featured['slug'] . '/direct')) ?>">Résultats en direct</a>
          <?php endif; ?>
          <a class="btn <?= $live ? 'btn-ghost' : 'btn-red' ?> btn-lg btn-display" href="<?= e(url('/connexion')) ?>">Inscrire mon club</a>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('/competitions/' . $featured['slug'])) ?>">Programme complet</a>
          <?php if (!$live && in_array($featured['status'], ['finished', 'archived'], true)): ?>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/competitions/' . $featured['slug'] . '/resultats')) ?>">Résultats</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="sb-figure"><strong><?= $athletesCount ?: ($featured['expected_participants'] ?: '—') ?></strong><span><?= $athletesCount ? 'judokas inscrits' : 'judokas attendus' ?></span></div>
    </div>
  </div>
</section>

<section class="section-floor" style="padding:1.4rem 0">
  <div class="wrap">
    <dl class="keyfacts">
      <div><dt>Date</dt><dd><?= e(fr_date($featured['event_date'], true)) ?></dd></div>
      <div><dt>Accueil des premiers judokas</dt><dd><?= e(fr_time($featured['start_time'])) ?></dd></div>
      <div><dt>Lieu</dt><dd><?= e($featured['venue'] ? $featured['venue'] . ', ' : '') ?><?= e($featured['address']) ?></dd></div>
      <div><dt>Inscriptions</dt><dd>
        <?php if ($regOpen): ?><span class="badge badge-green">Ouvertes</span> jusqu'au <?= e(short_datetime($featured['registration_closes_at'])) ?>
        <?php else: ?><?= e(CompetitionService::statusLabel($featured['status'])) ?><?php endif; ?>
      </dd></div>
    </dl>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap media-split">
    <div>
      <p class="eyebrow">Le club organisateur</p>
      <h2><?= e(ContentService::site('club_full_name', $clubName)) ?></h2>
      <div class="prose"><?= Markdown::render(ContentService::site('club_presentation')) ?></div>
      <div class="figures" style="margin:1.4rem 0">
        <?php if ($y = ContentService::site('club_founded')): ?><div><strong><?= e($y) ?></strong><span>année de création</span></div><?php endif; ?>
        <?php if ($l = ContentService::site('club_licensees')): ?><div><strong><?= e($l) ?></strong><span>licenciés en <?= e(ContentService::site('club_stats_season')) ?></span></div><?php endif; ?>
        <?php if ($b = ContentService::site('club_black_belts')): ?><div><strong><?= e($b) ?></strong><span>ceintures noires</span></div><?php endif; ?>
      </div>
      <a class="btn btn-ghost" href="<?= e(url('/club')) ?>">Découvrir le club</a>
    </div>
    <div class="card" style="border-top:6px solid var(--red)">
      <p class="eyebrow">Responsables de clubs</p>
      <h3 style="font-size:1.35rem"><?= e(ContentService::site('home_cta_title')) ?></h3>
      <p><?= e(ContentService::site('home_cta_text')) ?></p>
      <ul class="prose" style="padding-left:1.2rem">
        <li>Inscription par catégorie d'âge, de sexe et de poids</li>
        <li>Modification jusqu'à la date limite, export de vos listes</li>
        <li>Poules, horaires, tapis et résultats de vos judokas</li>
      </ul>
      <div class="btn-group"><a class="btn btn-red btn-display" href="<?= e(url('/connexion')) ?>">Inscrire mon club</a>
      <?php if ($featured && $featured['regulation_document_id']): ?><a class="btn btn-ghost" href="<?= e(url('/media/' . $featured['regulation_document_id'], ['telecharger' => 1])) ?>">Télécharger le programme</a><?php endif; ?></div>
    </div>
  </div>
</section>

<?php if ($history): $cats = ContentService::historyCategories($history); $last = end($history); ?>
<section class="section section-ink">
  <div class="wrap">
    <div class="section-title"><div><p class="eyebrow" style="color:var(--yellow)">L'historique de notre compétition</p><h2><?= (int) $last['total_participants'] ?> judokas en <?= e($last['season_label']) ?></h2></div>
      <a class="btn btn-outline-white" href="<?= e(url('/club')) ?>#historique">Toutes les éditions</a></div>
    <div class="figures">
      <?php foreach (array_slice(array_reverse($history), 0, 4) as $h): ?>
        <div><strong style="color:var(--yellow)"><?= (int) $h['total_participants'] ?></strong><span><?= e($h['season_label']) ?> · <?= (int) $h['clubs_count'] ?> clubs<?= $h['bouts_count'] ? ' · ' . (int) $h['bouts_count'] . ' combats' : '' ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($partners): ?>
<section class="section">
  <div class="wrap">
    <div class="section-title"><h2>Ils nous soutiennent</h2><a class="btn btn-ghost" href="<?= e(url('/partenaires')) ?>">Tous nos partenaires</a></div>
    <ul class="logos">
      <?php foreach ($partners as $p): if (!$p['logo_document_id']) continue; ?>
        <li><a href="<?= e($p['url'] ?: '#') ?>" <?= $p['url'] ? 'target="_blank" rel="noopener"' : '' ?> title="<?= e($p['name']) ?>"><img src="<?= e(url('/media/' . $p['logo_document_id'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
