<?php
/** Formulaire d'une configuration de combat. @var ?array $rule @var array $ages @var string $u @var array $model barème de la compétition */
$r = $rule ?? [];
$d = (int) ($r['duration_seconds'] ?? 120);
$th = (array) ($model['osaekomi'] ?? ['yuko' => 5, 'wazaari' => 10, 'ippon' => 20]);
$tri = fn (string $name, $value, string $yes, string $no, string $default) => '<select name="' . $name . '"><option value="">Par défaut (' . e($default) . ')</option><option value="1"' . selected((string) ($value ?? ''), '1') . '>' . e($yes) . '</option><option value="0"' . selected((string) ($value ?? ''), '0') . '>' . e($no) . '</option></select>';
?>
<form method="post" action="<?= e($u) ?>" class="cfg-form"><?= csrf_field() ?><input type="hidden" name="op" value="save"><?php if ($rule): ?><input type="hidden" name="rule_id" value="<?= (int) $r['id'] ?>"><?php endif; ?>
  <p class="cfg-step">Catégorie concernée</p>
  <div class="form-grid">
    <div class="field"><label>Catégorie d'âge</label><select name="age_category_id"><option value="">Toutes (configuration par défaut)</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($r['age_category_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Sexe</label><select name="sex"><option value="">Filles et garçons</option><option value="M"<?= selected($r['sex'] ?? '', 'M') ?>>Garçons</option><option value="F"<?= selected($r['sex'] ?? '', 'F') ?>>Filles</option></select></div>
    <div class="field"><label>Phase</label><select name="phase"><option value="all"<?= selected($r['phase'] ?? 'all', 'all') ?>>Toutes</option><option value="pool"<?= selected($r['phase'] ?? '', 'pool') ?>>Poules</option><option value="final"<?= selected($r['phase'] ?? '', 'final') ?>>Barrage / classement</option></select></div>
    <div class="field"><label>Nom de la configuration</label><input name="label" maxlength="120" value="<?= e($r['label'] ?? '') ?>" placeholder="ex. Poussins — combat éducatif"></div>
  </div>

  <p class="cfg-step">Temps de combat</p>
  <div class="form-grid">
    <div class="field"><label>Durée — minutes</label><input name="minutes" type="number" min="0" max="20" value="<?= intdiv($d, 60) ?>" required></div>
    <div class="field"><label>secondes</label><input name="seconds" type="number" min="0" max="59" value="<?= $d % 60 ?>" required></div>
    <div class="field"><label>Golden score</label><select name="golden_score_mode"><?php foreach (['unlimited' => 'Illimité', 'limited' => 'Limité', 'none' => 'Aucun (décision)'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($r['golden_score_mode'] ?? 'unlimited', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Durée du golden score limité (s)</label><input name="golden_score_seconds" type="number" min="0" max="600" value="<?= e($r['golden_score_seconds'] ?? '') ?>"></div>
    <div class="field"><label>Premier avantage au golden score</label><?= $tri('golden_first_score_wins', $r['golden_first_score_wins'] ?? null, 'Fait gagner', 'Ne suffit pas', !empty($model['golden_score_first_score_wins']) ? 'fait gagner' : 'ne suffit pas') ?></div>
    <div class="field"><label>Récupération entre deux combats (s)</label><input name="recovery_seconds" type="number" min="0" max="3600" value="<?= (int) ($r['recovery_seconds'] ?? 0) ?>"></div>
  </div>

  <p class="cfg-step">Immobilisations (osaekomi)</p>
  <div class="form-grid">
    <div class="field"><label>Yuko à</label><div class="unit"><input name="osaekomi_yuko_s" type="number" min="1" max="60" value="<?= e($r['osaekomi_yuko_s'] ?? '') ?>" placeholder="<?= (int) ($th['yuko'] ?? 5) ?>"><span>s</span></div></div>
    <div class="field"><label>Waza-ari à</label><div class="unit"><input name="osaekomi_wazaari_s" type="number" min="1" max="60" value="<?= e($r['osaekomi_wazaari_s'] ?? '') ?>" placeholder="<?= (int) ($th['wazaari'] ?? 10) ?>"><span>s</span></div></div>
    <div class="field"><label>Ippon à</label><div class="unit"><input name="osaekomi_ippon_s" type="number" min="1" max="60" value="<?= e($r['osaekomi_ippon_s'] ?? '') ?>" placeholder="<?= (int) ($th['ippon'] ?? 20) ?>"><span>s</span></div></div>
  </div>
  <p class="hint">Laissez vide pour reprendre les paramètres sportifs de la compétition (indiqués en grisé). La barre d'immobilisation de l'écran TV et du commissaire suit ces temps.</p>

  <p class="cfg-step">Barème et pénalités</p>
  <div class="form-grid">
    <div class="field"><label>Yuko</label><?= $tri('use_yuko', $r['use_yuko'] ?? null, 'Compté', 'Non compté', ($model['use_yuko'] ?? true) ? 'compté' : 'non compté') ?></div>
    <div class="field"><label>Waza-ari pour un ippon</label><input name="wazaari_for_ippon" type="number" min="1" max="5" value="<?= e($r['wazaari_for_ippon'] ?? '') ?>" placeholder="<?= (int) ($model['wazaari_for_ippon'] ?? 2) ?>"></div>
    <div class="field"><label>Shido pour hansoku-make</label><input name="shido_for_hansoku" type="number" min="1" max="6" value="<?= e($r['shido_for_hansoku'] ?? '') ?>" placeholder="<?= (int) ($model['shido_for_hansoku'] ?? 3) ?>"></div>
    <div class="field span-all"><label>Règles particulières</label><textarea name="special_rules" rows="2" maxlength="1000" placeholder="ex. Clés de bras et étranglements interdits · pas de techniques à genoux"><?= e($r['special_rules'] ?? '') ?></textarea><span class="hint">Rappel affiché sur l'écran du commissaire pendant le combat.</span></div>
  </div>

  <details class="cfg-more"><summary>Options avancées</summary>
    <div class="form-grid" style="margin-top:.8rem">
      <div class="field"><label>Note de prolongation</label><input name="overtime_rule" maxlength="255" value="<?= e($r['overtime_rule'] ?? '') ?>"></div>
      <div class="field"><label>Priorité</label><input name="priority" type="number" value="<?= (int) ($r['priority'] ?? 0) ?>"></div>
      <div class="field"><label>Valable du</label><input name="valid_from" type="date" value="<?= e($r['valid_from'] ?? '') ?>"></div>
      <div class="field"><label>au</label><input name="valid_until" type="date" value="<?= e($r['valid_until'] ?? '') ?>"></div>
    </div>
    <label class="check" style="margin-top:.6rem"><input type="checkbox" name="is_example" value="1"<?= checked($r['is_example'] ?? 0) ?>> Valeur d'exemple à confirmer</label>
  </details>
  <div class="btn-group" style="margin-top:1rem"><button class="btn" type="submit"><?= $rule ? 'Enregistrer la configuration' : 'Créer la configuration' ?></button></div>
</form>
