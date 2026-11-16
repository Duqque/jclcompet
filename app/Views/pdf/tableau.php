<?php
/** Tableau d'une catégorie en PDF (arbre façon feuille officielle + classement), généré à la demande : toujours à jour. */
use App\Services\CategoryService;

$bk = $bracket;
$placeLabel = fn (?int $pl) => $pl === null ? ($bk['complete'] ? 'NC' : '–') : ($pl === 1 ? '1er' : $pl . 'e');
?>
<style>
  .bt { position: relative; font-size: 7.4pt; }
  .bt-item { position: absolute; }
  .bt-name { position: relative; overflow: hidden; white-space: nowrap; }
  .bt-name .bt-txt { display: block; position: absolute; bottom: .3mm; left: 1.2mm; right: .8mm; overflow: hidden; white-space: nowrap; }
  .bt-club { color: #71717a; font-size: 6pt; }
  .bt-line { border-top: .5pt solid #a1a1aa; height: 0; }
  .bt-item.is-node .bt-line { border-top: .7pt solid #18181b; }
  .bt-item.is-root .bt-line { border-top: 1.6pt solid #d4a72c; }
  .bt-item.is-bye .bt-line { border-top: .4pt dashed #d4d4d8; }
  .bt-join { position: absolute; width: 0; border-left: .7pt solid #18181b; }
  .bt-sub { padding: .3mm 1mm 0 1.4mm; font-size: 6.2pt; color: #71717a; white-space: nowrap; overflow: hidden; }
  .bt-score { font-weight: 700; color: #18181b; }
  .bt-live { color: #c21a2c; font-weight: 700; }
  .bt-seed { color: #c21a2c; font-weight: 700; font-size: 6pt; }
  .bt-wait { color: #a1a1aa; font-style: italic; }
  .bt-logo, .bt-link { display: none; }
  .tree-title { font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .5pt; color: #71717a; margin: 0 0 1.5mm; }
  .rank { width: 100%; border-collapse: collapse; }
  .rank-box { border: .6pt solid #e4e4e7; border-radius: 3mm; padding: 2.5mm 3mm; }
  .rank td { border-bottom: .4pt solid #e4e4e7; padding: .8mm 1.2mm; font-size: 7.6pt; }
  .rank td.pl { width: 10mm; font-weight: 700; text-align: center; }
</style>
<?php foreach ($bk['drawings'] as $i => $tree):
    $ratio = ($tree['rows'] + .4) * max(34, min(46, 900 / max(1, $tree['rows']))) / (($tree['cols'] + .4) * 190);
    $wmm = $i === 0 ? min(196, 126 / max(.01, $ratio)) : min(270, 160 / max(.01, $ratio)); ?>
  <div class="<?= $i < count($bk['drawings']) - 1 ? 'break' : '' ?>">
    <p class="tree-title"><?= e($tree['title']) ?></p>
    <?php if ($i === 0): ?>
      <table style="width:100%;border-collapse:collapse"><tr>
        <td style="vertical-align:top;border:0;padding:0"><img src="<?= \App\Services\BracketImage::dataUri($tree) ?>" alt="" style="width:<?= round($wmm, 1) ?>mm"></td>
        <td style="vertical-align:bottom;border:0;padding:0;width:68mm">
          <div class="rank-box">
            <p class="tree-title" style="margin-bottom:1mm">Classement</p>
            <table class="rank"><tbody>
              <?php $shown = array_values(array_filter($bk['ranking'], fn ($r) => $r['athlete'] && $r['place'] !== null));
              foreach ([1, 2, 3, 3, 5, 5, 7, 7] as $k => $pl): $r = null;
                  foreach ($shown as $x) { if ($x['place'] === $pl && !in_array($x['athlete']['registration_id'], $used ?? [], true)) { $r = $x; $used[] = $x['athlete']['registration_id']; break; } } ?>
                <tr><td class="pl"><?= $pl === 1 ? '1er' : $pl . 'e' ?></td><td><?php if ($r): ?><strong><?= e($r['athlete']['last_name']) ?></strong> <?= e($r['athlete']['first_name']) ?><br><span class="muted"><?= e($r['athlete']['club_short'] ?? '') ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
              <?php endforeach; unset($used); ?>
            </tbody></table>
          </div>
        </td>
      </tr></table>
    <?php else: ?>
      <img src="<?= \App\Services\BracketImage::dataUri($tree) ?>" alt="" style="width:<?= round($wmm, 1) ?>mm">
    <?php endif; ?>
  </div>
<?php endforeach; ?>
