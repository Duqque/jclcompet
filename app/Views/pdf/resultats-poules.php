<?php
/** Résultats complets de chaque poule, dans l'ordre des âges, puis des poids. */
use App\Services\CategoryService;
$current = null;
?>
<?php if (!$pools): ?><p>Aucune poule.</p><?php endif; ?>
<?php foreach ($pools as $p):
  $label = CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex']);
  if ($label !== $current): $current = $label; ?>
    <h2 style="margin-top:2mm"><?= e($label) ?></h2>
  <?php endif; include __DIR__ . '/_sheet.php'; ?>
<?php endforeach; ?>
