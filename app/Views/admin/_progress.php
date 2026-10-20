<?php /** @var array $progress @var array $tv */ use App\Services\TvTokenService;
$tvByTatami = []; foreach ($tv ?? [] as $t) { $tvByTatami[$t['tatami_number']][] = $t; } ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Tapis</th><th>Poules</th><th>Combats</th><th style="min-width:160px">Avancement</th><th>Restants</th><th>Temps restant estimé</th><th>Écran TV</th></tr></thead>
    <tbody>
    <?php foreach ($progress as $t): $pct = $t['bouts'] ? round($t['done'] / $t['bouts'] * 100) : 0;
      $screens = $tvByTatami[$t['number']] ?? []; $statuses = array_map(fn ($x) => TvTokenService::status($x), $screens);
      $online = in_array('online', $statuses, true); $active = array_intersect($statuses, ['online', 'idle']); ?>
      <tr>
        <td><strong>Tapis <?= (int) $t['number'] ?></strong><?= (int) $t['is_active'] ? '' : ' <span class="badge">inactif</span>' ?></td>
        <td class="num"><?= (int) $t['pools'] ?></td>
        <td class="num"><?= (int) $t['done'] ?> / <?= (int) $t['bouts'] ?></td>
        <td><div class="progress"><span style="width:<?= $pct ?>%"></span></div><span class="small muted"><?= $pct ?> %</span></td>
        <td class="num"><?= (int) $t['remaining'] ?></td>
        <td class="num"><?= e(hours_label((int) $t['est_remaining'])) ?></td>
        <td><?php if ($online): ?><span class="badge badge-green"><span class="dot"></span> connecté</span><?php elseif ($active): ?><span class="badge badge-orange">lien actif, non connecté</span><?php else: ?><span class="badge">aucun lien actif</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
