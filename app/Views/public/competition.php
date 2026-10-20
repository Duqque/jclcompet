<?php
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\ContentService;
use App\Services\Markdown;
use App\Services\SalesService;

$sec = fn (string $k) => $s[$k] ?? null;
$md = fn (string $k) => isset($s[$k]) ? Markdown::render($s[$k]['body']) : '';
$poolAges = array_values(array_filter($ages, fn ($a) => $a['format'] === 'pools'));
$tabs = array_filter([
    'presentation' => 'Présentation',
    'horaires' => $schedule ? 'Horaires' : null,
    'deroulement' => $steps || $sec('plan') ? 'Déroulement' : null,
    'categories' => $poolAges ? 'Catégories' : null,
    'reglement' => 'Règlement',
    'buvette' => $menu ? 'Buvette' : null,
    'clubs' => $clubs ? 'Clubs' : null,
    'partenaires' => $partners ? 'Partenaires' : null,
    'loterie' => $prizes || $sec('lottery') ? 'Loterie' : null,
    'historique' => $history ? 'Historique' : null,
]);
$tierLabels = ContentService::PARTNER_TIERS;
?>
<div class="wrap" style="padding-top:.6rem"><?php $hc = $c; include __DIR__ . '/_hero.php'; ?></div>

<nav class="tabs no-print" aria-label="Sections du programme">
  <div class="wrap"><div class="segmented"><?php foreach ($tabs as $id => $label): ?><a href="#<?= $id ?>"><?= e($label) ?></a><?php endforeach; ?><?php if ($hasAlbum): ?><a href="<?= e(url('/photos/' . $c['slug'])) ?>"><?= icon('lock') ?>Photos</a><?php endif; ?><a href="<?= e(url('/competitions/' . $c['slug'] . '/resultats')) ?>">Résultats</a></div></div>
</nav>

<section class="section anchor" id="presentation">
  <div class="wrap media-split">
    <div>
      <?php if ($w = $sec('welcome')): ?><p class="eyebrow"><?= e($w['title']) ?></p><?php endif; ?>
      <h2><?= e($c['name']) ?></h2>
      <div class="prose"><?= $md('welcome') ?: Markdown::render($c['description']) ?></div>
      <dl class="keyfacts" style="margin:1.2rem 0">
        <div><dt>Date</dt><dd><?= e(fr_date($c['event_date'], true)) ?></dd></div>
        <div><dt>Lieu</dt><dd><?= e($c['venue'] ? $c['venue'] . ', ' : '') ?><?= e($c['address']) ?></dd></div>
        <div><dt>Tapis</dt><dd><?= (int) $mats ?></dd></div>
        <?php if ($c['registration_closes_at']): ?><div><dt>Date limite d'inscription</dt><dd><?= e(fr_datetime($c['registration_closes_at'])) ?></dd></div><?php endif; ?>
        <?php if ($c['expected_participants']): ?><div><dt>Participants attendus</dt><dd>≈ <?= (int) $c['expected_participants'] ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($c['practical_info']): ?><h3>Informations pratiques</h3><div class="prose"><?= Markdown::render($c['practical_info']) ?></div><?php endif; ?>
    </div>
    <div class="stack">
      <?php if ($cover): ?><img src="<?= e($cover) ?>" alt="Affiche de la <?= e($c['name']) ?>" loading="lazy" style="border-radius:24px;box-shadow:var(--shadow)"><?php endif; ?>
      <?php if ($c['coach_info']): ?>
        <div class="s-card"><p class="eyebrow">Pour les entraîneurs et responsables de clubs</p><div class="prose"><?= Markdown::render($c['coach_info']) ?></div>
        <a class="btn" href="<?= e(url('/connexion', ['profil' => 'club'])) ?>">Accéder à l'espace clubs</a></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($schedule): ?>
<section class="section anchor" id="horaires">
  <div class="wrap">
    <h2><?= e($sec('schedule_intro')['title'] ?? 'Horaires prévisionnels') ?> <span class="muted" style="font-size:.7em;text-transform:none;font-style:normal">du <?= e(fr_date($c['event_date'], true)) ?></span></h2>
    <div class="prose"><?= $md('schedule_intro') ?></div>
    <div class="schedule">
      <?php foreach ($schedule as $group => $rows): ?>
        <div class="schedule-block">
          <h3><?= e($group ?: 'Programme') ?></h3>
          <ol>
            <?php foreach ($rows as $row): ?>
              <li class="<?= $row['is_highlight'] ? 'hl' : '' ?>"><time><?= e($row['title']) ?></time><span><?= Markdown::inline((string) $row['body']) ?></span></li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="prose" style="margin-top:1rem"><?= $md('schedule_note') ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($steps || $sec('plan')): ?>
