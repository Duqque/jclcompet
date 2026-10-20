<?php $heading = 'Paramètres sportifs'; include __DIR__ . '/_head.php'; $m = $s['score_model']; $vp = $s['pool_ranking']['value_points']; $crit = \App\Controllers\Admin\SettingsController::MEDAL_CRITERIA; ?>
<div class="alert alert-info small">Les valeurs proposées par défaut sont des <strong>exemples à confirmer</strong> selon le règlement retenu ; aucune n'est une règle officielle.</div>
<form method="post" action="<?= e(url('/admin/competitions/' . $c['id'] . '/parametres')) ?>">
  <?= csrf_field() ?>
  <fieldset><legend>Composition des poules</legend><div class="form-grid">
    <div class="field"><label for="pool_grouping">Regroupement</label><select id="pool_grouping" name="pool_grouping">
      <option value="weight_category"<?= selected($s['pool_grouping'], 'weight_category') ?>>Par catégorie de poids (âge × sexe × poids)</option>
      <option value="morphological"<?= selected($s['pool_grouping'], 'morphological') ?>>Morphologique (âge × sexe, tri par poids)</option></select></div>
    <div class="field"><label for="weight_input">Saisie du poids à l'inscription</label><select id="weight_input" name="weight_input">
      <option value="category"<?= selected($s['weight_input'], 'category') ?>>Le club choisit la catégorie de poids (pas de pesée)</option>
      <option value="weight"<?= selected($s['weight_input'], 'weight') ?>>Le club indique le poids (catégorie calculée)</option></select></div>
    <div class="field"><label for="preferred_pool_size">Taille de poule préférée</label><select id="preferred_pool_size" name="preferred_pool_size"><?php foreach ([3, 4, 5] as $n): ?><option value="<?= $n ?>"<?= selected($s['preferred_pool_size'], $n) ?>><?= $n ?> combattants</option><?php endforeach; ?></select><span class="hint">Tailles autorisées : <?= e($c['pool_sizes']) ?> (dans « Informations »).</span></div>
    <div class="field"><label for="max_weight_spread_pct">Alerte écart de poids (%)</label><input id="max_weight_spread_pct" name="max_weight_spread_pct" type="number" min="0" max="100" value="<?= (int) $s['max_weight_spread_pct'] ?>"></div>
    <label class="check"><input type="checkbox" name="use_measured_weight" value="1"<?= checked($s['use_measured_weight']) ?>> Utiliser le poids mesuré à la pesée lorsqu'il existe</label>
    <label class="check"><input type="checkbox" name="avoid_same_club" value="1"<?= checked($s['avoid_same_club']) ?>> Séparer les combattants d'un même club quand c'est possible</label>
    <label class="check"><input type="checkbox" name="interleave_pools" value="1"<?= checked($s['interleave_pools']) ?>> Enchaîner les poules d'un tapis (récupération) :</label>
    <div class="form-grid" style="margin:.4rem 0 .6rem">
      <div class="field"><label for="interleave_block">Combats d'une poule avant de passer à la suivante</label><input id="interleave_block" name="interleave_block" type="number" min="1" max="10" value="<?= (int) ($s['interleave_block'] ?? 2) ?>"></div>
      <div class="field"><label for="interleave_scope">Poules enchaînées</label><select id="interleave_scope" name="interleave_scope"><option value="age"<?= selected($s['interleave_scope'] ?? 'age', 'age') ?>>Celles d'une même catégorie d'âge (même tranche horaire)</option><option value="tatami"<?= selected($s['interleave_scope'] ?? 'age', 'tatami') ?>>Toutes les poules du tapis</option></select></div>
    </div>
    <label class="check"><input type="checkbox" name="pools_published" value="1"<?= checked($s['pools_published']) ?>> Publier les poules aux clubs (espace club)</label>
  </div></fieldset>

  <fieldset><legend>Modèle de score</legend><div class="form-grid">
    <label class="check"><input type="checkbox" name="use_yuko" value="1"<?= checked($m['use_yuko']) ?>> Utiliser le yuko</label>
    <div class="field"><label for="wazaari_for_ippon">Waza-ari pour la victoire</label><input id="wazaari_for_ippon" name="wazaari_for_ippon" type="number" min="1" max="5" value="<?= (int) $m['wazaari_for_ippon'] ?>"></div>
    <div class="field"><label for="shido_for_hansoku">Shido entraînant hansoku-make</label><input id="shido_for_hansoku" name="shido_for_hansoku" type="number" min="1" max="5" value="<?= (int) $m['shido_for_hansoku'] ?>"></div>
    <div class="field"><label for="osae_yuko">Osaekomi : yuko après (s)</label><input id="osae_yuko" name="osae_yuko" type="number" min="1" max="30" value="<?= (int) $m['osaekomi']['yuko'] ?>"></div>
    <div class="field"><label for="osae_wazaari">Osaekomi : waza-ari après (s)</label><input id="osae_wazaari" name="osae_wazaari" type="number" min="1" max="30" value="<?= (int) $m['osaekomi']['wazaari'] ?>"></div>
    <div class="field"><label for="osae_ippon">Osaekomi : ippon après (s)</label><input id="osae_ippon" name="osae_ippon" type="number" min="1" max="40" value="<?= (int) $m['osaekomi']['ippon'] ?>"></div>
    <label class="check"><input type="checkbox" name="golden_score_first_score_wins" value="1"<?= checked($m['golden_score_first_score_wins']) ?>> Golden score : le premier avantage donne la victoire</label>
    <label class="check"><input type="checkbox" name="shido_decides" value="1"<?= checked($m['shido_decides']) ?>> À égalité d'avantages, les pénalités départagent</label>
    <div class="field"><label for="fighter_color">Couleur du premier appelé à l'écran</label><select id="fighter_color" name="fighter_color"><option value="red"<?= selected($s['fighter_color'], 'red') ?>>Rouge / blanc</option><option value="blue"<?= selected($s['fighter_color'], 'blue') ?>>Bleu / blanc</option></select></div>
  </div></fieldset>

  <fieldset><legend>Classement des poules</legend>
    <p class="small muted">Ordre : nombre de victoires, puis points de valeur des victoires, puis confrontation directe. Égalité persistante : départage par l'administrateur ou places partagées.</p>
    <div class="form-grid">
      <?php foreach ($vp as $method => $pts): ?><div class="field"><label for="vp_<?= e($method) ?>">Victoire par <?= e(\App\Services\BoutService::METHODS[$method] ?? $method) ?></label><input id="vp_<?= e($method) ?>" name="vp_<?= e($method) ?>" type="number" min="0" max="100" value="<?= (int) $pts ?>"></div><?php endforeach; ?>
      <div class="field"><label for="tie_handling">Égalité non départagée</label><select id="tie_handling" name="tie_handling"><option value="manual"<?= selected($s['tie_handling'], 'manual') ?>>Départage par l'administrateur (barrage ou décision)</option><option value="shared"<?= selected($s['tie_handling'], 'shared') ?>>Places partagées (ex æquo)</option></select></div>
      <div class="field"><label for="forfeit_policy">Athlète forfait sur tous ses combats</label><select id="forfeit_policy" name="forfeit_policy"><option value="no_points"<?= selected($s['forfeit_policy'], 'no_points') ?>>Ni points ni médaille</option><option value="ranked"<?= selected($s['forfeit_policy'], 'ranked') ?>>Classé normalement</option></select></div>
    </div>
  </fieldset>

  <fieldset><legend>Classement des clubs par médailles</legend>
    <p class="small muted">Ordre des critères (du plus important au moins important).</p>
    <div class="form-grid">
      <?php for ($i = 0; $i < 6; $i++): $cur = $s['medal_order'][$i] ?? ''; ?>
        <div class="field"><label for="mo<?= $i ?>">Critère <?= $i + 1 ?></label><select id="mo<?= $i ?>" name="medal_order[]"><option value="">—</option><?php foreach ($crit as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($cur, $k) ?>><?= e($label) ?> (décroissant)</option><?php endforeach; ?></select></div>
      <?php endfor; ?>
    </div>
  </fieldset>

  <fieldset><legend>Planning et affichages</legend><div class="form-grid">
    <div class="field"><label for="bout_gap_seconds">Battement entre deux combats (s)</label><input id="bout_gap_seconds" name="bout_gap_seconds" type="number" min="0" max="900" value="<?= (int) $s['bout_gap_seconds'] ?>"><span class="hint">Utilisé pour estimer la durée de chaque tapis.</span></div>
    <div class="field"><label for="tv_refresh_ms">Rafraîchissement des écrans TV (ms)</label><input id="tv_refresh_ms" name="tv_refresh_ms" type="number" min="500" max="10000" step="100" value="<?= (int) $s['tv_refresh_ms'] ?>"></div>
    <div class="field"><label for="tv_result_seconds">Affichage de « Victoire » sur la TV (s)</label><input id="tv_result_seconds" name="tv_result_seconds" type="number" min="3" max="300" value="<?= (int) $s['tv_result_seconds'] ?>"><span class="hint">Ensuite, l'écran présente le combat suivant.</span></div>
    <div class="field"><label for="public_refresh_seconds">Rafraîchissement du classement public (s)</label><input id="public_refresh_seconds" name="public_refresh_seconds" type="number" min="30" max="900" value="<?= (int) $s['public_refresh_seconds'] ?>"></div>
    <label class="check"><input type="checkbox" name="public_live" value="1"<?= checked($s['public_live']) ?>> Direct public des tapis</label>
    <label class="check"><input type="checkbox" name="public_podium_photos" value="1"<?= checked($s['public_podium_photos'] ?? false) ?>> Photos des judokas sur l'annonce des podiums (écran de classement public — avec l'accord des familles)</label>
    <label class="check"><input type="checkbox" name="public_mat_results" value="1"<?= checked($s['public_mat_results']) ?>> Résultats publics par tapis</label>
  </div></fieldset>

  <fieldset><legend>Inscriptions et buvette</legend><div class="form-grid">
    <label class="check"><input type="checkbox" name="late_registration_allowed" value="1"<?= checked($s['late_registration_allowed']) ?>> Inscriptions tardives et sur place autorisées</label>
    <label class="check"><input type="checkbox" name="late_registration_requires_validation" value="1"<?= checked($s['late_registration_requires_validation']) ?>> Les inscriptions tardives nécessitent une validation de l'administrateur</label>
    <label class="check"><input type="checkbox" name="require_license" value="1"<?= checked($s['require_license']) ?>> Numéro de licence obligatoire</label>
    <label class="check"><input type="checkbox" name="card_payment_accepted" value="1"<?= checked($s['card_payment_accepted']) ?>> « CB autorisée » à la buvette</label>
  </div></fieldset>
  <button class="btn btn-red btn-lg" type="submit"<?= $c['status'] === 'archived' ? ' disabled' : '' ?>>Enregistrer les paramètres</button>
</form>
