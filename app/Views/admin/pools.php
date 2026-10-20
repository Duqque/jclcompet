<?php
use App\Services\PoolGenerator;
$u = url('/admin/competitions/' . $c['id'] . '/poules');
$api = url('/api/admin/competitions/' . $c['id'] . '/poules');
$heading = 'Poules'; include __DIR__ . '/_head.php';
$draft = $g && $g['status'] === 'draft';
$validated = $g && $g['status'] === 'validated';
$post = fn (string $op, string $label, string $cls = 'btn-ghost', array $hidden = [], ?string $confirm = null) =>
    '<form method="post" action="' . e($u) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="op" value="' . e($op) . '">'
    . implode('', array_map(fn ($k, $v) => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($hidden), $hidden))
    . '<button class="btn btn-sm ' . $cls . '" type="submit">' . $label . '</button></form>';
$allPools = $pools;
?>
<?php
$started = $loads && array_sum(array_column($loads['tatamis'], 'done')) > 0;
$canGenerate = !$validated || !$started;
$assigned = $g && $pools && !array_filter($pools, fn ($p) => !$p['tatami_id']);
$pdf = url('/admin/competitions/' . $c['id'] . '/pdf/feuilles');
$steps = [
    ['Générer', $g ? count($pools) . ' poules · ' . (int) $participants . ' judokas présents' : (int) $participants . ' judokas présents et confirmés', (bool) $g],
    ['Répartir sur les tapis', $assigned ? 'Toutes les poules ont un tapis' : 'Équilibre la durée entre les tapis', $assigned],
    ['Ajuster', 'Glisser-déposer judokas et poules', $validated],
    ['Valider', $validated ? 'Combats créés chez les commissaires' : 'Crée les combats', $validated],
    ['Imprimer', 'Feuilles par poule ou par tapis', false],
];
?>
<section class="card pool-flow">
  <ol class="flow">
    <?php foreach ($steps as $i => [$t, $d, $ok]): ?><li class="<?= $ok ? 'ok' : '' ?>"><span class="n"><?= $ok ? icon('check') : $i + 1 ?></span><span><strong><?= e($t) ?></strong><small><?= e($d) ?></small></span></li><?php endforeach; ?>
  </ol>
  <div class="flow-actions">
    <?php if ($canGenerate): ?><?= $post('generate', icon('refresh') . ($g ? 'Mettre à jour les poules' : 'Générer les poules'), 'btn-red', [], $validated ? 'Recomposer les poules ? Les combats non commencés seront recréés (les poules verrouillées sont conservées).' : null) ?><?php endif; ?>
    <?php if ($g): ?><?= $post('distribute', icon('grid') . 'Répartir sur les tapis', $assigned ? 'btn-ghost' : 'btn-red') ?><?php endif; ?>
    <?php if ($g): ?><a class="btn btn-sm btn-ghost" href="?vue=tapis"><?= icon('sliders') ?>Ajuster par glisser-déposer</a><?php endif; ?>
    <?php if ($draft): ?>
      <form method="post" action="<?= e($u) ?>" class="btn-group" data-confirm="Valider la composition et la répartition ? Les combats seront créés avec la configuration de chaque catégorie et apparaîtront chez les commissaires.">
        <?= csrf_field() ?><input type="hidden" name="op" value="validate">
        <?php if ($unplaced): ?><label class="check"><input type="checkbox" name="acknowledge_unplaced" value="1"> Laisser <?= count($unplaced) ?> athlète(s) hors poule</label><?php endif; ?>
        <button class="btn btn-sm btn-green" type="submit"><?= icon('check') ?>Valider les poules</button>
      </form>
    <?php endif; ?>
    <?php if ($g): ?>
      <span class="flow-sep"></span>
      <a class="btn btn-sm btn-ghost" href="<?= e($pdf) ?>" target="_blank" rel="noopener"><?= icon('download') ?>Toutes les feuilles</a>
      <?php foreach ($tatamis as $t): ?><a class="btn btn-sm btn-soft" href="<?= e($pdf . '?tatami_id=' . (int) $t['id']) ?>" target="_blank" rel="noopener">Tapis <?= (int) $t['number'] ?></a><?php endforeach; ?>
    <?php endif; ?>
    <?php if ($g && $canGenerate): ?><?= $post('cancel', 'Annuler la génération', 'btn-danger', [], 'Annuler la génération ? Les poules et les combats non commencés seront supprimés.') ?><?php endif; ?>
  </div>
  <p class="small muted" style="margin:.6rem 0 0">Seuls les judokas <strong>présents</strong> et confirmés sont placés ; âges et sexes ne sont jamais mélangés ; les poids sont rapprochés et les clubs séparés. « Mettre à jour » recalcule les poules à chaque clic tant qu'aucun combat n'a commencé (les poules verrouillées restent telles quelles).<?= !$g ? ' <a href="' . e(url('/accueil/' . $c['id'])) . '">Contrôler les présences</a>.' : '' ?></p>
