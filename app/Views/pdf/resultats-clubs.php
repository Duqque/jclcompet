<?php
/** Résultats par club : catégorie par catégorie (ordre des âges, puis des poids), puis ordre de classement. */
use App\Services\CategoryService;
$i = 0;
$medalLabel = ['gold' => 'Or', 'silver' => 'Argent', 'bronze' => 'Bronze'];
?>
<?php if (!$clubs): ?><p>Aucun résultat pour cette sélection.</p><?php endif; ?>
<?php foreach ($clubs as $cl): $i++;
  $rows = $cl['rows'];
  $gold = count(array_filter($rows, fn ($r) => $r['medal'] === 'gold'));
  $silver = count(array_filter($rows, fn ($r) => $r['medal'] === 'silver'));
  $bronze = count(array_filter($rows, fn ($r) => $r['medal'] === 'bronze'));
  $groups = [];
  foreach ($rows as $r) {
      $groups[CategoryService::label(['name' => $r['pool']['age_name'], 'name_m' => $r['pool']['name_m'], 'name_f' => $r['pool']['name_f']], $r['pool']['sex'])][] = $r;
  } ?>
  <div class="card" style="background:#f5f5f3;border:0">
    <table class="card-head" style="margin:0"><tr>
      <td style="width:17mm"><?= pdf_logo($cl['club']['logo'] ?? null, $cl['club']['name'], 13) ?></td>
      <td><p class="eyebrow">Résultats du club</p><h2 style="margin:0;font-size:14pt"><?= e($cl['club']['name']) ?></h2></td>
      <td class="right"><span class="pill"><?= count($rows) ?> judokas</span><span class="pill" style="background:#f2c94c">Or <?= $gold ?></span><span class="pill" style="background:#d4d7dd">Argent <?= $silver ?></span><span class="pill" style="background:#e0a370">Bronze <?= $bronze ?></span></td>
    </tr></table>
  </div>
  <?php foreach ($groups as $label => $list): ?>
    <div class="avoid">
      <p class="eyebrow" style="margin:3mm 0 0"><?= e($label) ?></p>
      <table class="t">
        <thead><tr><th class="num" style="width:12mm">Place</th><th>Judoka</th><th style="width:22mm">Poids</th><th style="width:20mm">Poule</th><th class="num" style="width:14mm">Vict.</th><th class="num" style="width:14mm">Points</th><th style="width:18mm">Médaille</th></tr></thead>
        <tbody><?php foreach ($list as $r): $a = $r['athlete']; ?>
          <tr>
            <td class="num"><?php if ($r['rank']): ?><span class="medal m<?= min(4, $r['rank']) ?>"><?= $r['rank'] ?></span><?php else: ?><span class="muted small"><?= $r['fought'] ? 'en cours' : 'à venir' ?></span><?php endif; ?></td>
            <td><strong><?= e(mb_strtoupper($a['last_name'])) ?></strong> <?= e($a['first_name']) ?></td>
            <td class="small"><?= $r['pool']['weight_label'] ? e($r['pool']['weight_label']) . ' kg' : ($a['reference_weight_kg'] !== null ? e(kg($a['reference_weight_kg'])) : '—') ?></td>
            <td class="small">Poule <?= (int) $r['pool']['number'] ?></td>
            <td class="num"><?= (int) $r['wins'] ?></td>
            <td class="num"><?= (int) $r['points'] ?></td>
            <td class="small"><?= $r['medal'] ? e($medalLabel[$r['medal']] ?? '') : '' ?></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <?php if ($i < count($clubs)): ?><div class="break"></div><?php endif; ?>
<?php endforeach; ?>
