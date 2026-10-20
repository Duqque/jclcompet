<?php use App\Services\ContentService; use App\Services\Markdown; ?>
<div class="wrap" style="padding-top:.6rem">
  <?php if ($featured): $hc = $featured; $onHome = true; include __DIR__ . '/_hero.php'; endif; ?>
</div>
<section class="section">
  <div class="wrap split">
    <div>
      <p class="eyebrow">Le club organisateur</p>
      <h2 style="font-size:clamp(1.5rem,3vw,2.2rem);letter-spacing:-.03em"><?= e(ContentService::site('club_full_name', $clubName)) ?></h2>
      <div class="prose lead"><?= Markdown::render(ContentService::site('club_presentation')) ?></div>
      <a class="btn btn-ghost" href="<?= e(url('/club')) ?>">Découvrir le club</a>
    </div>
    <div class="s-card">
      <div class="figures">
        <?php if ($y = ContentService::site('club_founded')): ?><div><strong><?= e($y) ?></strong><span>création</span></div><?php endif; ?>
        <?php if ($l = ContentService::site('club_licensees')): ?><div><strong><?= e($l) ?></strong><span>licenciés</span></div><?php endif; ?>
        <?php if ($b = ContentService::site('club_black_belts')): ?><div><strong><?= e($b) ?></strong><span>ceintures noires</span></div><?php endif; ?>
        <?php if ($history): $last = end($history); ?><div><strong><?= (int) $last['total_participants'] ?></strong><span>judokas en <?= e($last['season_label']) ?></span></div><?php endif; ?>
      </div>
      <hr style="border:0;border-top:1px solid var(--line);margin:1.4rem 0">
      <h3><?= e(ContentService::site('home_cta_title')) ?></h3>
      <p class="muted"><?= e(ContentService::site('home_cta_text')) ?></p>
      <a class="btn" href="<?= e(url('/connexion', ['profil' => 'club'])) ?>">Espace clubs</a>
    </div>
  </div>
</section>

<?php if ($albums): ?>
<section class="section-tight">
  <div class="wrap">
    <div class="section-title"><div><h2>Photos</h2><p>Albums des compétitions, accessibles avec le mot de passe communiqué par le club.</p></div><a class="btn btn-ghost" href="<?= e(url('/photos')) ?>">Tous les albums</a></div>
    <div class="albums"><?php foreach (array_slice($albums, 0, 3) as $a): include __DIR__ . '/_album_card.php'; endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($partners): ?>
<section class="section">
  <div class="wrap">
    <div class="section-title"><h2>Ils nous soutiennent</h2><a class="btn btn-ghost" href="<?= e(url('/partenaires')) ?>">Tous nos partenaires</a></div>
    <ul class="logos"><?php foreach ($partners as $p): if (!$p['logo_document_id']) continue; ?><li><a href="<?= e($p['url'] ?: '#') ?>" <?= $p['url'] ? 'target="_blank" rel="noopener"' : '' ?> title="<?= e($p['name']) ?>"><img src="<?= e(url('/media/' . $p['logo_document_id'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a></li><?php endforeach; ?></ul>
  </div>
</section>
<?php endif; ?>
