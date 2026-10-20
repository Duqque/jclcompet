<?php
$base = '/admin/competitions/' . $c['id'];
$heading = 'Résultats';
$sub = $done . ' poule(s) terminée(s) sur ' . $total;
$actions = '<a class="btn btn-ghost" href="' . e(url('/competitions/' . $c['slug'] . '/resultats')) . '" target="_blank">Page publique ' . icon('arrow') . '</a><a class="btn btn-ghost" href="' . e(url($base . '/pdf/classement')) . '" target="_blank">' . icon('download') . 'PDF</a>';
include __DIR__ . '/_head.php';
$medal = ['gold' => 'Or', 'silver' => 'Argent', 'bronze' => 'Bronze'];
?>
<div class="stats">
  <div class="stat accent"><strong><?= (int) $done ?><small> / <?= (int) $total ?></small></strong><span>poules terminées</span></div>
  <div class="stat"><strong><?= (int) $totals['gold'] ?></strong><span>médailles d'or</span></div>
  <div class="stat"><strong><?= (int) $totals['silver'] ?></strong><span>argent</span></div>
  <div class="stat"><strong><?= (int) $totals['bronze'] ?></strong><span>bronze</span></div>
  <?php if ($tiebreaks): ?><a class="stat warn" style="text-decoration:none;color:inherit" href="<?= e(url($base . '/classements')) ?>"><strong><?= (int) $tiebreaks ?></strong><span>égalité(s) à départager</span></a><?php endif; ?>
</div>
<div class="toolbar"><div class="segmented"><a href="?"<?= $filter === '' ? ' aria-current="page"' : '' ?>>Toutes</a><a href="?etat=terminees"<?= $filter === 'terminees' ? ' aria-current="page"' : '' ?>>Terminées</a><a href="?etat=en-cours"<?= $filter === 'en-cours' ? ' aria-current="page"' : '' ?>>En cours</a></div></div>
<?php if (!$groups): ?><div class="empty"><strong>Aucun résultat</strong>Les résultats apparaissent dès que les combats sont validés par les commissaires.</div><?php endif; ?>
<?php foreach ($groups as $label => $list): ?>
  <div class="group-title"><h2><?= e($label) ?></h2><span class="muted small"><?= count($list) ?> poule(s)</span></div>
  <div class="grid grid-3">
    <?php foreach ($list as $p): $athletes = $p['athletes']; usort($athletes, fn ($x, $y) => [$x['final_rank'] ?? $x['computed_rank'] ?? 99, $x['seed']] <=> [$y['final_rank'] ?? $y['computed_rank'] ?? 99, $y['seed']]); $pct = $p['bouts_total'] ? round($p['bouts_done'] / $p['bouts_total'] * 100) : 0; ?>
      <article class="card">
        <div class="card-head" style="margin-bottom:.6rem"><div><h3 class="card-title"><?= e($p['weight_label'] ? $p['weight_label'] . ' kg' : preg_replace('/^.*· /', '', $p['label'])) ?></h3><span class="small muted">Poule <?= (int) $p['number'] ?> · Tapis <?= e($p['tatami_number'] ?? '—') ?></span></div>
          <?php if ($p['ranking_complete']): ?><span class="badge badge-green"><span class="dot"></span>Terminée</span><?php elseif ((int) $p['needs_tiebreak']): ?><span class="badge badge-orange">Égalité</span><?php else: ?><span class="badge"><?= (int) $p['bouts_done'] ?>/<?= (int) $p['bouts_total'] ?></span><?php endif; ?></div>
        <?php if (!$p['ranking_complete']): ?><div class="progress" style="margin-bottom:.8rem"><span style="width:<?= $pct ?>%"></span></div><?php endif; ?>
        <ol class="podium">
          <?php foreach ($athletes as $a): $rank = $a['final_rank'] ?? null; ?>
            <li><span class="rank"><?= $rank ? (int) $rank : '·' ?></span><?= avatar($photos[(int) $a['registration_id']] ?? null, $a['first_name'] . ' ' . $a['last_name']) ?>
              <span class="who"><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><small><?= e($a['club_name'] ?? $a['club_exception_label']) ?></small></span>
              <span class="small muted num"><?= (int) $a['wins'] ?> V</span></li>
          <?php endforeach; ?>
        </ol>
        <p style="margin:.8rem 0 0"><a class="small" href="<?= e(url($base . '/combats', ['pool_id' => $p['id']])) ?>">Voir les <?= (int) $p['bouts_total'] ?> combats →</a></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
