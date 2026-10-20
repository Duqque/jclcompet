<?php /** @var array $history */ $cats = \App\Services\ContentService::historyCategories($history); ?>
<div class="table-wrap">
  <table class="history">
    <thead><tr><th scope="col">Saisons</th><?php foreach ($history as $h): ?><th scope="col"><?= e($h['season_label']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <tr><th scope="row">Nombre de clubs</th><?php foreach ($history as $h): ?><td><?= $h['clubs_count'] === null ? '—' : (int) $h['clubs_count'] ?></td><?php endforeach; ?></tr>
      <?php foreach ($cats as $label): ?>
        <tr><th scope="row"><?= e($label) ?></th><?php foreach ($history as $h): $v = null; foreach ($h['categories'] as $cc) { if ($cc['label'] === $label) { $v = $cc['count']; } } ?><td><?= $v === null ? '—' : (int) $v ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <tr class="total"><th scope="row">Total</th><?php foreach ($history as $h): ?><td><?= $h['total_participants'] === null ? '—' : (int) $h['total_participants'] ?></td><?php endforeach; ?></tr>
      <tr><th scope="row">Combats arbitrés</th><?php foreach ($history as $h): ?><td><?= $h['bouts_count'] === null ? 'ND' : (int) $h['bouts_count'] ?></td><?php endforeach; ?></tr>
    </tbody>
  </table>
</div>
