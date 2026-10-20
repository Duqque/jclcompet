<?php
/**
 * Feuille de poule (grille croisée, victoires, points, place) — format des feuilles officielles.
 * @var array $p poule issue de PoolService::sheets()
 * @var callable(array):?string $boutHref lien vers le détail / la correction d'un combat (null = pas de lien)
 * @var string $q recherche en cours (surligne l'athlète)
 */
use App\Services\BoutService;
use App\Services\CategoryService;

$n = count($p['athletes']);
$short = ['ippon' => 'Ippon', 'wazaari' => 'Waza-ari', 'yuko' => 'Yuko', 'decision' => 'Décision', 'hansoku_make' => 'Hansoku', 'fusen_gachi' => 'Forfait', 'kiken_gachi' => 'Abandon', 'shido' => 'Shido'];
$q = mb_strtolower(trim($q ?? ''));
$state = $p['bouts_done'] >= $p['bouts_total'] && $p['bouts_total'] ? ['badge-green', 'Terminée'] : ($p['bouts_started'] ? ['badge-red', 'En cours'] : ['', 'À venir']);
$link = function (array $b, string $inner, string $cls, string $title) use ($boutHref) {
    $href = $boutHref($b);
    return $href ? '<a class="' . $cls . '" href="' . e($href) . '" title="' . e($title) . '">' . $inner . '</a>' : '<span class="' . $cls . '" title="' . e($title) . '">' . $inner . '</span>';
};
?>
<article class="sheet" id="poule-<?= (int) $p['id'] ?>">
  <header class="sheet-head">
    <div>
      <p class="eyebrow"><?= e(CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></p>
      <h3>Poule <?= (int) $p['number'] ?></h3>
    </div>
    <div class="sheet-meta">
      <span class="badge badge-ink">Tapis <?= e($p['tatami_number'] ?? '—') ?></span>
      <span class="badge"><?= $n ?> judokas</span>
      <span class="badge"><?= (int) $p['bouts_done'] ?>/<?= (int) $p['bouts_total'] ?> combats</span>
      <span class="badge <?= $state[0] ?>"><span class="dot"></span><?= $state[1] ?></span>
    </div>
  </header>

  <div class="sheet-scroll">
    <table class="sheet-grid" style="--n:<?= $n ?>">
      <thead><tr>
        <th class="seed" scope="col"><span class="sr-only">N°</span></th><th scope="col" class="who">Nom</th><th scope="col" class="clubcol">Club</th>
        <?php for ($j = 1; $j <= $n; $j++): ?><th scope="col" class="x"><?= $j ?></th><?php endfor; ?>
        <th scope="col" class="sum">Vict.</th><th scope="col" class="sum">Points</th><th scope="col" class="sum">Place</th>
      </tr></thead>
      <tbody>
      <?php foreach ($p['athletes'] as $i => $a): $row = $i + 1; $st = $p['standing'][(int) $a['registration_id']] ?? null;
        $hit = $q !== '' && str_contains(mb_strtolower($a['last_name'] . ' ' . $a['first_name'] . ' ' . $a['first_name'] . ' ' . $a['last_name']), $q); ?>
        <tr<?= $hit ? ' class="hit"' : '' ?>>
          <th scope="row" class="seed"><?= $row ?></th>
          <td class="who"><?= avatar($a['photo_document_id'] ? (int) $a['photo_document_id'] : null, $a['first_name'] . ' ' . $a['last_name'], 'avatar sheet-av') ?><span class="nm"><strong><?= e(mb_strtoupper($a['last_name'])) ?></strong> <?= e($a['first_name']) ?><?php if ($a['reference_weight_kg'] !== null): ?><small><?= e(kg($a['reference_weight_kg'])) ?></small><?php endif; ?></span></td>
          <td class="clubcol"><span class="club" title="<?= e($a['club_name'] ?? $a['club_exception_label'] ?? '') ?>"><?php $logo = $a['club_logo']; $name = $a['club_name'] ?? $a['club_exception_label'] ?? '?'; $size = 26; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?><span><?= e($a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label'])) ?></span></span></td>
          <?php for ($j = 1; $j <= $n; $j++):
            if ($j === $row) { echo '<td class="x diag" aria-hidden="true"></td>'; continue; }
            $b = $p['cells'][$row][$j] ?? null;
            if (!$b) { echo '<td class="x"></td>'; continue; }
            $vs = $p['athletes'][$j - 1]['last_name'];
            if ($b['status'] === 'validated') {
                $won = $b['winner_side'] === $b['side'];
                $inner = '<strong>' . ($won ? (int) $b['points'] : 0) . '</strong><small>' . e($won ? ($short[$b['win_method']] ?? '') : '') . '</small>';
                echo '<td class="x">' . $link($b, $inner, 'cell ' . ($won ? 'win' : 'loss'), 'Combat ' . $b['order_in_pool'] . ' contre ' . $vs . ' — ' . (BoutService::METHODS[$b['win_method']] ?? '') . ' · voir / corriger') . '</td>';
            } elseif (in_array($b['status'], ['in_progress', 'finished'], true)) {
                echo '<td class="x">' . $link($b, '<small>' . ($b['status'] === 'finished' ? 'à valider' : 'en cours') . '</small>', 'cell live', 'Combat ' . $b['order_in_pool'] . ' contre ' . $vs) . '</td>';
            } else {
                echo '<td class="x">' . $link($b, '<small>C' . (int) $b['order_in_pool'] . '</small>', 'cell todo', 'Combat ' . $b['order_in_pool'] . ' contre ' . $vs . ' — à venir') . '</td>';
            }
          endfor; ?>
          <td class="sum"><?= $st ? (int) $st['wins'] : 0 ?></td>
          <td class="sum"><?= $st ? (int) $st['value_points'] : 0 ?></td>
          <td class="sum place"><?php if ($p['complete'] && $st && !$st['excluded']): ?><span class="rank r<?= (int) $st['rank'] ?>"><?= (int) $st['rank'] ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($p['bouts']): ?>
  <footer class="sheet-order" aria-label="Ordre des combats">
    <p class="lbl">Ordre des combats <span class="legend"><?= belt('red') ?>premier appelé <?= belt('white') ?>second appelé</span></p>
    <ol class="bout-list">
    <?php foreach ($p['bouts'] as $b):
      $fa = $p['athletes'][($b['seed_a'] ?? 1) - 1] ?? null;
      $fb = $p['athletes'][($b['seed_b'] ?? 1) - 1] ?? null;
      $done = $b['status'] === 'validated';
      $live = in_array($b['status'], ['in_progress', 'finished'], true);
      $res = $done ? '<span class="res done">' . (int) $b['points'] . ' · ' . e($short[$b['win_method']] ?? '') . '</span>' : ($live ? '<span class="res live">' . ($b['status'] === 'finished' ? 'à valider' : 'en cours') . '</span>' : '<span class="res">à venir</span>');
      $name = fn (?array $f, string $side) => $f ? '<span class="fighter' . ($done && $b['winner_side'] === $side ? ' won' : '') . '"><span class="sd">' . ($side === 'a' ? $b['seed_a'] : $b['seed_b']) . '</span><strong>' . e(mb_strtoupper($f['last_name'])) . '</strong> ' . e($f['first_name']) . '</span>' : '<span class="fighter">?</span>';
      $inner = '<span class="o">' . (int) $b['order_in_pool'] . '</span>' . belt('red') . $name($fa, 'a') . '<span class="vs">×</span>' . belt('white') . $name($fb, 'b') . ($b['phase'] === 'final' ? '<span class="badge">barrage</span>' : '') . $res;
      echo '<li>' . $link($b, $inner, 'bout-row' . ($done ? ' done' : ($live ? ' live' : '')), 'Combat ' . $b['order_in_pool'] . ' — ' . BoutService::STATUS[$b['status']]) . '</li>';
    endforeach; ?>
    </ol>
  </footer>
  <?php endif; ?>
</article>
