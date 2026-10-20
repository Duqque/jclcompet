<?php use App\Services\BoutService; ?>
<div class="page-head"><div><p class="eyebrow">Tapis <?= (int) $s['tatami_number'] ?> · <?= e($s['category']) ?> · <?= e($s['pool_label']) ?></p><h1>Corriger le combat</h1></div><a class="btn btn-ghost" href="<?= e(url('/commissaire/tapis/' . $s['tatami_id'] . '/historique')) ?>">Annuler</a></div>
<form method="post" action="<?= e(url('/commissaire/combats/' . $s['id'] . '/corriger')) ?>" class="card" data-confirm="Enregistrer la correction ? L'ancien résultat reste dans l'historique ; classements et écran TV seront mis à jour.">
  <?= csrf_field() ?><input type="hidden" name="version" value="<?= (int) $s['version'] ?>"><input type="hidden" name="confirm" value="1">
  <table class="table-compact"><thead><tr><th>Combattant</th><th>Ippon</th><th>Waza-ari</th><th>Yuko</th><th>Shido</th><th>Hansoku</th></tr></thead><tbody>
  <?php foreach (['a', 'b'] as $side): $f = $s[$side]; ?><tr><td><?= e($f['first_name'] . ' ' . $f['last_name']) ?></td><?php foreach (['ippon' => 1, 'wazaari' => 9, 'yuko' => 99, 'shido' => 9, 'hansoku' => 1] as $k => $max): ?><td><input aria-label="<?= e($k) ?>" name="scores[<?= $side ?>][<?= $k ?>]" type="number" min="0" max="<?= $max ?>" value="<?= (int) $f[$k] ?>" style="width:72px"></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table>
  <div class="form-grid" style="margin-top:1rem">
    <div class="field"><label>Vainqueur</label><select name="winner_side"><option value="a"<?= selected($s['winner_side'], 'a') ?>><?= e($s['a']['first_name'] . ' ' . $s['a']['last_name']) ?></option><option value="b"<?= selected($s['winner_side'], 'b') ?>><?= e($s['b']['first_name'] . ' ' . $s['b']['last_name']) ?></option></select></div>
    <div class="field"><label>Type de victoire</label><select name="method"><?php foreach (BoutService::METHODS as $k => $l): ?><option value="<?= $k ?>"<?= selected($s['win_method'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field span-all"><label class="required">Motif</label><textarea name="reason" required minlength="5" rows="2"></textarea></div>
  </div>
  <button class="btn btn-red btn-lg" type="submit" style="margin-top:1rem">Enregistrer la correction</button>
</form>
