<?php
$heading = 'Configurations de combat';
$sub = 'Temps, golden score, immobilisations, barème et règles particulières par catégorie';
include __DIR__ . '/_head.php';
$u = url('/admin/competitions/' . $c['id'] . '/durees');
$gs = ['none' => 'Pas de golden score', 'limited' => 'Golden score limité', 'unlimited' => 'Golden score illimité'];
$th = (array) ($model['osaekomi'] ?? ['yuko' => 5, 'wazaari' => 10, 'ippon' => 20]);
$val = fn (array $rl, string $k, $default) => $rl[$k] !== null && $rl[$k] !== '' ? $rl[$k] : $default;
?>
<?php foreach ($issues as $i): ?><div class="alert alert-<?= $i['level'] === 'error' ? 'error' : 'warning' ?> small"><?= e($i['message']) ?></div><?php endforeach; ?>
<p class="muted small" style="margin-top:0">Chaque combat garde la configuration de sa catégorie au moment de sa création : la modifier ne change jamais un combat commencé. La configuration la plus précise s'applique (catégorie, sexe, phase), puis la priorité.</p>

<div class="cfg-grid">
  <?php foreach ($rules as $rl):
    $y = (int) $val($rl, 'osaekomi_yuko_s', $th['yuko'] ?? 5); $w = (int) $val($rl, 'osaekomi_wazaari_s', $th['wazaari'] ?? 10); $ip = (int) $val($rl, 'osaekomi_ippon_s', $th['ippon'] ?? 20);
    $useYuko = (bool) $val($rl, 'use_yuko', $model['use_yuko'] ?? true);
    $custom = array_filter(array_map(fn ($k) => $rl[$k] !== null, \App\Services\DurationRuleService::OVERRIDES)); ?>
    <article class="cfg-card<?= $rl['is_example'] ? ' example' : '' ?>">
      <header>
        <div><p class="eyebrow"><?= e($rl['age_name'] ?? 'Toutes catégories') ?> · <?= e($rl['sex'] ? ($rl['sex'] === 'F' ? 'Filles' : 'Garçons') : 'Filles et garçons') ?><?= $rl['phase'] !== 'all' ? ' · ' . e(['pool' => 'Poules', 'final' => 'Barrage'][$rl['phase']]) : '' ?></p>
          <h3><?= e($rl['label'] ?: 'Configuration') ?></h3></div>
        <?php if ($rl['is_example']): ?><span class="badge badge-orange">exemple à confirmer</span><?php elseif ($custom): ?><span class="badge badge-ink">barème spécifique</span><?php endif; ?>
      </header>
      <div class="tiles cfg-tiles">
        <div class="tile"><span class="label">Durée</span><strong><?= e(duration_label((int) $rl['duration_seconds'])) ?></strong></div>
        <div class="tile"><span class="label">Golden score</span><strong><?= e($rl['golden_score_mode'] === 'limited' ? 'Limité · ' . duration_label((int) $rl['golden_score_seconds']) : ($rl['golden_score_mode'] === 'none' ? 'Non' : 'Illimité')) ?></strong></div>
        <div class="tile"><span class="label">Récupération</span><strong><?= (int) $rl['recovery_seconds'] ?> s</strong></div>
      </div>
      <div class="osae-scale" aria-label="Immobilisation : <?= $useYuko ? "yuko $y s, " : '' ?>waza-ari <?= $w ?> s, ippon <?= $ip ?> s">
        <div class="bar"><?php if ($useYuko): ?><span class="mark y" style="left:<?= round($y / $ip * 100, 1) ?>%"></span><?php endif; ?><span class="mark w" style="left:<?= round($w / $ip * 100, 1) ?>%"></span><span class="mark i" style="left:100%"></span></div>
        <div class="legend"><?php if ($useYuko): ?><span>Yuko <strong><?= $y ?> s</strong></span><?php endif; ?><span>Waza-ari <strong><?= $w ?> s</strong></span><span>Ippon <strong><?= $ip ?> s</strong></span></div>
      </div>
      <p class="cfg-line"><?= $useYuko ? 'Yuko compté' : 'Yuko non compté' ?> · <?= (int) $val($rl, 'wazaari_for_ippon', $model['wazaari_for_ippon'] ?? 2) ?> waza-ari = ippon · <?= (int) $val($rl, 'shido_for_hansoku', $model['shido_for_hansoku'] ?? 3) ?> shido = hansoku-make</p>
      <?php if ($rl['special_rules']): ?><p class="cfg-rules"><?= e($rl['special_rules']) ?></p><?php endif; ?>
      <footer>
        <details class="cfg-edit"><summary class="btn btn-ghost btn-sm">Modifier</summary><?php $rule = $rl; include __DIR__ . '/_duration_form.php'; ?></details>
        <form method="post" action="<?= e($u) ?>" data-confirm="Supprimer cette configuration ? Les combats déjà créés gardent la leur." data-danger><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="rule_id" value="<?= (int) $rl['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Supprimer</button></form>
      </footer>
    </article>
  <?php endforeach; ?>
</div>

<div class="grid grid-2" style="margin-top:1.4rem;align-items:start">
  <section class="card"><h2 style="font-size:1.15rem">Nouvelle configuration</h2><?php $rule = null; include __DIR__ . '/_duration_form.php'; ?></section>
  <section class="card"><h2 style="font-size:1.15rem">Combats déjà créés</h2>
    <p><?= (int) $scheduled ?> combat(s) à venir. Après une modification, appliquez explicitement les configurations actuelles aux combats non commencés (ceux en cours ou terminés ne changent jamais).</p>
    <form method="post" action="<?= e($u) ?>" data-confirm="Appliquer les configurations actuelles aux <?= (int) $scheduled ?> combats à venir ?"><?= csrf_field() ?><input type="hidden" name="op" value="reapply"><button class="btn" type="submit"<?= $scheduled ? '' : ' disabled' ?>>Réappliquer aux combats à venir</button></form>
  </section>
</div>
