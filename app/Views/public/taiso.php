<?php use App\Services\Markdown; $sec = fn ($k) => $s[$k] ?? null; ?>
<section class="section"><div class="wrap media-split">
  <div>
    <p class="eyebrow">Nouveauté au club</p>
    <h1><?= e($sec('hero')['title'] ?? 'Taïso') ?></h1>
    <div class="prose"><?= Markdown::render($sec('hero')['body'] ?? '') ?></div>
    <?php if ($infos): ?><dl class="info-list" style="margin-top:1.2rem"><?php foreach ($infos as $i): ?><dt><?= e($i['title']) ?></dt><dd><?= e($i['subtitle']) ?></dd><?php endforeach; ?></dl><?php endif; ?>
  </div>
  <?php if (!empty($sec('hero')['image_document_id'])): ?><img src="<?= e(url('/media/' . $sec('hero')['image_document_id'])) ?>" alt="Affiche du Taïso au Judo Club Leforest" loading="lazy" style="box-shadow:var(--shadow)"><?php endif; ?>
</div></section>
<section class="section section-floor"><div class="wrap-narrow">
  <h2><?= e($sec('what')['title'] ?? 'Le Taïso') ?></h2>
  <div class="prose"><?= Markdown::render($sec('what')['body'] ?? '') ?></div>
  <?php if ($benefits): ?><ul class="prose"><?php foreach ($benefits as $b): ?><li><?= e($b['title']) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="prose"><?= Markdown::render($sec('outro')['body'] ?? '') ?></div>
</div></section>
