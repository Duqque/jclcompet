<?php
/** Feuille de poule pour les PDF (même présentation que l'application). @var array $p poule issue de PoolService::sheets() */
use App\Services\BoutService;
use App\Services\CategoryService;

$n = count($p['athletes']);
$short = ['ippon' => 'Ippon', 'wazaari' => 'Waza-ari', 'yuko' => 'Yuko', 'decision' => 'Décision', 'hansoku_make' => 'Hansoku', 'fusen_gachi' => 'Forfait', 'kiken_gachi' => 'Abandon', 'shido' => 'Shido'];
$logo = function (array $a): string {
    $src = pdf_image($a['club_logo'] ?? null);
    return $src ? '<img class="logo" src="' . e($src) . '" alt="">' : '<span class="mono">' . e(initials($a['club_name'] ?? $a['club_exception_label'] ?? '?')) . '</span>';
};
$done = $p['bouts_total'] && $p['bouts_done'] >= $p['bouts_total'];
?>
<div class="card">
  <table class="card-head"><tr>
    <td><p class="eyebrow"><?= e(CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></p><h2 style="margin:0">Poule <?= (int) $p['number'] ?></h2></td>
    <td class="right"><span class="pill ink">Tapis <?= e($p['tatami_number'] ?? '—') ?></span><span class="pill"><?= $n ?> judokas</span><span class="pill"><?= (int) $p['bouts_done'] ?>/<?= (int) $p['bouts_total'] ?> combats</span><span class="pill <?= $done ? 'green' : ($p['bouts_started'] ? 'red' : '') ?>"><?= $done ? 'Terminée' : ($p['bouts_started'] ? 'En cours' : 'À venir') ?></span></td>
  </tr></table>
  <table class="grid">
    <thead><tr><th></th><th class="l">Nom</th><th class="l">Club</th><?php for ($j = 1; $j <= $n; $j++): ?><th><?= $j ?></th><?php endfor; ?><th>Vict.</th><th>Points</th><th>Place</th></tr></thead>
    <tbody>
    <?php foreach ($p['athletes'] as $i => $a): $row = $i + 1; $st = $p['standing'][(int) $a['registration_id']] ?? null; ?>
      <tr>
        <td class="seed"><?= $row ?></td>
        <td><strong><?= e(mb_strtoupper($a['last_name'])) ?></strong> <?= e($a['first_name']) ?><?php if ($a['reference_weight_kg'] !== null): ?><span class="weight"><?= e(kg($a['reference_weight_kg'])) ?></span><?php endif; ?></td>
        <td class="small"><?= $logo($a) ?><?= e($a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label'])) ?></td>
        <?php for ($j = 1; $j <= $n; $j++):
          if ($j === $row) { echo '<td class="x diag"></td>'; continue; }
          $b = $p['cells'][$row][$j] ?? null;
          if ($b && $b['status'] === 'validated') {
              $won = $b['winner_side'] === $b['side'];
              echo '<td class="x ' . ($won ? 'win' : 'loss') . '">' . ($won ? (int) $b['points'] . '<span class="how">' . e($short[$b['win_method']] ?? '') . '</span>' : '0') . '</td>';
          } else {
              echo '<td class="x"></td>';
          }
        endfor; ?>
        <td class="sum"><?= $st && $p['bouts_done'] ? (int) $st['wins'] : '' ?></td>
        <td class="sum"><?= $st && $p['bouts_done'] ? (int) $st['value_points'] : '' ?></td>
        <td class="sum place"><?php if ($p['complete'] && $st && !$st['excluded']): ?><span class="medal m<?= min(4, (int) $st['rank']) ?>"><?= (int) $st['rank'] ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($p['bouts']): ?>
  <table class="t" style="margin-top:1.5mm">
    <thead><tr><th class="num" style="width:6mm">N°</th><th><?= belt('red', true) ?> Premier appelé</th><th class="num" style="width:6mm"></th><th><?= belt('white', true) ?> Second appelé</th><th class="num" style="width:22mm">Résultat</th></tr></thead>
    <tbody>
    <?php foreach ($p['bouts'] as $b): $fa = $p['athletes'][($b['seed_a'] ?? 1) - 1] ?? null; $fb = $p['athletes'][($b['seed_b'] ?? 1) - 1] ?? null; $ok = $b['status'] === 'validated'; ?>
      <tr>
        <td class="num"><strong><?= (int) $b['order_in_pool'] ?></strong></td>
        <td<?= $ok && $b['winner_side'] === 'a' ? ' style="font-weight:700;color:#15803d"' : '' ?>><span class="muted small"><?= (int) $b['seed_a'] ?></span> <?= $fa ? e(mb_strtoupper($fa['last_name']) . ' ' . $fa['first_name']) : '?' ?></td>
        <td class="num muted">×</td>
        <td<?= $ok && $b['winner_side'] === 'b' ? ' style="font-weight:700;color:#15803d"' : '' ?>><span class="muted small"><?= (int) $b['seed_b'] ?></span> <?= $fb ? e(mb_strtoupper($fb['last_name']) . ' ' . $fb['first_name']) : '?' ?><?= $b['phase'] === 'final' ? ' <span class="pill">barrage</span>' : '' ?></td>
        <td class="num small"><?= $ok ? '<strong style="color:#15803d">' . (int) $b['points'] . '</strong> · ' . e($short[$b['win_method']] ?? '') : '&nbsp;' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
