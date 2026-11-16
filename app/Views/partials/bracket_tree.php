<?php
/**
 * Arbre d'un tableau, façon feuille officielle : chaque combattant sur une ligne, le vainqueur avance sur la ligne du
 * tour suivant avec le score dessous (vainqueur\perdant). Positions absolues : même dessin à l'écran et dans le PDF.
 * @var array $tree  BracketService::drawings() : cols, rows, items, joins, title
 * @var float $W     largeur d'une colonne
 * @var float $H     hauteur d'une ligne
 * @var string $u    unité ('px' ou 'mm')
 * @var bool $pdf    rendu PDF (pas de liens ni d'animation)
 * @var ?callable $boutHref lien vers un combat
 */
$pdf = $pdf ?? false;
$boutHref = $boutHref ?? fn (array $b) => null;
$L = $H * 0.56; // hauteur de la ligne dans la rangée : nom au-dessus, score en dessous
$px = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . $u;
$finalCol = $tree['cols'] - 1;
?>
<div class="bt<?= $pdf ? ' bt-pdf' : '' ?>" style="width:<?= $px($tree['cols'] * $W + $W * .35) ?>;height:<?= $px($tree['rows'] * $H + $H * .3) ?>">
  <?php foreach ($tree['joins'] as $j): ?>
    <div class="bt-join" style="left:<?= $px($j['col'] * $W) ?>;top:<?= $px($j['y1'] * $H + $L) ?>;height:<?= $px(($j['y2'] - $j['y1']) * $H) ?>"></div>
  <?php endforeach; ?>
  <?php foreach ($tree['items'] as $it):
      $left = $it['col'] * $W;
      $width = $it['span'] * $W;
      $isRoot = !$it['leaf'] && $it['col'] === $finalCol;
      $cls = 'bt-item' . ($it['leaf'] ? ' is-leaf' : ' is-node') . ($isRoot ? ' is-root' : '');
      $live = !$it['leaf'] && in_array($it['status'] ?? null, ['in_progress', 'finished'], true);
      $a = $it['leaf'] ? ($it['athlete'] ?? null) : ($it['winner'] ?? null);
      if ($live) {
          $cls .= ' is-live';
      }
      if ($it['leaf'] && $it['type'] === 'bye') {
          $cls .= ' is-bye';
      }
      $href = !$pdf && !$it['leaf'] && !empty($it['bout_id']) ? $boutHref(['id' => $it['bout_id']]) : null; ?>
    <div class="<?= $cls ?>" style="left:<?= $px($left) ?>;top:<?= $px($it['y'] * $H) ?>;width:<?= $px($width + ($isRoot ? $W * .3 : 0)) ?>;height:<?= $px($H) ?>">
      <div class="bt-name" style="height:<?= $px($L) ?>">
        <?php if ($a): ?>
          <?php if ($it['leaf'] && !$pdf && !empty($a['club_logo'])): ?><span class="bt-logo"><img src="<?= e($a['club_logo']) ?>" alt="" loading="lazy"></span><?php endif; ?>
          <span class="bt-txt"><strong><?= e($a['last_name']) ?></strong> <?= e($a['first_name']) ?><?php if ($it['leaf'] && !empty($it['seed'])): ?> <span class="bt-seed">TS<?= (int) $it['seed'] ?></span><?php endif; ?><?php if ($pdf && $it['leaf']): ?> <span class="bt-club"><?= e($a['club_short'] ?? '') ?></span><?php endif; ?></span>
        <?php elseif ($it['leaf'] && $it['type'] === 'pending'): ?>
          <span class="bt-txt bt-wait"><?= e($it['text'] ?? '') ?></span>
        <?php endif; ?>
      </div>
      <div class="bt-line"></div>
      <div class="bt-sub">
        <?php if ($it['leaf'] && $a && !$pdf): ?><?= e($a['club_short'] ?? '') ?>
        <?php elseif (!$it['leaf'] && !empty($it['score'])): ?><span class="bt-score"><?= e($it['score']) ?></span>
        <?php elseif ($live): ?><span class="bt-live">En cours · tapis <?= (int) $it['mat'] ?></span>
        <?php elseif (!$it['leaf'] && ($it['status'] ?? null) === 'scheduled'): ?><span class="bt-next">Tapis <?= (int) $it['mat'] ?></span><?php endif; ?>
        <?php if ($href): ?><a class="bt-link" href="<?= e($href) ?>" aria-label="Détail du combat <?= e($it['label']) ?>">›</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
