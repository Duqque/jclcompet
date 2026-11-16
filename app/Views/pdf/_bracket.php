<?php
/** Tableau (double repêchage ou tableau final) pour les PDF : un tableau par tour, puis le classement. @var array $p */
use App\Services\CategoryService;

$bk = $p['bracket'];
$blank = $blank ?? false;
$name = function (array $x) use ($blank): string {
    if ($x['type'] === 'athlete' && $x['athlete']) {
        return '<strong>' . e($x['athlete']['last_name']) . '</strong> ' . e($x['athlete']['first_name']) . ' <span class="muted">' . e($x['athlete']['club_short'] ?? '') . '</span>';
    }
    return '<span class="muted">' . e($x['text'] ?? '') . '</span>';
};
$score = function (array $n, string $s) use ($blank): string {
    if ($blank || !$n['bout'] || $n['bout']['status'] !== 'validated') {
        return '';
    }
    $real = $n['bout']['flip'] ? ($s === 'a' ? 'b' : 'a') : $s;
    $sc = $n['bout']['score'][$real];
    return $sc['ippon'] . $sc['wazaari'] . $sc['yuko'] . ($sc['shido'] ? '-' . $sc['shido'] : '');
};
?>
<div class="card sheet-card">
  <div class="doc-head-mini">
    <strong><?= e(CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) ?><?= $p['weight_label'] ? ' ' . e($p['weight_label']) . ' kg' : '' ?></strong>
    — <?= $bk['kind'] === 'final_block' ? 'Tableau final' : 'Tableau à double repêchage' ?> · Tapis <?= e($p['tatami_number'] ?? '—') ?> · <?= (int) $bk['count'] ?> judokas
  </div>
  <?php foreach ($bk['sections'] as $s): ?>
    <table class="grid" style="margin-top:6px">
      <thead><tr><th colspan="4"><?= e($s['title']) ?></th></tr></thead>
      <tbody>
      <?php foreach ($s['nodes'] as $n): if (!$n['fight'] && $n['a']['type'] !== 'pending' && $n['b']['type'] !== 'pending') { continue; } ?>
        <tr><td style="width:22%"><?= e($n['label']) ?></td><td><?= belt('red', true) ?> <?= $name($n['a']) ?></td><td style="width:8%" class="num"><?= $score($n, 'a') ?></td><td rowspan="1"></td></tr>
        <tr><td></td><td><?= belt('white', true) ?> <?= $name($n['b']) ?></td><td class="num"><?= $score($n, 'b') ?></td><td></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
  <?php if (!$blank): ?>
    <table class="grid" style="margin-top:6px"><thead><tr><th style="width:12%">Place</th><th>Judoka</th></tr></thead><tbody>
      <?php foreach ($bk['ranking'] as $r): if (!$r['athlete']) { continue; } ?>
        <tr><td class="num"><?= $r['place'] === null ? 'NC' : ($r['place'] === 1 ? '1er' : $r['place'] . 'e') ?></td><td><strong><?= e($r['athlete']['last_name']) ?></strong> <?= e($r['athlete']['first_name']) ?> <span class="muted"><?= e($r['athlete']['club_short'] ?? '') ?></span></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
