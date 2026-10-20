<?php /** @var array $partners par catégorie */ use App\Services\ContentService; ?>
<?php foreach ($partners as $tier => $list): ?>
  <div class="partners-tier">
    <h3><?= e(ContentService::PARTNER_TIERS[$tier] ?? $tier) ?></h3>
    <?php $withAds = array_filter($list, fn ($p) => $p['ad_document_id']); $logosOnly = array_filter($list, fn ($p) => !$p['ad_document_id']); ?>
    <?php if ($logosOnly): ?>
      <ul class="logos">
        <?php foreach ($logosOnly as $p): $tag = $p['url'] ? 'a' : 'span'; ?>
          <li><<?= $tag ?><?= $p['url'] ? ' href="' . e($p['url']) . '" target="_blank" rel="noopener"' : '' ?> title="<?= e($p['name']) ?>">
            <?php if ($p['logo_document_id']): ?><img src="<?= e(url('/media/' . $p['logo_document_id'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php else: ?><strong><?= e($p['name']) ?></strong><?php endif; ?>
          </<?= $tag ?>></li>
        <?php endforeach; ?>
      </ul>
      <?php foreach ($logosOnly as $p): if (!$p['description']) continue; ?><p class="muted" style="margin-top:.8rem;max-width:72ch"><strong style="color:var(--ink)"><?= e($p['name']) ?></strong> — <?= e($p['description']) ?></p><?php endforeach; ?>
    <?php endif; ?>
    <?php if ($withAds): ?>
      <ul class="ads">
        <?php foreach ($withAds as $p): ?>
          <li>
            <?php if ($p['url']): ?><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?php endif; ?>
            <img src="<?= e(url('/media/' . $p['ad_document_id'])) ?>" alt="Encart de <?= e($p['name']) ?>" loading="lazy">
            <?php if ($p['url']): ?></a><?php endif; ?>
            <div class="ad-body"><strong><?= e($p['name']) ?></strong><?php if ($p['description']): ?><p><?= e($p['description']) ?></p><?php endif; ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
