<?php
use App\Services\CategoryService;
$per = $opt['per_page']; $opt['show_blank'] = true;
$cols = $per >= 4 ? 2 : 1;
$chunks = array_chunk($pools, $per);
$renderPool = function (array $p) use ($opt, $bouts) {
    ob_start(); ?>
  <div class="pool">
    <h2>Poule n° <?= (int) $p['number'] ?><?= $opt['show_category'] ? ' — ' . e(CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) : '' ?><?= $opt['show_weight'] && $p['weight_label'] ? ' ' . e($p['weight_label']) . ' kg' : '' ?><?= $opt['show_tatami'] && $p['tatami_number'] ? ' · Tapis ' . (int) $p['tatami_number'] : '' ?></h2>
    <div class="small muted"><?= e($p['label']) ?><?= $opt['show_weight'] && $p['weight_min_kg'] !== null ? ' · ' . e(kg($p['weight_min_kg'])) . ' – ' . e(kg($p['weight_max_kg'])) : '' ?></div>
    <table><thead><tr><th style="width:7%">N°</th><?php if ($opt['show_names']): ?><th>Judoka</th><th>Club</th><?php endif; ?><?php if ($opt['show_blank']): ?><th style="width:11%">V</th><th style="width:11%">Pts</th><th style="width:11%">Place</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($p['athletes'] as $a): ?><tr><td class="num"><?= (int) $a['seed'] ?></td><?php if ($opt['show_names']): ?><td><?= e($a['last_name'] . ' ' . $a['first_name']) ?></td><td class="small"><?= e($a['club_name'] ?? $a['club_exception_label']) ?></td><?php endif; ?><?php if ($opt['show_blank']): ?><td class="box"></td><td class="box"></td><td class="box"></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table>
    <?php if ($opt['show_blank'] && !empty($bouts[$p['id']])): ?>
      <table class="small"><thead><tr><th style="width:7%">C.</th><th>Rouge / premier appelé</th><th style="width:16%">Score</th><th>Blanc</th><th style="width:16%">Score</th><th style="width:12%">Vainq.</th></tr></thead><tbody>
      <?php foreach ($bouts[$p['id']] as $b): ?><tr><td class="num"><?= (int) $b['order_in_pool'] ?></td><td><?= e($b['a_name']) ?></td><td class="box"></td><td><?= e($b['b_name']) ?></td><td class="box"></td><td class="box"></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
<?php return ob_get_clean(); };
?>
<?php if (!$pools): ?><p>Aucune poule pour cette sélection.</p><?php endif; ?>
<?php foreach ($chunks as $k => $chunk): ?>
  <?php if ($cols === 2): ?>
    <?php foreach (array_chunk($chunk, 2) as $pair): ?><div class="row2"><?php foreach ($pair as $p): ?><div class="half"><?= $renderPool($p) ?></div><?php endforeach; ?></div><?php endforeach; ?>
  <?php else: foreach ($chunk as $p) { echo $renderPool($p); } endif; ?>
  <?php if ($k < count($chunks) - 1): ?><div class="break"></div><?php endif; ?>
<?php endforeach; ?>
