<?php
use App\Services\BoutService;
use App\Services\CategoryService;
$tabs = ['categories' => 'Par catégorie', 'poules' => 'Par poule', 'individuel' => 'Classement individuel', 'clubs' => 'Classement des clubs', 'medailles' => 'Médailles'];
if ($showMats) { $tabs['tapis'] = 'Par tapis'; }
$medal = fn ($m) => $m ? '<span class="medal medal-' . e($m) . '" title="' . ($m === 'gold' ? 'Or' : ($m === 'silver' ? 'Argent' : 'Bronze')) . '">' . ($m === 'gold' ? 'Or' : ($m === 'silver' ? 'Ar' : 'Br')) . '</span>' : '';
?>
<section class="section">
  <div class="wrap">
    <p class="eyebrow"><a href="<?= e(url('/competitions/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></p>
    <div class="section-title">
      <h1>Résultats</h1>
      <div class="btn-group">
        <?php if ($c['status'] === 'in_progress'): ?><a class="btn btn-red" href="<?= e(url('/competitions/' . $c['slug'] . '/direct')) ?>">Tapis en direct</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/classement/' . $c['slug'])) ?>">Classement des clubs (écran)</a>
      </div>
    </div>
    <dl class="keyfacts" style="margin-bottom:1.2rem">
      <div><dt>Médailles d'or</dt><dd><?= (int) $totals['gold'] ?></dd></div>
      <div><dt>Argent</dt><dd><?= (int) $totals['silver'] ?></dd></div>
      <div><dt>Bronze</dt><dd><?= (int) $totals['bronze'] ?></dd></div>
      <div><dt>Dernière mise à jour</dt><dd><?= e($lastUpdate ? short_datetime($lastUpdate) : '—') ?></dd></div>
    </dl>
    <nav class="btn-group" aria-label="Vues des résultats" style="margin-bottom:1.4rem">
      <?php foreach ($tabs as $k => $label): ?><a class="btn btn-sm <?= $view === $k ? 'btn-red' : 'btn-ghost' ?>" href="?vue=<?= $k ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
    </nav>

    <?php if (!$pools && in_array($view, ['categories', 'poules', 'tapis'], true)): ?>
      <div class="empty"><strong>Poules non publiées</strong>Les poules et les résultats apparaîtront ici le jour de la compétition.</div>
    <?php elseif ($view === 'categories' || $view === 'poules' || $view === 'tapis'):
      $byKey = [];
      foreach ($pools as $p) {
          $key = match ($view) {
              'tapis' => 'Tapis ' . ($p['tatami_number'] ?? '—'),
              'poules' => 'Poules',
              default => CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex']) . ($p['weight_label'] ? ' ' . $p['weight_label'] . ' kg' : ''),
          };
          $byKey[$key][] = $p;
      }
      foreach ($byKey as $label => $list): ?>
        <h2 style="margin-top:1.6rem"><?= e($label) ?></h2>
        <div class="grid grid-2">
          <?php foreach ($list as $p): ?>
            <article class="card">
              <div class="card-head"><h3><?= e($p['label']) ?></h3><span class="badge <?= $p['ranking_complete'] ? 'badge-green' : 'badge-orange' ?>"><?= $p['ranking_complete'] ? 'Terminée' : $p['bouts_done'] . '/' . $p['bouts_total'] . ' combats' ?></span></div>
              <p class="small muted">Poule n° <?= (int) $p['number'] ?> · Tapis <?= e($p['tatami_number'] ?? '—') ?><?= $p['weight_min_kg'] !== null ? ' · ' . kg($p['weight_min_kg']) . ' – ' . kg($p['weight_max_kg']) : '' ?></p>
              <?php $athletes = $p['athletes']; usort($athletes, fn ($x, $y) => [$x['final_rank'] ?? $x['computed_rank'] ?? 99, $x['seed']] <=> [$y['final_rank'] ?? $y['computed_rank'] ?? 99, $y['seed']]); ?>
              <ol class="podium-list">
                <?php foreach ($athletes as $a): ?>
                  <li><span class="place"><?= $a['final_rank'] ? (int) $a['final_rank'] : '·' ?></span>
                    <span style="flex:1"><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><br><span class="small muted"><?= e($a['club_name'] ?? $a['club_exception_label']) ?></span></span>
                    <span class="small muted num"><?= (int) $a['wins'] ?> V · <?= (int) $a['value_points'] ?> pts</span></li>
                <?php endforeach; ?>
              </ol>
              <?php if (!empty($boutsByPool[$p['id']])): ?>
                <details style="margin-top:.6rem"><summary class="small">Détail des combats</summary>
                  <table class="table-compact" style="margin-top:.5rem"><tbody>
                  <?php foreach ($boutsByPool[$p['id']] as $b): ?>
                    <tr><td class="num"><?= (int) $b['order_in_pool'] ?></td>
                      <td<?= $b['winner_side'] === 'a' ? ' style="font-weight:700"' : '' ?>><?= e($b['a_name']) ?></td>
                      <td class="num nowrap"><?= (int) $b['a_ippon'] ?><?= (int) $b['a_wazaari'] ?><?= (int) $b['a_yuko'] ?> – <?= (int) $b['b_ippon'] ?><?= (int) $b['b_wazaari'] ?><?= (int) $b['b_yuko'] ?></td>
                      <td<?= $b['winner_side'] === 'b' ? ' style="font-weight:700"' : '' ?>><?= e($b['b_name']) ?></td>
                      <td class="small muted"><?= $b['status'] === 'validated' ? e(BoutService::METHODS[$b['win_method']] ?? '') : e(BoutService::STATUS[$b['status']]) ?></td></tr>
                  <?php endforeach; ?>
                  </tbody></table>
                </details>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

    <?php elseif ($view === 'individuel'): ?>
      <?php if (!$individual): ?><div class="empty"><strong>Aucun classement définitif</strong>Les places sont publiées dès qu'une poule est terminée et validée.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Catégorie</th><th>Poule</th><th>Place</th><th>Judoka</th><th>Club</th><th>Points</th></tr></thead>
        <tbody><?php foreach ($individual as $i): ?>
          <tr><td><?= e(CategoryService::label(['name' => $i['age_name'], 'name_m' => $i['name_m'], 'name_f' => $i['name_f']], $i['sex'])) ?><?= $i['weight_label'] ? ' ' . e($i['weight_label']) . ' kg' : '' ?></td>
            <td><?= e($i['pool_label']) ?></td><td class="nowrap"><?= (int) $i['place'] ?> <?= $medal($i['medal']) ?></td>
            <td><strong><?= e($i['first_name'] . ' ' . $i['last_name']) ?></strong></td><td><?= e($i['club_name']) ?></td><td class="num"><?= (int) $i['points'] ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div><?php endif; ?>

    <?php else: $rows = $view === 'medailles' ? $clubsMedals : $clubsPoints; ?>
      <?php if (!$rows): ?><div class="empty"><strong>Classement en attente</strong>Le classement des clubs se construit au fil des poules terminées.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Rang</th><th>Club</th><th>Participants</th><?php if ($view === 'clubs'): ?><th>Points</th><?php endif; ?><th>Or</th><th>Argent</th><th>Bronze</th><th>4<sup>e</sup></th><th>5<sup>e</sup></th><?php if ($view === 'medailles'): ?><th>Points</th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
          <tr><td class="num"><strong><?= (int) ($view === 'medailles' ? $r['rank_medals'] : $r['rank_points']) ?></strong></td>
            <td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['participants'] ?></td>
            <?php if ($view === 'clubs'): ?><td class="num"><strong><?= (int) $r['points'] ?></strong></td><?php endif; ?>
            <td class="num"><?= (int) $r['gold'] ?></td><td class="num"><?= (int) $r['silver'] ?></td><td class="num"><?= (int) $r['bronze'] ?></td><td class="num"><?= (int) $r['fourth'] ?></td><td class="num"><?= (int) $r['fifth'] ?></td>
            <?php if ($view === 'medailles'): ?><td class="num"><?= (int) $r['points'] ?></td><?php endif; ?></tr>
        <?php endforeach; ?></tbody>
      </table></div><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
