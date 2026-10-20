<?php use App\Services\CategoryService; $heading = 'Catégories d\'âge et de poids'; include __DIR__ . '/_head.php'; $u = url('/admin/competitions/' . $c['id'] . '/categories'); ?>
<p class="muted">La catégorie d'âge est calculée à partir de l'<strong>année de naissance</strong>. Année de référence de la compétition : <strong><?= (int) $refYear ?></strong>. Les catégories de poids ne sont jamais déduites de l'âge : elles proviennent du poids ou du choix du club.</p>

<section class="card" style="margin-bottom:1.2rem">
  <h2 style="font-size:1.15rem">Tableaux ouverts</h2>
  <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="table_save">
  <div class="table-wrap"><table>
    <thead><tr><th>Catégorie</th><th>Années</th><th>Format</th><th>Filles</th><th>Garçons</th></tr></thead>
    <tbody><?php foreach ($ages as $a): ?>
      <tr><td><strong><?= e($a['name']) ?></strong> <span class="muted small"><?= e($a['code']) ?></span></td><td class="num"><?= e(CategoryService::birthYearsLabel($a)) ?></td>
        <td><?= $a['format'] === 'animation' ? '<span class="badge badge-yellow">Animation</span>' : 'Poules' ?></td>
        <?php foreach (['F', 'M'] as $sex): $cc = $open[$a['id']][$sex] ?? null; ?>
          <td><?php if ($cc): ?>
            <label class="check"><input type="checkbox" name="cc[<?= (int) $cc['id'] ?>][is_open]" value="1"<?= checked($cc['is_open']) ?>> Ouvert (<?= (int) ($counts[$a['id']][$sex] ?? 0) ?> inscrits)</label>
            <div style="display:flex;gap:.3rem;margin-top:.3rem"><input aria-label="Quota" name="cc[<?= (int) $cc['id'] ?>][max_participants]" type="number" min="1" placeholder="Quota" value="<?= e($cc['max_participants']) ?>" style="min-height:34px;max-width:90px">
            <input aria-label="Groupe de regroupement" name="cc[<?= (int) $cc['id'] ?>][merge_group]" placeholder="Regroupement" value="<?= e($cc['merge_group']) ?>" style="min-height:34px;max-width:130px"></div>
          <?php endif; ?></td>
        <?php endforeach; ?></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="small muted">« Regroupement » : même code sur plusieurs tableaux d'un même sexe = exception explicite autorisant à les réunir dans les mêmes poules (jamais filles et garçons).</p>
  <button class="btn btn-red" type="submit">Enregistrer les tableaux</button>
  </form>
</section>

