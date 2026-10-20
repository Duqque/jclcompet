<?php
$v = fn (string $k, $d = '') => old($k, $c[$k] ?? $d);
$dt = fn (?string $s) => $s ? str_replace(' ', 'T', substr($s, 0, 16)) : '';
$sizes = explode(',', (string) old('pool_sizes_str', $c['pool_sizes'] ?? '3,4,5'));
$archived = $c && $c['status'] === 'archived';
?>
<?php if ($c): $heading = 'Informations'; include dirname(__DIR__) . '/_head.php'; else: ?><div class="page-head"><div><p class="eyebrow">Compétitions</p><h1>Nouvelle compétition</h1></div></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url($c ? '/admin/competitions/' . $c['id'] . '/modifier' : '/admin/competitions')) ?>">
  <?= csrf_field() ?>
  <fieldset><legend>Présentation</legend>
    <div class="form-grid">
      <div class="field span-all"><label class="required" for="name">Nom</label><input id="name" name="name" required maxlength="190" value="<?= e($v('name')) ?>"><?= field_error('name') ?></div>
      <div class="field"><label for="edition_label">Édition</label><input id="edition_label" name="edition_label" maxlength="80" placeholder="Édition 2027" value="<?= e($v('edition_label')) ?>"></div>
      <div class="field"><label for="tagline">Accroche</label><input id="tagline" name="tagline" maxlength="255" value="<?= e($v('tagline')) ?>"></div>
      <div class="field span-all"><label for="description">Description</label><textarea id="description" name="description" rows="4"><?= e($v('description')) ?></textarea><span class="hint">Mise en forme : **gras**, *italique*, listes avec « - », liens [texte](https://…).</span></div>
    </div>
  </fieldset>
  <fieldset><legend>Date et lieu</legend>
    <div class="form-grid">
      <div class="field"><label class="required" for="event_date">Date</label><input id="event_date" name="event_date" type="date" required value="<?= e($v('event_date')) ?>"><?= field_error('event_date') ?></div>
      <div class="field"><label class="required" for="start_time">Heure de début (accueil)</label><input id="start_time" name="start_time" type="time" required value="<?= e(substr((string) $v('start_time', '09:00'), 0, 5)) ?>"></div>
      <div class="field"><label for="venue">Salle</label><input id="venue" name="venue" maxlength="190" value="<?= e($v('venue')) ?>"></div>
      <div class="field"><label class="required" for="address">Adresse</label><input id="address" name="address" required maxlength="255" value="<?= e($v('address')) ?>"></div>
      <div class="field"><label class="required" for="city">Ville</label><input id="city" name="city" required maxlength="120" value="<?= e($v('city')) ?>"></div>
    </div>
  </fieldset>
  <fieldset><legend>Inscriptions</legend>
    <div class="form-grid">
      <div class="field"><label for="registration_opens_at">Ouverture</label><input id="registration_opens_at" name="registration_opens_at" type="datetime-local" value="<?= e($dt($v('registration_opens_at'))) ?>"></div>
      <div class="field"><label for="registration_closes_at">Clôture</label><input id="registration_closes_at" name="registration_closes_at" type="datetime-local" value="<?= e($dt($v('registration_closes_at'))) ?>"><?= field_error('registration_closes_at') ?></div>
      <div class="field"><label for="age_reference_date">Date de référence des âges</label><input id="age_reference_date" name="age_reference_date" type="date" value="<?= e($v('age_reference_date')) ?>"><span class="hint">Année utilisée pour calculer les années de naissance des catégories (ex. 31/12 de la saison).</span></div>
      <div class="field"><label for="max_athletes">Nombre maximal d'athlètes</label><input id="max_athletes" name="max_athletes" type="number" min="1" value="<?= e($v('max_athletes')) ?>"></div>
      <div class="field"><label for="expected_participants">Participants attendus (affichage)</label><input id="expected_participants" name="expected_participants" type="number" min="0" value="<?= e($v('expected_participants')) ?>"></div>
    </div>
  </fieldset>
  <fieldset><legend>Organisation sportive</legend>
    <div class="form-grid">
      <div class="field"><label class="required" for="mat_count">Nombre de tapis</label><input id="mat_count" name="mat_count" type="number" min="1" max="20" required value="<?= e($v('mat_count', 4)) ?>"><span class="hint">Les tapis et leurs pages TV sont créés automatiquement.</span></div>
      <div class="field"><span class="label">Tailles de poules autorisées</span>
        <div class="btn-group"><?php foreach ([3, 4, 5] as $n): ?><label class="check"><input type="checkbox" name="pool_sizes[]" value="<?= $n ?>"<?= checked(in_array((string) $n, $sizes, true)) ?>> <?= $n ?> combattants</label><?php endforeach; ?></div></div>
      <?php if (!$c): ?>
        <div class="field span-all"><span class="label">Catégories d'âge à créer (modèles à confirmer)</span>
          <div class="btn-group"><?php foreach ($templates as $t): ?><label class="check"><input type="checkbox" name="age_templates[]" value="<?= e($t['code']) ?>"<?= checked(in_array($t['code'], ['U11', 'U13', 'U15'], true)) ?>> <?= e($t['name']) ?> (<?= e($t['code']) ?>)</label><?php endforeach; ?></div>
          <label class="check" style="margin-top:.4rem"><input type="checkbox" name="with_weights" value="1" checked> Créer des catégories de poids d'exemple</label>
          <span class="hint">Tout reste modifiable dans « Catégories ». Les années de naissance sont calculées à partir de la date de référence.</span></div>
      <?php endif; ?>
    </div>
  </fieldset>
  <fieldset><legend>Textes du programme</legend>
    <div class="form-grid">
      <div class="field span-all"><label for="practical_info">Informations pratiques</label><textarea id="practical_info" name="practical_info" rows="4"><?= e($v('practical_info')) ?></textarea></div>
      <div class="field span-all"><label for="coach_info">Informations destinées aux entraîneurs</label><textarea id="coach_info" name="coach_info" rows="4"><?= e($v('coach_info')) ?></textarea></div>
      <div class="field span-all"><label for="schedule_text">Horaires (texte libre, si vous n'utilisez pas la liste d'horaires)</label><textarea id="schedule_text" name="schedule_text" rows="3"><?= e($v('schedule_text')) ?></textarea></div>
    </div>
  </fieldset>
  <fieldset><legend>Documents et visuel</legend>
    <div class="form-grid">
      <div class="field"><label for="regulation">Règlement / programme (PDF)</label><input id="regulation" name="regulation" type="file" accept="application/pdf"><?php if (!empty($c['regulation_document_id'])): ?><a class="small" href="<?= e(url('/media/' . $c['regulation_document_id'])) ?>" target="_blank">Document actuel</a><?php endif; ?></div>
      <div class="field"><label for="cover">Visuel (affiche, image)</label><input id="cover" name="cover" type="file" accept="image/png,image/jpeg,image/webp"><?php if (!empty($c['cover_document_id'])): ?><img src="<?= e(url('/media/' . $c['cover_document_id'])) ?>" alt="Visuel actuel" style="max-width:160px;margin-top:.4rem"><label class="check"><input type="checkbox" name="remove_cover" value="1"> Retirer le visuel</label><?php endif; ?></div>
      <div class="field"><label class="check"><input type="checkbox" name="is_featured" value="1"<?= checked($v('is_featured')) ?>> Mettre en avant sur l'accueil</label></div>
    </div>
  </fieldset>
  <div class="btn-group"><button class="btn btn-red btn-lg" type="submit"<?= $archived ? ' disabled' : '' ?>><?= $c ? 'Enregistrer' : 'Créer la compétition' ?></button><a class="btn btn-ghost btn-lg" href="<?= e(url($c ? '/admin/competitions/' . $c['id'] : '/admin/competitions')) ?>">Annuler</a></div>
</form>
