<?php
/**
 * Tableau à double repêchage ou tableau final croisé (BracketService::view()), lisible sur téléphone :
 * un onglet par tour, une colonne par écran (défilement horizontal aimanté) ; toutes les colonnes côte à côte sur ordinateur.
 * Puis le classement complet de la catégorie (1, 2, 3, 3, 5, 5, 7, 7, puis NC).
 * @var array $p poule issue de PoolService::sheets() avec $p['bracket']
 * @var ?callable $boutHref lien vers un combat (admin / commissaire)
 * @var ?int $highlightClub club à surligner (espace coach)
 */
use App\Services\CategoryService;

$bk = $p['bracket'];
$boutHref = $boutHref ?? fn (array $b) => null;
$highlightClub = $highlightClub ?? null;
$uid = 'bk' . (int) $p['id'];
$methods = ['ippon' => 'Ippon', 'wazaari' => 'Waza-ari', 'yuko' => 'Yuko', 'decision' => 'Décision', 'hansoku_make' => 'Hansoku', 'fusen_gachi' => 'Forfait', 'kiken_gachi' => 'Abandon', 'shido' => 'Shido'];
// NC (non classé) seulement quand le tableau est terminé ; avant, « – » pour ceux dont la place n'est pas encore connue.
$placeLabel = fn (?int $pl) => $pl === null ? ($bk['complete'] ? 'NC' : '–') : ($pl === 1 ? '1er' : $pl . 'e');
$medal = fn (?int $pl) => match ($pl) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
$started = false;
foreach ($bk['sections'] as $s) {
    foreach ($s['nodes'] as $n) {
        if ($n['bout'] && $n['bout']['status'] !== 'scheduled') {
            $started = true;
        }
    }
}
$state = $bk['complete'] ? ['badge-green', 'Terminé'] : ($started ? ['badge-red', 'En cours'] : ['', 'À venir']);
// Onglet ouvert par défaut : le premier tour qui a encore un combat à disputer
$current = 0;
foreach ($bk['sections'] as $i => $s) {
    foreach ($s['nodes'] as $n) {
        if ($n['fight'] && (!$n['bout'] || $n['bout']['status'] !== 'validated')) {
            $current = $i;
            break 2;
        }
    }
}
$side = function (array $node, string $s) use ($methods, $highlightClub): string {
    $x = $node[$s];
    $win = $node['winner'] === $s;
    $lost = $node['winner'] !== null && !$win && $node['fight'];
    $belt = belt($s === 'a' ? 'red' : 'white');
    if ($x['type'] !== 'athlete' || !$x['athlete']) {
        return '<div class="bk-row is-empty">' . $belt . '<span class="bk-who"><em>' . e($x['text'] ?? '') . '</em></span></div>';
    }
    $a = $x['athlete'];
    $score = '';
    if ($node['bout'] && $node['bout']['status'] === 'validated') {
        $real = $node['bout']['flip'] ? ($s === 'a' ? 'b' : 'a') : $s;
        $sc = $node['bout']['score'][$real];
        $score = '<span class="bk-score">' . $sc['ippon'] . $sc['wazaari'] . $sc['yuko'] . ($sc['shido'] ? '<small>-' . $sc['shido'] . '</small>' : '') . '</span>';
    }
    $mine = $highlightClub && ($a['club'] ?? null) !== null && isset($a['club_id']) && (int) $a['club_id'] === (int) $highlightClub;
    $logo = $a['club_logo'] ? '<span class="bk-logo"><img src="' . e($a['club_logo']) . '" alt="" loading="lazy"></span>' : '<span class="bk-logo"></span>';
    return '<div class="bk-row' . ($win ? ' is-win' : '') . ($lost ? ' is-lost' : '') . ($mine ? ' is-mine' : '') . '">' . $belt . $logo
        . '<span class="bk-who"><strong>' . e($a['last_name']) . '</strong> <span>' . e($a['first_name']) . '</span>'
        . ($x['seed'] ? ' <span class="bk-seed" title="Tête de série">TS' . (int) $x['seed'] . '</span>' : '')
        . '<small>' . e($a['club_short'] ?? '') . '</small></span>' . $score . ($win ? '<span class="bk-check">' . icon('check') . '</span>' : '') . '</div>';
};
?>
<article class="sheet bk" id="poule-<?= (int) $p['id'] ?>">
  <header class="sheet-head">
    <div>
      <p class="eyebrow"><?= e(CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></p>
      <h3><?= $bk['kind'] === 'final_block' ? 'Tableau final' : 'Tableau · double repêchage' ?></h3>
    </div>
    <div class="sheet-meta">
      <span class="badge badge-ink">Tapis <?= e($p['tatami_number'] ?? '—') ?></span>
      <span class="badge"><?= (int) $bk['count'] ?> judokas</span>
      <span class="badge <?= $state[0] ?>"><?= $state[1] ?></span>
    </div>
  </header>
  <?php if (!$bk['sections']): ?>
    <p class="muted">Seul(e) dans sa catégorie : classé(e) 1er sans combat.</p>
  <?php else: ?>
  <div class="bk-bar">
    <div class="bk-switch" role="group" aria-label="Affichage du tableau">
      <button type="button" data-bk-view="tree" aria-pressed="true">Arbre</button><button type="button" data-bk-view="rounds" aria-pressed="false">Par tours</button>
    </div>
    <div class="bk-zoom" data-bk-zoom>
      <button type="button" data-z="-" aria-label="Dézoomer">−</button><button type="button" data-z="fit">Tout voir</button><button type="button" data-z="+" aria-label="Zoomer">+</button>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e($bk['pdf_url']) ?>" target="_blank" rel="noopener"><?= icon('download') ?>PDF à jour</a>
  </div>
  <div class="bk-pane" data-bk-pane="tree">
    <?php foreach ($bk['drawings'] as $tree): ?>
      <section class="bt-block">
        <h4><?= e($tree['title']) ?></h4>
        <div class="bt-scroll"><div class="bt-zoom" data-bt-zoom><?php $W = 196; $H = 46; $u = 'px'; $pdf = false; include __DIR__ . '/bracket_tree.php'; ?></div></div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="bk-pane" data-bk-pane="rounds" hidden>
  <nav class="bk-tabs" aria-label="Tours du tableau">
    <?php foreach ($bk['sections'] as $i => $s): ?><button type="button" data-bk-tab="<?= e($uid . '-' . $s['key']) ?>"<?= $i === $current ? ' aria-current="true"' : '' ?>><?= e($s['title']) ?></button><?php endforeach; ?>
  </nav>
  <div class="bk-cols" data-bk-cols data-bk-start="<?= e($uid . '-' . $bk['sections'][$current]['key']) ?>">
    <?php foreach ($bk['sections'] as $s): ?>
      <section class="bk-col" id="<?= e($uid . '-' . $s['key']) ?>">
        <h4><?= e($s['title']) ?></h4>
        <?php if (!empty($s['note'])): ?><p class="bk-note"><?= e($s['note']) ?></p><?php endif; ?>
        <?php if (!$s['nodes']): ?><p class="bk-note">Après les quarts de finale.</p><?php endif; ?>
        <?php foreach ($s['nodes'] as $n):
            if (!$n['fight'] && $n['a']['type'] !== 'pending' && $n['b']['type'] !== 'pending' && $s['key'] !== 'F') {
                continue; // tour offert (exempt) : pas de carte
            }
            $b = $n['bout'];
            $live = $b && in_array($b['status'], ['in_progress', 'finished'], true);
            $href = $b ? $boutHref(['id' => $b['id']]) : null; ?>
          <div class="bk-match<?= $live ? ' is-live' : '' ?><?= $b && $b['status'] === 'validated' ? ' is-done' : '' ?>">
            <div class="bk-match-head">
              <span><?= e($n['label']) ?></span>
              <?php if ($live): ?><span class="bk-live">En cours</span>
              <?php elseif ($b && $b['status'] === 'validated'): ?><span><?= e($methods[$b['method']] ?? '') ?></span>
              <?php elseif ($b): ?><span>Tapis <?= (int) $b['tatami'] ?> · n° <?= (int) $b['order_on_tatami'] ?></span><?php endif; ?>
            </div>
            <?= $side($n, 'a') ?><?= $side($n, 'b') ?>
            <?php if ($href): ?><a class="bk-link" href="<?= e($href) ?>">Détail du combat</a><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
  </div>
  <?php endif; ?>
  <section class="bk-ranking">
    <h4>Classement de la catégorie</h4>
    <ol>
      <?php foreach ($bk['ranking'] as $r): $a = $r['athlete']; if (!$a) { continue; } ?>
        <li class="<?= $medal($r['place']) ?>"><span class="bk-place"><?= e($placeLabel($r['place'])) ?></span><span class="bk-who"><strong><?= e($a['last_name']) ?></strong> <?= e($a['first_name']) ?><small><?= e($a['club_short'] ?? '') ?></small></span></li>
      <?php endforeach; ?>
    </ol>
    <p class="bk-note"><?= $bk['complete'] ? 'NC : non classé.' : 'Places connues au fil des combats ; « – » : place pas encore attribuée. Classement définitif à la fin du tableau.' ?></p>
  </section>
</article>