<section class="section anchor" id="deroulement">
  <div class="wrap">
    <h2><?= e($sec('welcome')['title'] ?? 'Déroulement de la journée') ?></h2>
    <?php if ($steps): ?>
      <div class="steps">
        <?php foreach ($steps as $st): ?><article class="step"><h3><?= e($st['title']) ?></h3><div class="prose"><?= Markdown::render($st['body']) ?></div></article><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($p = $sec('plan')): ?>
      <div class="media-split" style="margin-top:2.5rem">
        <?php if ($p['image_document_id']): ?><img src="<?= e(url('/media/' . $p['image_document_id'])) ?>" alt="Plan de la salle : plateau de compétition et tapis" loading="lazy" style="background:#fff;padding:12px;box-shadow:var(--shadow)"><?php endif; ?>
        <div>
          <h3 style="font-size:1.3rem"><?= e($p['title']) ?></h3>
          <div class="prose"><?= Markdown::render($p['body']) ?></div>
          <?php if ($access): ?><ul class="prose"><?php foreach ($access as $a): ?><li><?= e($a['title']) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($r = $sec('respect')): ?><div class="s-card" style="margin-top:2rem"><h3><?= e($r['title']) ?></h3><div class="prose"><?= Markdown::render($r['body']) ?></div></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($poolAges): ?>
<section class="section anchor" id="categories">
  <div class="wrap">
    <h2><?= e($sec('categories')['title'] ?? 'Catégories d\'âge et de poids') ?></h2>
    <div class="prose"><?= $md('categories') ?></div>
    <?php foreach (['F' => 'Les filles', 'M' => 'Les garçons'] as $sex => $label):
      $cols = array_values(array_filter($poolAges, fn ($a) => !empty($weights[$sex][$a['id']])));
      if (!$cols) continue;
      // Lignes alignées par valeur, comme dans le programme : « -28 kg » sur une même ligne pour toutes les catégories,
      // la catégorie ouverte « +X » sur la ligne suivant « -X ».
      $limits = [];
      foreach ($cols as $a) { foreach ($weights[$sex][$a['id']] as $w) { if ($w['max_weight_kg'] !== null) { $limits[] = (float) $w['max_weight_kg']; } } }
      $limits = array_values(array_unique($limits)); sort($limits);
      $grid = [];
      foreach ($cols as $a) {
          foreach ($weights[$sex][$a['id']] as $w) {
              if ($w['max_weight_kg'] !== null) { $row = array_search((float) $w['max_weight_kg'], $limits, true); }
              else { $after = array_values(array_filter($limits, fn ($l) => $l > (float) $w['min_weight_kg'])); $row = $after ? array_search($after[0], $limits, true) : count($limits); }
              $grid[$row][$a['id']] = $w;
          }
      }
      $rowsCount = $grid ? max(array_keys($grid)) + 1 : 0; ?>
      <div style="margin:1.6rem 0 0"><div class="table-wrap">
        <table class="weights">
          <caption><?= e($label) ?></caption>
          <thead><tr><?php foreach ($cols as $a): ?><th scope="col"><?= e(CategoryService::label($a, $sex)) ?><br><span class="muted">(<?= e(CategoryService::birthYearsLabel($a)) ?>)</span></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php for ($i = 0; $i < $rowsCount; $i++): ?>
            <tr><?php foreach ($cols as $a): $w = $grid[$i][$a['id']] ?? null; ?><td class="<?= $w ? '' : 'empty-cell' ?>"><?= $w ? e($w['label']) . ' kg' : '—' ?></td><?php endforeach; ?></tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div></div>
    <?php endforeach; ?>
    <?php $anim = array_filter($ages, fn ($a) => $a['format'] === 'animation'); if ($anim): ?>
      <p><?php foreach ($anim as $a): ?><span class="badge badge-yellow"><?= e($a['name']) ?> (<?= e(CategoryService::birthYearsLabel($a)) ?>)</span> <?php endforeach; ?> animation collective sans classement, médaille pour tous.</p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section anchor" id="reglement">
  <div class="wrap">
    <h2>Le règlement d'arbitrage</h2>
    <div class="grid grid-3">
      <?php foreach (['rules_format', 'rules_refereeing', 'rules_durations'] as $k): if (!$sec($k)) continue; ?>
        <div class="s-card"><h3><?= e($s[$k]['title']) ?></h3><div class="prose"><?= $md($k) ?></div></div>
      <?php endforeach; ?>
      <?php if (!$sec('rules_durations') && $rules): ?>
        <div class="card"><h3>Temps de combat</h3><ul class="prose"><?php foreach ($rules as $rl): ?><li><?= e($rl['age_name'] ?? 'Par défaut') ?> : <?= e(duration_label((int) $rl['duration_seconds'])) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
    </div>
    <?php if ($regulation): ?><p style="margin-top:1rem"><a class="btn btn-ghost" href="<?= e(url('/media/' . $regulation['id'], ['telecharger' => 1])) ?>">Télécharger le document complet (PDF)</a></p><?php endif; ?>
  </div>
