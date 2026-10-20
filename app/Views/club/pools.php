<div class="page-head"><div><p class="eyebrow"><?= e($c['name']) ?></p><h1>Poules et horaires</h1></div><a class="btn btn-ghost" href="<?= e(url('/competitions/' . $c['slug'])) ?>#horaires" target="_blank">Horaires prévisionnels ↗</a></div>
<?php if (!$published): ?><div class="empty"><strong>Poules non publiées</strong>Les poules seront visibles ici dès leur publication par l'organisation, le jour de la compétition.</div><?php else: ?>
<h2 style="font-size:1.2rem">Les poules de mes judokas (<?= count($mine) ?>)</h2>
<div class="grid grid-3" style="margin-bottom:1.6rem">
<?php foreach ($mine as $p): ?><div class="pool"><header><strong><?= e($p['label']) ?></strong><span class="badge">Tapis <?= e($p['tatami_number'] ?? '—') ?></span></header>
  <ol><?php foreach ($p['athletes'] as $a): ?><li<?= (int) $a['club_id'] === (int) $club['id'] ? ' style="font-weight:700"' : '' ?>><?= e($a['last_name'] . ' ' . $a['first_name']) ?> <span class="club">· <?= e($a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label'])) ?></span></li><?php endforeach; ?></ol>
  <footer><span><?= (int) $p['bouts_total'] ?> combats · ordre <?= (int) $p['order_on_tatami'] ?> sur le tapis · <?= (int) $p['bouts_done'] ?> terminés</span></footer></div><?php endforeach; ?>
</div>
<details><summary><strong>Toutes les poules (<?= count($pools) ?>)</strong></summary><div class="grid grid-3" style="margin-top:1rem">
<?php foreach ($pools as $p): ?><div class="pool"><header><strong><?= e($p['label']) ?></strong><span class="badge">Tapis <?= e($p['tatami_number'] ?? '—') ?></span></header><ol><?php foreach ($p['athletes'] as $a): ?><li><?= e($a['last_name'] . ' ' . $a['first_name']) ?> <span class="club">· <?= e($a['club_short'] ?: ($a['club_name'] ?? '')) ?></span></li><?php endforeach; ?></ol></div><?php endforeach; ?>
</div></details>
<?php endif; ?>
