<?php use App\Services\CategoryService; use App\Services\RegistrationService; $b = '/espace-club/competitions/' . $c['id']; ?>
<div class="page-head"><div><p class="eyebrow"><?= e(fr_date($c['event_date'], true)) ?></p><h1>Mes inscriptions</h1></div>
  <div class="btn-group"><a class="btn btn-ghost" href="<?= e(url($b . '/export.csv')) ?>">Télécharger la liste (CSV)</a><a class="btn btn-ghost" href="<?= e(url('/competitions/' . $c['slug'])) ?>" target="_blank">Programme ↗</a></div></div>
<?php if (!$canEdit): ?><div class="alert alert-warning">Les inscriptions en ligne sont fermées pour cette compétition<?= $c['registration_closes_at'] ? ' (clôture le ' . e(fr_datetime($c['registration_closes_at'])) . ')' : '' ?>. Pour toute modification, contactez l'organisation.</div>
<?php else: ?><div class="alert alert-info small">Inscriptions ouvertes jusqu'au <strong><?= e(fr_datetime($c['registration_closes_at'])) ?></strong>. Vous pouvez modifier ou retirer vos inscriptions jusqu'à cette date.</div><?php endif; ?>
<?php $active = array_filter($rows, fn ($r) => in_array($r['status'], ['pending', 'confirmed'], true)); ?>
<section class="card" style="margin-bottom:1.2rem">
  <h2 style="font-size:1.15rem"><?= count($active) ?> judoka(s) inscrit(s)</h2>
  <?php if (!$rows): ?><div class="empty"><strong>Aucune inscription</strong>Inscrivez vos judokas avec le formulaire ci-dessous.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Judoka</th><th>Né(e) le</th><th>Catégorie</th><th>Poids</th><th>Statut</th><th>Présence</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr<?= in_array($r['status'], ['withdrawn', 'rejected'], true) ? ' style="opacity:.55"' : '' ?>>
      <td><strong><?= e($r['last_name']) ?></strong> <?= e($r['first_name']) ?></td><td><?= e(date('d/m/Y', strtotime($r['birth_date']))) ?></td>
      <td><?= e(CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex'])) ?></td><td><?= $r['weight_label'] ? e($r['weight_label']) . ' kg' : ($r['declared_weight_kg'] ? e(kg($r['declared_weight_kg'])) : '—') ?></td>
      <td><span class="badge <?= $r['status'] === 'confirmed' ? 'badge-green' : ($r['status'] === 'pending' ? 'badge-orange' : '') ?>"><?= e(RegistrationService::STATUS[$r['status']]) ?></span></td>
      <td class="small"><?= e(RegistrationService::ATTENDANCE[$r['attendance_status']]) ?></td>
      <td class="actions"><?php if ($canEdit && in_array($r['status'], ['pending', 'confirmed'], true)): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($b . '/inscriptions/' . $r['id'])) ?>">Modifier</a>
        <form method="post" action="<?= e(url($b . '/inscriptions/' . $r['id'] . '/retirer')) ?>" data-confirm="Retirer <?= e($r['first_name'] . ' ' . $r['last_name']) ?> de la compétition ?" style="display:inline"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Retirer</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</section>
<?php if ($canEdit): ?>
<section class="card" style="margin-bottom:1.2rem">
  <h2 style="font-size:1.15rem">Inscrire un judoka</h2>
  <?php if ($athletes): ?>
    <form method="post" action="<?= e(url($b . '/inscriptions')) ?>" class="btn-group" style="margin-bottom:1rem"><?= csrf_field() ?>
      <label for="athlete_id">Déjà inscrit(e) lors d'une compétition précédente :</label>
      <select id="athlete_id" name="athlete_id" style="width:auto;max-width:320px"><?php foreach ($athletes as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['last_name'] . ' ' . $a['first_name'] . ' (' . date('Y', strtotime($a['birth_date'])) . ')') ?></option><?php endforeach; ?></select>
      <span class="small muted">puis choisissez sa catégorie de poids :</span>
      <select name="weight_category_id" aria-label="Catégorie de poids" style="width:auto"><option value="">—</option><?php foreach ($formConfig['weights'] as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['label']) ?> kg (<?= e($w['sex']) ?>, <?= e(array_column($formConfig['ages'], 'name', 'id')[$w['age']] ?? '') ?>)</option><?php endforeach; ?></select>
      <button class="btn btn-ghost" type="submit">Inscrire</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= e(url($b . '/inscriptions')) ?>"><?= csrf_field() ?>
    <?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?>
    <button class="btn btn-red btn-lg" type="submit" style="margin-top:1rem">Inscrire ce judoka</button>
  </form>
</section>
<section class="card"><h2 style="font-size:1.1rem">Importer un fichier (CSV)</h2>
  <p class="small muted">Colonnes : <code>nom;prenom;sexe;date_naissance;categorie_poids;poids_declare;grade;licence</code> — sexe F/M, date JJ/MM/AAAA, catégorie de poids « -28 ». Les lignes en erreur sont listées et ne sont pas importées.</p>
  <form method="post" enctype="multipart/form-data" action="<?= e(url($b . '/import')) ?>" class="btn-group"><?= csrf_field() ?><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-ghost" type="submit">Importer</button></form>
</section>
<?php endif; ?>