<?php foreach ($ages as $a): ?>
<section class="card" style="margin-bottom:1rem">
  <details>
    <summary style="cursor:pointer"><strong style="font-family:var(--font-display);font-size:1.1rem"><?= e($a['name']) ?></strong> — <?= e(CategoryService::birthYearsLabel($a)) ?> <?= $a['format'] === 'animation' ? '<span class="badge badge-yellow">animation</span>' : '' ?></summary>
    <form method="post" action="<?= e($u) ?>" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="op" value="age_save"><input type="hidden" name="age_id" value="<?= (int) $a['id'] ?>">
      <div class="form-grid">
        <div class="field"><label>Code</label><input name="code" value="<?= e($a['code']) ?>" maxlength="20" required></div>
        <div class="field"><label>Nom</label><input name="name" value="<?= e($a['name']) ?>" required></div>
        <div class="field"><label>Nom (garçons)</label><input name="name_m" value="<?= e($a['name_m']) ?>"></div>
        <div class="field"><label>Nom (filles)</label><input name="name_f" value="<?= e($a['name_f']) ?>"></div>
        <div class="field"><label>Née / né en (de)</label><input name="birth_year_min" type="number" value="<?= (int) $a['birth_year_min'] ?>" required></div>
        <div class="field"><label>à</label><input name="birth_year_max" type="number" value="<?= (int) $a['birth_year_max'] ?>" required></div>
        <div class="field"><label>Format</label><select name="format"><option value="pools"<?= selected($a['format'], 'pools') ?>>Combats en poules</option><option value="animation"<?= selected($a['format'], 'animation') ?>>Animation (sans combats)</option></select></div>
        <div class="field"><label>Surclassement autorisé vers</label><select name="upgrade_to_id"><option value="">Aucun</option><?php foreach ($ages as $b): if ($b['id'] === $a['id']) continue; ?><option value="<?= (int) $b['id'] ?>"<?= selected($a['upgrade_to_id'], $b['id']) ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Quota (toutes catégories de poids)</label><input name="max_participants" type="number" min="1" value="<?= e($a['max_participants']) ?>"></div>
        <div class="field"><label>Ordre</label><input name="sort_order" type="number" value="<?= (int) $a['sort_order'] ?>"></div>
      </div>
      <div class="btn-group" style="margin-top:.8rem"><button class="btn btn-red btn-sm" type="submit">Enregistrer</button></div>
    </form>
    <?php if ($a['format'] === 'pools'): ?>
    <div class="grid grid-2" style="margin-top:1rem">
      <?php foreach (['F' => 'Filles', 'M' => 'Garçons'] as $sex => $label): $ws = $weights[$a['id']][$sex] ?? []; ?>
        <div>
          <h4><?= e(CategoryService::label($a, $sex)) ?> — catégories de poids</h4>
          <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="weights_save"><input type="hidden" name="age_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="sex" value="<?= $sex ?>">
            <div class="field"><label class="sr-only" for="lim<?= $a['id'] . $sex ?>">Limites</label><input id="lim<?= $a['id'] . $sex ?>" name="limits" value="<?= e(implode(' ', array_map(fn ($w) => $w['label'], $ws))) ?>" placeholder="-22 -25 -28 +28"><span class="hint">Limites supérieures séparées par des espaces ; terminer par « +X » pour la catégorie ouverte. Vide = poules morphologiques.</span></div>
            <button class="btn btn-ghost btn-sm" type="submit" style="margin-top:.4rem">Enregistrer les poids</button>
          </form>
          <?php if ($ws): ?>
          <form method="post" action="<?= e($u) ?>" style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="op" value="weight_quota">
            <div class="btn-group"><?php foreach ($ws as $w): ?><label class="small"><?= e($w['label']) ?> <input name="wq[<?= (int) $w['id'] ?>]" type="number" min="1" placeholder="quota" value="<?= e($w['max_participants']) ?>" style="min-height:32px;width:78px"></label><?php endforeach; ?></div>
            <button class="btn btn-ghost btn-sm" type="submit" style="margin-top:.4rem">Enregistrer les quotas</button>
          </form><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e($u) ?>" style="margin-top:1rem" data-confirm="Supprimer la catégorie « <?= e($a['name']) ?> » et ses catégories de poids ?" data-danger><?= csrf_field() ?><input type="hidden" name="op" value="age_delete"><input type="hidden" name="age_id" value="<?= (int) $a['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Supprimer la catégorie</button></form>
  </details>
</section>
<?php endforeach; ?>

<section class="card">
  <h2 style="font-size:1.15rem">Ajouter une catégorie d'âge</h2>
  <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="age_save">
    <div class="form-grid">
      <div class="field"><label>Code</label><input name="code" maxlength="20" required placeholder="JU"></div>
      <div class="field"><label>Nom</label><input name="name" required placeholder="Juniors"></div>
      <div class="field"><label>Né(e)s de</label><input name="birth_year_min" type="number" required value="<?= (int) $refYear - 20 ?>"></div>
      <div class="field"><label>à</label><input name="birth_year_max" type="number" required value="<?= (int) $refYear - 18 ?>"></div>
      <div class="field"><label>Format</label><select name="format"><option value="pools">Combats en poules</option><option value="animation">Animation</option></select></div>
      <div class="field"><label>Ordre</label><input name="sort_order" type="number" value="<?= count($ages) * 10 + 10 ?>"></div>
    </div>
    <button class="btn btn-red" type="submit" style="margin-top:.8rem">Ajouter</button>
  </form>
</section>
