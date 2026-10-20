<?php use App\Services\BoutService; ?>
<?php foreach ($tatamis as $k => $t): $start = strtotime($c['event_date'] . ' ' . $c['start_time']); $elapsed = 0; ?>
  <h2>Tapis <?= (int) $t['number'] ?> — <?= count($t['bouts']) ?> combats · durée estimée <?= e(hours_label(array_sum(array_map(fn ($b) => (int) $b['duration_seconds'] + $gap, $t['bouts'])))) ?></h2>
  <table class="small"><thead><tr><th style="width:5%">Ordre</th><th style="width:7%">Estim.</th><th style="width:20%">Poule</th><th>Combattant 1</th><th>Combattant 2</th><th style="width:7%">Durée</th><th style="width:10%">État</th></tr></thead><tbody>
  <?php foreach ($t['bouts'] as $b): ?><tr><td class="num"><?= (int) $b['order_on_tatami'] ?></td><td class="num">+<?= e(hours_label($elapsed)) ?></td><td><?= e($b['pool_number'] . ' · ' . $b['pool_label']) ?></td><td><?= e($b['a_name']) ?><br><span class="muted"><?= e($b['a_club']) ?></span></td><td><?= e($b['b_name']) ?><br><span class="muted"><?= e($b['b_club']) ?></span></td><td class="num"><?= e(duration_label((int) $b['duration_seconds'])) ?></td><td><?= e(BoutService::STATUS[$b['status']]) ?></td></tr><?php $elapsed += (int) $b['duration_seconds'] + $gap; endforeach; ?>
  </tbody></table>
  <?php if ($k < count($tatamis) - 1): ?><div class="break"></div><?php endif; ?>
<?php endforeach; ?>