</section>

<?php foreach (($report['groups'] ?? []) as $grp): if ($grp['status'] !== 'needs_decision') continue; ?>
  <section class="card" style="border-left:6px solid var(--orange);margin-bottom:1rem">
    <h2 style="font-size:1.05rem;margin-bottom:.3rem"><?= e($grp['label']) ?> — décision nécessaire</h2>
    <p class="small"><?= e($grp['issue']['reason']) ?> Aucun athlète n'a été écarté : choisissez une solution.</p>
    <div class="btn-group">
      <?php foreach ($grp['issue']['alternatives'] as $alt): ?>
        <?php if ($alt['type'] === 'sizes'): ?><?= $post('decide', 'Accepter : ' . e($alt['label']), 'btn-soft', ['group_key' => $grp['key'], 'type' => 'sizes', 'sizes' => implode(',', $alt['sizes'])]) ?>
        <?php elseif ($alt['type'] === 'unplaced'): ?><?= $post('decide', e($alt['label']), 'btn-ghost', ['group_key' => $grp['key'], 'type' => 'unplaced'], 'Laisser ces athlètes hors poule ? La décision est consignée au journal.') ?>
        <?php elseif ($alt['type'] === 'merge'): ?>
          <form method="post" action="<?= e($u) ?>" class="btn-group"><?= csrf_field() ?><input type="hidden" name="op" value="decide"><input type="hidden" name="type" value="merge"><input type="hidden" name="group_key" value="<?= e($grp['key']) ?>">
            <label class="sr-only" for="m<?= e(md5($grp['key'])) ?>">Regrouper avec</label>
            <select id="m<?= e(md5($grp['key'])) ?>" name="into" style="max-width:280px;min-height:34px"><?php foreach ($groups as $k => $label): if ($k === $grp['key'] || explode('|', $k)[1] !== explode('|', $grp['key'])[1]) continue; ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost btn-sm" type="submit">Regrouper (exception explicite)</button></form>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($warnings): ?>
<details class="card" style="margin-bottom:1rem"><summary><strong><?= count($warnings) ?> point(s) d'attention</strong> (écarts de poids, même club, tailles, durées)</summary>
  <ul class="small" style="margin:.6rem 0 0"><?php foreach ($warnings as $w): ?><li class="<?= $w['level'] === 'error' ? 'field-error' : '' ?>"><?= e($w['message']) ?></li><?php endforeach; ?></ul></details>
<?php endif; ?>

