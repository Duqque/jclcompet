<?php
/**
 * Feuilles de poule : les poules d'un même tapis s'enchaînent sur la page tant qu'une poule entière
 * (grille + ordre des combats) y tient ; sinon elle passe à la page suivante. Nouvelle page à chaque tapis.
 * ?vierge=1 : feuilles sans résultats, à remplir à la main.
 */
$blank = !empty($_GET['vierge']);
$lastMat = null;
?>
<?php if (!$pools): ?><p>Aucune poule pour cette sélection.</p><?php endif; ?>
<?php foreach ($pools as $p):
  $mat = (string) ($p['tatami_number'] ?? '—');
  if ($lastMat !== null && $mat !== $lastMat): ?><div class="break"></div><?php endif;
  if ($mat !== $lastMat): $lastMat = $mat; ?><p class="eyebrow" style="margin:0 0 2mm">Tapis <?= e($mat) ?></p><?php endif;
  include __DIR__ . '/_sheet.php';
endforeach; ?>
