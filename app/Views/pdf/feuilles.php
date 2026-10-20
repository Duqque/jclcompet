<?php /** Feuilles de poule : une ou deux par page, regroupées par tapis. */ $per = $opt['per_page'] >= 2 ? 2 : 1; ?>
<?php if (!$pools): ?><p>Aucune poule pour cette sélection.</p><?php endif; ?>
<?php foreach (array_chunk($pools, $per) as $k => $chunk): ?>
  <?php foreach ($chunk as $p) { include __DIR__ . '/_sheet.php'; } ?>
  <?php if ($k < (int) ceil(count($pools) / $per) - 1): ?><div class="break"></div><?php endif; ?>
<?php endforeach; ?>
