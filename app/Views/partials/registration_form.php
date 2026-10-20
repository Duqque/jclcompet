<?php
/** Formulaire d'inscription partagé (administration, espace club, accueil sur place). */
use App\Services\CategoryService;
$r = $reg ?? [];
$v = fn ($k, $d = '') => old($k, $r[$k] ?? $d);
$byCategory = ($weightInput ?? 'weight') === 'category';
?>
<?= json_script('reg-config', $formConfig) ?>
<div class="form-grid" data-reg-form>
  <div class="field"><label class="required" for="last_name">Nom</label><input id="last_name" name="last_name" required maxlength="80" autocomplete="off" value="<?= e($v('last_name')) ?>"><?= field_error('last_name') ?></div>
  <div class="field"><label class="required" for="first_name">Prénom</label><input id="first_name" name="first_name" required maxlength="80" autocomplete="off" value="<?= e($v('first_name')) ?>"><?= field_error('first_name') ?></div>
  <div class="field"><span class="label required">Sexe</span><div class="btn-group">
    <label class="check"><input type="radio" name="sex" value="F" required data-sex<?= checked($v('sex') === 'F') ?>> Fille</label>
    <label class="check"><input type="radio" name="sex" value="M" data-sex<?= checked($v('sex') === 'M') ?>> Garçon</label></div><?= field_error('sex') ?></div>
  <div class="field"><label class="required" for="birth_date">Date de naissance</label><input id="birth_date" name="birth_date" type="date" required value="<?= e($v('birth_date')) ?>" data-birth><?= field_error('birth_date') ?></div>
  <div class="field"><label for="age_category_id">Catégorie d'âge</label>
    <select id="age_category_id" name="age_category_id" data-age><option value="">Automatique (selon l'année de naissance)</option>
      <?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($v('age_category_id'), $a['id']) ?>><?= e($a['name']) ?> (<?= e(CategoryService::birthYearsLabel($a)) ?>)</option><?php endforeach; ?></select>
    <span class="hint" data-age-hint>Choisissez une autre catégorie uniquement en cas de surclassement autorisé.</span></div>
  <div class="field"><label<?= $byCategory ? ' class="required"' : '' ?> for="weight_category_id">Catégorie de poids</label>
    <select id="weight_category_id" name="weight_category_id" data-weight><option value=""><?= $byCategory ? 'Choisir…' : 'Automatique (selon le poids)' ?></option></select>
    <input type="hidden" data-weight-current value="<?= e($v('weight_category_id')) ?>">
    <?= field_error('weight_category_id') ?></div>
  <div class="field"><label<?= $byCategory ? '' : ' class="required"' ?> for="declared_weight_kg">Poids déclaré (kg)</label><input id="declared_weight_kg" name="declared_weight_kg" type="number" step="0.1" min="10" max="250" inputmode="decimal" value="<?= e($v('declared_weight_kg')) ?>" data-kg><span class="hint"><?= $byCategory ? 'Facultatif : la catégorie de poids suffit (pas de pesée).' : 'Sert à déterminer la catégorie de poids et à composer les poules.' ?></span></div>
  <div class="field"><label for="grade">Grade</label><input id="grade" name="grade" maxlength="40" list="grades" value="<?= e($v('grade')) ?>"><datalist id="grades"><?php foreach (['Blanche', 'Blanche-jaune', 'Jaune', 'Jaune-orange', 'Orange', 'Orange-verte', 'Verte', 'Bleue', 'Marron', 'Noire'] as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist></div>
  <div class="field"><label for="license_number">N° de licence</label><input id="license_number" name="license_number" maxlength="40" value="<?= e($v('license_number')) ?>"></div>
  <div class="field"><label for="photo">Photo (facultative)</label>
    <div style="display:flex;gap:.7rem;align-items:center"><?= avatar(!empty($r['photo_document_id']) ? (int) $r['photo_document_id'] : null, trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '?') ?>
      <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="user"></div>
    <?php if (!empty($r['photo_document_id'])): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> Retirer la photo</label><?php endif; ?>
    <span class="hint">Visible uniquement dans les espaces privés (jamais sur le site public).</span></div>
  <p class="span-all small muted" data-reg-summary aria-live="polite"></p>
</div>
