<?php use App\Services\BoutService; $s = $state; $base = '/admin/competitions/' . $c['id']; $heading = 'Combat n° ' . $s['id']; include dirname(__DIR__) . '/_head.php'; ?>
<p><a href="<?= e(url($base . '/combats')) ?>">← Tous les combats</a></p>
<div class="grid grid-2">
  <section class="card">
    <h2 style="font-size:1.15rem"><?= e($s['category']) ?><?= $s['weight_label'] ? ' ' . e($s['weight_label']) . ' kg' : '' ?> — <?= e($s['pool_label']) ?></h2>
    <dl class="kv"><dt>Tapis</dt><dd><?= e($s['tatami_number'] ?? '—') ?> (ordre <?= (int) $s['order_on_tatami'] ?>)</dd><dt>État</dt><dd><?= e($s['status_label']) ?></dd>
      <dt>Durée</dt><dd><?= e(duration_label(intdiv($s['clock']['duration_ms'], 1000))) ?> · golden score <?= e($s['clock']['golden_score_mode']) ?></dd>
      <dt>Vainqueur</dt><dd><?= $s['winner_side'] ? e($s[$s['winner_side']]['first_name'] . ' ' . $s[$s['winner_side']]['last_name']) . ' — ' . e($s['win_method_label']) : '—' ?></dd>
      <dt>Validé</dt><dd><?= $s['validated_at'] ? e(short_datetime($s['validated_at'])) . ' par ' . e($s['validated_by']) : 'non' ?></dd></dl>
    <table class="table-compact" style="margin-top:1rem"><thead><tr><th></th><th>Ippon</th><th>Waza-ari</th><th>Yuko</th><th>Shido</th><th>Hansoku</th></tr></thead><tbody>
      <?php foreach (['a', 'b'] as $side): $f = $s[$side]; ?><tr><th scope="row" style="text-transform:none;letter-spacing:0;color:var(--ink);background:none"><?= e($f['first_name'] . ' ' . $f['last_name']) ?><br><span class="small muted"><?= e($f['club']) ?></span></th><td><?= $f['ippon'] ?></td><td><?= $f['wazaari'] ?></td><td><?= $f['yuko'] ?></td><td><?= $f['shido'] ?></td><td><?= $f['hansoku'] ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <section class="card">
    <?php if ($s['status'] === 'validated'): ?>
      <h2 style="font-size:1.15rem">Corriger le résultat</h2>
      <form method="post" action="<?= e(url($base . '/combats/' . $s['id'])) ?>" data-confirm="Enregistrer cette correction ? L'ancien résultat est conservé dans l'historique et les classements seront recalculés.">
        <?= csrf_field() ?><input type="hidden" name="op" value="correct"><input type="hidden" name="version" value="<?= (int) $s['version'] ?>"><input type="hidden" name="confirm" value="1">
        <table class="table-compact"><thead><tr><th></th><th>I</th><th>W</th><th>Y</th><th>S</th><th>H</th></tr></thead><tbody>
          <?php foreach (['a', 'b'] as $side): $f = $s[$side]; ?><tr><td class="small"><?= e($f['last_name']) ?></td><?php foreach (['ippon' => 1, 'wazaari' => 9, 'yuko' => 99, 'shido' => 9, 'hansoku' => 1] as $k => $max): ?><td><input aria-label="<?= e($k) ?>" name="scores[<?= $side ?>][<?= $k ?>]" type="number" min="0" max="<?= $max ?>" value="<?= (int) $f[$k] ?>" style="width:60px;min-height:34px"></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table>
        <div class="form-grid" style="margin-top:.8rem">
          <div class="field"><label>Vainqueur</label><select name="winner_side"><option value="a"<?= selected($s['winner_side'], 'a') ?>><?= e($s['a']['first_name'] . ' ' . $s['a']['last_name']) ?></option><option value="b"<?= selected($s['winner_side'], 'b') ?>><?= e($s['b']['first_name'] . ' ' . $s['b']['last_name']) ?></option></select></div>
          <div class="field"><label>Type de victoire</label><select name="method"><?php foreach (BoutService::METHODS as $k => $l): ?><option value="<?= $k ?>"<?= selected($s['win_method'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field span-all"><label class="required">Motif de la correction</label><textarea name="reason" required minlength="5" rows="2"></textarea></div>
        </div>
        <button class="btn btn-red" type="submit" style="margin-top:.6rem">Corriger le résultat</button>
      </form>
    <?php elseif ($s['status'] === 'scheduled'): ?>
      <h2 style="font-size:1.15rem">Combat à venir</h2>
      <form method="post" action="<?= e(url($base . '/combats/' . $s['id'])) ?>" data-confirm="Enregistrer ce forfait ? Le combat sera validé."><?= csrf_field() ?><input type="hidden" name="op" value="forfeit">
        <div class="form-grid"><div class="field"><label>Vainqueur</label><select name="winner_side"><option value="a"><?= e($s['a']['first_name'] . ' ' . $s['a']['last_name']) ?></option><option value="b"><?= e($s['b']['first_name'] . ' ' . $s['b']['last_name']) ?></option></select></div>
          <div class="field"><label>Motif</label><select name="method"><option value="fusen_gachi">Forfait (fusen-gachi)</option><option value="kiken_gachi">Abandon (kiken-gachi)</option></select></div></div>
        <button class="btn btn-ghost" type="submit" style="margin-top:.6rem">Enregistrer le forfait</button></form>
      <form method="post" action="<?= e(url($base . '/combats/' . $s['id'])) ?>" data-confirm="Annuler ce combat ? Il ne comptera plus dans la poule." data-reason data-danger style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><button class="btn btn-danger btn-sm" type="submit">Annuler le combat</button></form>
    <?php else: ?>
      <h2 style="font-size:1.15rem">Combat en cours</h2><p>La saisie se fait depuis l'interface commissaire du tapis <?= e($s['tatami_number']) ?>.</p><a class="btn btn-ghost" href="<?= e(url('/commissaire/tapis/' . $s['tatami_id'])) ?>">Ouvrir la saisie du tapis</a>
    <?php endif; ?>
  </section>
</div>
<section class="card" style="margin-top:1.2rem"><h2 style="font-size:1.1rem">Historique des résultats</h2>
  <?php if (!$results): ?><p class="muted">Aucun résultat validé.</p><?php else: ?><table class="table-compact"><thead><tr><th>Révision</th><th>Type</th><th>Vainqueur</th><th>Victoire</th><th>Score</th><th>Par</th><th>Le</th><th>Motif</th></tr></thead><tbody>
  <?php foreach ($results as $res): $sc = json_decode($res['score_json'], true); ?><tr<?= $res['is_current'] ? ' style="font-weight:600"' : '' ?>><td><?= (int) $res['revision'] ?><?= $res['is_current'] ? ' (actuel)' : '' ?></td><td><?= $res['kind'] === 'correction' ? 'Correction' : 'Validation' ?></td><td><?= e(strtoupper((string) $res['winner_side'])) ?></td><td><?= e(BoutService::METHODS[$res['win_method']] ?? $res['win_method']) ?></td>
    <td class="num"><?= $sc['a']['ippon'] . $sc['a']['wazaari'] . $sc['a']['yuko'] ?> – <?= $sc['b']['ippon'] . $sc['b']['wazaari'] . $sc['b']['yuko'] ?></td><td><?= e($res['display_name']) ?></td><td class="nowrap"><?= e(short_datetime($res['recorded_at'])) ?></td><td><?= e($res['reason']) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
<details class="card" style="margin-top:1.2rem"><summary><strong>Événements de saisie (<?= count($events) ?>)</strong></summary>
  <table class="table-compact" style="margin-top:.6rem"><tbody><?php foreach ($events as $ev): ?><tr><td class="nowrap small"><?= e(substr($ev['created_at'], 11, 12)) ?></td><td><code class="small"><?= e($ev['action']) ?></code></td><td><?= e($ev['side']) ?></td><td class="small"><?= e($ev['payload_json']) ?></td><td class="num small"><?= $ev['clock_ms'] !== null ? e(duration_label(intdiv((int) $ev['clock_ms'], 1000))) : '' ?></td><td class="small"><?= e($ev['display_name']) ?></td></tr><?php endforeach; ?></tbody></table></details>