<?php if ($loads && $vue === 'tapis'): ?>
<section class="card" style="margin-bottom:1.2rem">
  <div class="card-head"><h2 style="margin:0;font-size:1.1rem">Charge des tapis</h2><span class="small muted">Écart entre le tapis le plus et le moins chargé : <strong><?= e(hours_label((int) $loads['spread_seconds'])) ?></strong> · battement <?= (int) $loads['gap_seconds'] ?> s entre combats</span></div>
  <div class="table-wrap"><table class="table-compact">
    <thead><tr><th>Tapis</th><th>Poules</th><th>Combats</th><th>Temps estimé</th><th>Avancement</th><th>Restants</th><th>Temps restant</th></tr></thead>
    <tbody><?php foreach ($loads['tatamis'] as $l): $pct = $l['bouts'] ? round($l['done'] / $l['bouts'] * 100) : 0; ?>
      <tr><td><strong>Tapis <?= (int) $l['tatami']['number'] ?></strong></td><td class="num"><?= $l['pools'] ?></td><td class="num"><?= $l['bouts'] ?></td><td class="num"><?= e(hours_label($l['seconds'])) ?></td>
        <td style="min-width:140px"><div class="progress green"><span style="width:<?= $pct ?>%"></span></div><span class="small muted"><?= $pct ?> %</span></td><td class="num"><?= $l['bouts'] - $l['done'] ?></td><td class="num"><?= e(hours_label($l['remaining_seconds'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($g):
  $cid = (int) $c['id'];
  $vq = fn (string $v) => '?' . http_build_query(array_filter(['vue' => $v, 'q' => $q]));
  $boutHref = fn (array $b) => url('/admin/competitions/' . $cid . '/combats/' . $b['id']);
  $pdfHref = fn (array $p) => url('/admin/competitions/' . $cid . '/pdf/feuilles', ['pool_id' => $p['id']]);
  $byCat = [];
  foreach ($shown as $p) { $byCat[\App\Services\CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])][] = $p; } ?>
<form method="get" class="toolbar" role="search">
  <input type="hidden" name="vue" value="<?= e($vue) ?>">
  <label class="search"><?= icon('search') ?><span class="sr-only">Retrouver une poule</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom d'un judoka, club ou n° de poule…"></label>
  <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e($vq($vue) . '&q=') ?>"><?= icon('x') ?>Effacer</a><span class="small muted"><?= count($shown) ?> poule(s) trouvée(s)</span><?php endif; ?>
  <span style="flex:1"></span>
  <div class="segmented" role="group" aria-label="Affichage des poules">
    <a href="<?= e($vq('feuilles')) ?>"<?= $vue === 'feuilles' ? ' aria-current="page"' : '' ?>>Feuilles de poule</a>
    <a href="<?= e($vq('categories')) ?>"<?= $vue === 'categories' ? ' aria-current="page"' : '' ?>>Cartes</a>
    <a href="<?= e($vq('liste')) ?>"<?= $vue === 'liste' ? ' aria-current="page"' : '' ?>>Liste</a>
    <a href="<?= e($vq('tapis')) ?>"<?= $vue === 'tapis' ? ' aria-current="page"' : '' ?>>Répartition des tapis</a></div>
</form>
<?php if ($vue !== 'tapis' && !$shown): ?>
  <div class="empty"><strong>Aucune poule trouvée</strong>Vérifiez l'orthographe du nom, ou cherchez par club ou numéro de poule.</div>
<?php elseif ($vue === 'feuilles'): ?>
  <?php foreach ($byCat as $label => $list): ?>
    <div class="group-title"><h2><?= e($label) ?></h2><span class="muted small"><?= count($list) ?> poule(s) · <?= array_sum(array_column($list, 'size')) ?> judokas · <?= array_sum(array_column($list, 'bouts_total')) ?> combats</span></div>
    <div class="sheets"><?php foreach ($list as $p): include BASE_PATH . '/app/Views/partials/pool_sheet.php'; endforeach; ?></div>
  <?php endforeach; ?>
  <p class="small muted">Cliquez sur une case ou sur un combat de l'ordre pour ouvrir son détail et corriger le résultat (motif obligatoire, historique conservé).</p>
<?php elseif ($vue === 'liste'): ?>
  <section class="card"><div class="table-wrap"><table class="table-compact">
    <thead><tr><th>Poule</th><th>Catégorie</th><th>Tapis</th><th>Judokas</th><th class="num">Combats</th><th>État</th><th></th></tr></thead>
    <tbody><?php foreach ($shown as $p): $done = $p['bouts_total'] && $p['bouts_done'] >= $p['bouts_total']; ?>
      <tr><td class="nowrap"><strong>Poule <?= (int) $p['number'] ?></strong></td>
        <td><?= e(\App\Services\CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex'])) ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></td>
        <td class="nowrap">Tapis <?= e($p['tatami_number'] ?? '—') ?></td>
        <td class="small"><?php foreach ($p['athletes'] as $i => $a): ?><span class="pool-li"><span class="muted"><?= $i + 1 ?>.</span> <?php $logo = $a['club_logo']; $name = $a['club_name'] ?? '?'; $size = 18; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?> <?= e(mb_strtoupper($a['last_name']) . ' ' . $a['first_name']) ?></span><?php endforeach; ?></td>
        <td class="num"><?= (int) $p['bouts_done'] ?>/<?= (int) $p['bouts_total'] ?></td>
        <td><span class="badge <?= $done ? 'badge-green' : ($p['bouts_started'] ? 'badge-red' : '') ?>"><?= $done ? 'Terminée' : ($p['bouts_started'] ? 'En cours' : 'À venir') ?></span></td>
        <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e('?' . http_build_query(['vue' => 'feuilles', 'q' => (string) $p['number']])) ?>">Feuille</a><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/competitions/' . $cid . '/combats', ['pool_id' => $p['id']])) ?>">Combats</a></td></tr>
    <?php endforeach; ?></tbody></table></div></section>
<?php elseif ($vue === 'categories'): ?>
  <?php foreach ($byCat as $label => $list): ?>
    <div class="group-title"><h2><?= e($label) ?></h2><span class="muted small"><?= count($list) ?> poule(s) · <?= array_sum(array_column($list, 'size')) ?> judokas · <?= array_sum(array_column($list, 'bouts_total')) ?> combats</span></div>
    <div class="pool-board">
      <?php foreach ($list as $p): ?>
        <article class="pool<?= (int) $p['is_locked'] ? ' locked' : '' ?><?= $p['bouts_started'] ? ' started' : '' ?>">
          <header><strong>Poule <?= (int) $p['number'] ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></strong><span class="badge badge-ink">Tapis <?= e($p['tatami_number'] ?? '—') ?></span></header>
          <ol><?php foreach ($p['athletes'] as $a): ?><li><?= e($a['last_name'] . ' ' . $a['first_name']) ?> <?= $a['reference_weight_kg'] !== null ? '<span class="small muted">' . e(kg($a['reference_weight_kg'])) . '</span>' : '' ?><br><span class="club"><?php $logo = $a['club_logo']; $name = $a['club_name'] ?? '?'; $size = 18; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?> <?= e($a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label'])) ?></span></li><?php endforeach; ?></ol>
          <footer><span><?= (int) $p['size'] ?> judokas · <?= (int) $p['bouts_total'] ?> combats · <?= e(hours_label((int) $p['estimated_seconds'])) ?><?= $p['spread_pct'] > 0 ? ' · écart ' . number_format($p['spread_pct'], 1, ',', '') . ' %' : '' ?></span><a class="small" href="<?= e('?' . http_build_query(['vue' => 'feuilles', 'q' => (string) $p['number']])) ?>">Feuille</a></footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
<p class="small muted">Glissez une poule vers un autre tapis, un athlète vers une autre poule, ou un athlète sur un autre athlète pour les échanger. Sur tablette, utilisez les menus « Déplacer » sous chaque poule et le formulaire en bas de page.</p>
<div class="pool-board" data-pool-board data-api="<?= e($api) ?>">
  <?php foreach (array_merge($tatamis, [['id' => 0, 'number' => null]]) as $t): $list = $byTatami[$t['id']] ?? []; if ($t['id'] === 0 && !$list) continue; ?>
    <section class="mat-col" data-tatami="<?= (int) $t['id'] ?>">
      <header><strong><?= $t['number'] ? 'Tapis ' . (int) $t['number'] : 'Non affectées' ?></strong><span><?= count($list) ?> poules · <?= array_sum(array_column($list, 'bouts_total')) ?> combats · <?= e(hours_label(array_sum(array_column($list, 'estimated_seconds')))) ?></span><?php if ($t['id'] && $list): ?><a class="mat-pdf" href="<?= e(url('/admin/competitions/' . $cid . '/pdf/feuilles', ['tatami_id' => $t['id']])) ?>" target="_blank" rel="noopener" title="Feuilles de poule du tapis <?= (int) $t['number'] ?>"><?= icon('download') ?>PDF</a><?php endif; ?></header>
      <div class="drop">
        <?php foreach ($list as $i => $p): $locked = (int) $p['is_locked'] === 1; $started = $p['bouts_started'] > 0;
          $clubs = array_count_values(array_filter(array_column($p['athletes'], 'club_id'))); ?>
          <article class="pool<?= $locked ? ' locked' : '' ?><?= $started ? ' started' : '' ?>" data-pool="<?= (int) $p['id'] ?>"<?= !$started ? ' draggable="true"' : '' ?>>
            <header><strong>Poule <?= (int) $p['number'] ?><?= $p['weight_label'] ? ' · ' . e($p['weight_label']) . ' kg' : '' ?></strong>
              <?php if ($locked): ?><span class="badge badge-yellow" title="Verrouillée">🔒</span><?php endif; ?>
              <span class="badge"><?= (int) $p['size'] ?></span></header>
            <ol>
              <?php foreach ($p['athletes'] as $a): ?>
                <li class="athlete<?= ($a['club_id'] && ($clubs[$a['club_id']] ?? 0) > 1) ? ' dup' : '' ?>" data-reg="<?= (int) $a['registration_id'] ?>"<?= !$started && !$locked ? ' draggable="true"' : '' ?>>
                  <?= e($a['last_name'] . ' ' . $a['first_name']) ?> <?= $a['reference_weight_kg'] !== null ? '<span class="small">' . e(kg($a['reference_weight_kg'])) . '</span>' : '' ?><br><span class="club"><?php $logo = $a['club_logo']; $name = $a['club_name'] ?? '?'; $size = 16; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?> <?= e($a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
            <footer>
              <span>N° <?= (int) $p['number'] ?> · <?= (int) $p['bouts_total'] ?> comb. · <?= e(hours_label((int) $p['estimated_seconds'])) ?><?= $p['spread_pct'] > 0 ? ' · écart ' . number_format($p['spread_pct'], 1, ',', '') . ' %' : '' ?><?= $started ? ' · ' . (int) $p['bouts_done'] . ' validés' : '' ?></span>
              <span style="flex:1"></span>
              <?php if (!$started): ?>
                <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="<?= $locked ? 'unlock' : 'lock' ?>"><input type="hidden" name="pool_id" value="<?= (int) $p['id'] ?>"><button class="icon-btn" type="submit" title="<?= $locked ? 'Déverrouiller' : 'Verrouiller' ?>"><?= $locked ? 'Déverrouiller' : 'Verrouiller' ?></button></form>
              <?php endif; ?>
              <details><summary class="icon-btn">Déplacer</summary>
                <form method="post" action="<?= e($u) ?>" style="padding:.4rem 0;display:grid;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="op" value="move_pool"><input type="hidden" name="pool_id" value="<?= (int) $p['id'] ?>">
                  <select name="tatami_id" aria-label="Tapis" style="min-height:34px"><?php foreach ($tatamis as $tt): ?><option value="<?= (int) $tt['id'] ?>"<?= selected($p['tatami_id'], $tt['id']) ?>>Tapis <?= (int) $tt['number'] ?></option><?php endforeach; ?></select>
                  <input name="position" type="number" min="1" placeholder="Position (facultatif)" aria-label="Position" style="min-height:34px">
                  <button class="btn btn-ghost btn-sm" type="submit">Déplacer la poule</button></form>
                <?php if (!$started && !$locked): ?>
                  <?= $post('new_pool', 'Créer une poule vide du même groupe', 'btn-ghost', ['pool_id' => $p['id']]) ?>
                  <?= $post('regroup', 'Recalculer ce groupe', 'btn-ghost', ['group_key' => $p['group_key']], 'Recalculer toutes les poules non commencées de ce groupe ?') ?>
                  <?php if (!$p['athletes']): ?><?= $post('delete_pool', 'Supprimer la poule vide', 'btn-danger', ['pool_id' => $p['id']]) ?><?php endif; ?>
                <?php endif; ?>
              </details>
            </footer>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?php endif; ?>
<div class="grid grid-2" style="margin-top:1.4rem">
  <section class="card">
    <h2 style="font-size:1.1rem">Déplacer ou échanger (sans glisser-déposer)</h2>
    <?php $opts = ''; foreach ($allPools as $p) { foreach ($p['athletes'] as $a) { $opts .= '<option value="' . (int) $a['registration_id'] . '">' . e($a['last_name'] . ' ' . $a['first_name'] . ' — ' . $p['label']) . '</option>'; } } ?>
    <form method="post" action="<?= e($u) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="op" value="move_athlete">
      <div class="field"><label>Athlète</label><select name="registration_id"><?= $opts ?></select></div>
      <div class="field"><label>Vers la poule</label><select name="pool_id"><?php foreach ($allPools as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['label']) ?></option><?php endforeach; ?></select></div>
      <label class="check"><input type="checkbox" name="allow_exception" value="1"> Exception autorisée (catégorie différente — jamais filles/garçons)</label>
      <button class="btn btn-ghost" type="submit">Déplacer l'athlète</button></form>
    <hr style="border:0;border-top:1px solid var(--line);margin:1.2rem 0">
    <form method="post" action="<?= e($u) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="op" value="swap">
      <div class="field"><label>Athlète A</label><select name="a"><?= $opts ?></select></div>
      <div class="field"><label>Athlète B</label><select name="b"><?= $opts ?></select></div>
      <label class="check"><input type="checkbox" name="allow_exception" value="1"> Exception autorisée</label>
      <button class="btn btn-ghost" type="submit">Échanger</button></form>
  </section>
  <section class="card">
    <h2 style="font-size:1.1rem"><?= count($unplaced) ?> athlète(s) présent(s) non placé(s)</h2>
    <?php if (!$unplaced): ?><p class="muted">Tous les athlètes présents sont placés.</p><?php else: ?>
      <p class="small muted">Arrivés après la génération, retirés d'une poule ou laissés hors poule. Aucun résultat existant n'est écrasé : ajoutez-les à une poule non commencée ou recalculez leur groupe.</p>
      <ul style="list-style:none;padding:0;margin:0"><?php foreach ($unplaced as $up): ?>
        <li style="padding:.6rem 0;border-bottom:1px solid var(--line)"><strong><?= e($up['last_name'] . ' ' . $up['first_name']) ?></strong> <span class="small muted"><?= e($up['group_label']) ?><?= $up['ref_weight'] ? ' · ' . e(kg($up['ref_weight'])) : '' ?></span>
          <div class="btn-group" style="margin-top:.35rem">
            <?php foreach ($up['suggestions'] as $sg): ?><?= $post('move_athlete', 'Ajouter à ' . e($sg['pool']['label']) . ' (' . $sg['size'] . ($sg['full'] ? ', complète' : '') . ')', $sg['full'] ? 'btn-ghost' : 'btn-soft', ['registration_id' => $up['id'], 'pool_id' => $sg['pool']['id']]) ?><?php endforeach; ?>
            <?= $post('regroup', 'Recalculer le groupe', 'btn-ghost', ['group_key' => $up['group_key']], 'Recalculer les poules non commencées de ce groupe en intégrant cet athlète ?') ?>
          </div></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
