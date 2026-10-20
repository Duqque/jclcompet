<?php /** @var ?array $rule @var array $ages @var string $u */ $r = $rule ?? []; $d = (int) ($r['duration_seconds'] ?? 120); ?>
<form method="post" action="<?= e($u) ?>" style="text-align:left;margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="op" value="save"><?php if ($rule): ?><input type="hidden" name="rule_id" value="<?= (int) $r['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <div class="field"><label>Catégorie d'âge</label><select name="age_category_id"><option value="">Règle par défaut</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($r['age_category_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Sexe</label><select name="sex"><option value="">Tous</option><option value="M"<?= selected($r['sex'] ?? '', 'M') ?>>Masculin</option><option value="F"<?= selected($r['sex'] ?? '', 'F') ?>>Féminin</option></select></div>
    <div class="field"><label>Phase</label><select name="phase"><option value="all"<?= selected($r['phase'] ?? 'all', 'all') ?>>Toutes</option><option value="pool"<?= selected($r['phase'] ?? '', 'pool') ?>>Poules</option><option value="final"<?= selected($r['phase'] ?? '', 'final') ?>>Barrage / classement</option></select></div>
    <div class="field"><label>Durée — minutes</label><input name="minutes" type="number" min="0" max="20" value="<?= intdiv($d, 60) ?>" required></div>
    <div class="field"><label>secondes</label><input name="seconds" type="number" min="0" max="59" value="<?= $d % 60 ?>" required></div>
    <div class="field"><label>Golden score</label><select name="golden_score_mode"><?php foreach (['unlimited' => 'Illimité', 'limited' => 'Limité', 'none' => 'Aucun (décision)'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($r['golden_score_mode'] ?? 'unlimited', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Durée du golden score limité (s)</label><input name="golden_score_seconds" type="number" min="0" max="600" value="<?= e($r['golden_score_seconds'] ?? '') ?>"></div>
    <div class="field"><label>Récupération (s)</label><input name="recovery_seconds" type="number" min="0" max="3600" value="<?= (int) ($r['recovery_seconds'] ?? 0) ?>"></div>
    <div class="field"><label>Libellé</label><input name="label" maxlength="120" value="<?= e($r['label'] ?? '') ?>"></div>
    <div class="field"><label>Prolongation / note</label><input name="overtime_rule" maxlength="255" value="<?= e($r['overtime_rule'] ?? '') ?>"></div>
    <div class="field"><label>Priorité</label><input name="priority" type="number" value="<?= (int) ($r['priority'] ?? 0) ?>"></div>
    <div class="field"><label>Valable du</label><input name="valid_from" type="date" value="<?= e($r['valid_from'] ?? '') ?>"></div>
    <div class="field"><label>au</label><input name="valid_until" type="date" value="<?= e($r['valid_until'] ?? '') ?>"></div>
    <label class="check"><input type="checkbox" name="is_example" value="1"<?= checked($r['is_example'] ?? 0) ?>> Valeur d'exemple à confirmer</label>
  </div>
  <button class="btn btn-red btn-sm" type="submit" style="margin-top:.6rem">Enregistrer la règle</button>
</form>
