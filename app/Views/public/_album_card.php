<a class="album" href="<?= e(url('/photos/' . $a['slug'])) ?>">
  <span class="lock"><?= icon('lock') ?></span>
  <span><span class="small muted"><?= e(fr_date($a['event_date'])) ?> · <?= (int) $a['photos'] ?> photo<?= (int) $a['photos'] > 1 ? 's' : '' ?></span><h3><?= e($a['name']) ?><?= $a['edition_label'] ? ' · ' . e($a['edition_label']) : '' ?></h3></span>
</a>