</section>

<?php if ($menu): ?>
<section class="section anchor" id="buvette">
  <div class="wrap">
    <div class="section-title"><h2><?= e($sec('buvette')['title'] ?? 'Buvette') ?></h2><?php if ($cardOk): ?><span class="card-ok"><?= icon('check') ?> CB autorisée</span><?php endif; ?></div>
    <div class="prose"><?= $md('buvette') ?></div>
    <div class="menu">
      <?php foreach ($menu as $cat => $items): ?>
        <div class="menu-card">
          <h3><?= e(SalesService::CATEGORIES[$cat] ?? ucfirst($cat)) ?></h3>
          <ul><?php foreach ($items as $p): ?><li><span><?= e($p['name']) ?><?php if ($p['description']): ?> <small>(<?= e($p['description']) ?>)</small><?php endif; ?></span><span class="price"><?= e(money((int) $p['price_cents'])) ?></span></li><?php endforeach; ?></ul>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="small muted" style="margin-top:1rem"><?= e($sec('alcohol')['body'] ?? '') ?></p>
  </div>
</section>
<?php endif; ?>

<?php if ($clubs): ?>
<section class="section anchor" id="clubs">
  <div class="wrap">
    <h2><?= e($sec('clubs')['title'] ?? 'Clubs présents') ?></h2>
    <p class="muted"><?= e($sec('clubs')['body'] ?? '') ?></p>
    <ul class="clubs-grid">
      <?php foreach ($clubs as $cl): $link = $cl['facebook_url'] ?: $cl['website_url']; $tag = $link ? 'a' : 'div'; ?>
        <li><<?= $tag ?> class="club-tile<?= $cl['is_host'] ? ' host' : '' ?>"<?= $link ? ' href="' . e($link) . '" target="_blank" rel="noopener"' : '' ?>>
          <?php $logo = $cl['logo_path']; $name = $cl['name']; $size = 84; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?>
          <strong><?= e($cl['name']) ?><?= $cl['department'] ? ' (' . e($cl['department']) . ')' : '' ?></strong>
          <?php if ($cl['is_host']): ?><span class="badge badge-red">Club hôte</span><?php endif; ?>
        </<?= $tag ?>></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($partners): ?>
<section class="section anchor" id="partenaires">
  <div class="wrap">
    <h2><?= e($sec('partners')['title'] ?? 'Nos partenaires') ?></h2>
    <p class="muted"><?= e($sec('partners')['body'] ?? '') ?></p>
    <?php include BASE_PATH . '/app/Views/public/_partners.php'; ?>
    <?php if ($a = $sec('adage')): ?><blockquote class="s-card" style="margin:2rem 0 0"><h3><?= e($a['title']) ?></h3><div class="prose"><?= Markdown::render($a['body']) ?></div></blockquote><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($prizes || $sec('lottery')): ?>
<section class="section anchor" id="loterie">
  <div class="wrap media-split">
    <div>
      <h2><?= e($sec('lottery')['title'] ?? 'Loterie') ?></h2>
      <div class="prose"><?= $md('lottery') ?></div>
    </div>
    <div class="s-card">
      <h3>De nombreux lots à gagner</h3>
      <ul class="prose"><?php foreach ($prizes as $p): ?><li><?= e($p['title']) ?></li><?php endforeach; ?></ul>
      <div class="prose"><?= $md('lottery_outro') ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($v = $sec('volunteers')): ?>
<section class="section">
  <div class="wrap-narrow s-card" style="text-align:center;background:var(--glow)"><h2><?= e($v['title']) ?></h2><div class="prose" style="margin-inline:auto"><?= Markdown::render($v['body']) ?></div></div>
</section>
<?php endif; ?>

<?php if ($history): ?>
<section class="section anchor" id="historique">
  <div class="wrap">
    <h2><?= e($sec('history')['title'] ?? 'Historique') ?></h2>
    <?php include BASE_PATH . '/app/Views/public/_history.php'; ?>
    <p style="font-weight:600;font-size:1.2rem;margin-top:1.2rem"><?= e($sec('history')['body'] ?? '') ?></p>
  </div>
</section>
<?php endif; ?>

<?php if ($t = $sec('thanks')): ?>
<section class="section"><div class="wrap-narrow" style="text-align:center"><h2><?= e($t['title']) ?></h2><p><?= e($t['body']) ?></p></div></section>
<?php endif; ?>
