<?php use App\Services\CategoryService; use App\Services\RegistrationService; $base = '/admin/competitions/' . $c['id'];
$heading = 'Inscriptions'; $actions = '<a class="btn btn-red" href="' . e(url($base . '/inscriptions/nouvelle')) . '">Inscrire un athlète</a><a class="btn btn-ghost" href="' . e(url($base . '/inscriptions/export.csv')) . '">Exporter (CSV)</a>'; include dirname(__DIR__) . '/_head.php'; ?>
<form method="get" class="filters card" style="padding:.8rem 1rem">
  <div class="field wide"><label for="q">Recherche</label><input id="q" name="q" type="search" value="<?= e($f['q'] ?? '') ?>" placeholder="Nom, prénom, club"></div>
  <div class="field"><label for="club_id">Club</label><select id="club_id" name="club_id" data-autosubmit><option value="">Tous</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"<?= selected($f['club_id'] ?? '', $cl['id']) ?>><?= e($cl['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="age">Catégorie</label><select id="age" name="age_category_id" data-autosubmit><option value="">Toutes</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($f['age_category_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="sex">Sexe</label><select id="sex" name="sex" data-autosubmit><option value="">Tous</option><option value="F"<?= selected($f['sex'] ?? '', 'F') ?>>Filles</option><option value="M"<?= selected($f['sex'] ?? '', 'M') ?>>Garçons</option></select></div>
  <div class="field"><label for="att">Présence</label><select id="att" name="attendance" data-autosubmit><option value="">Toutes</option><?php foreach (RegistrationService::ATTENDANCE as $k => $l): ?><option value="<?= $k ?>"<?= selected($f['attendance'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="st">Statut</label><select id="st" name="status" data-autosubmit><option value="active"<?= selected($f['status'] ?? 'active', 'active') ?>>Actives</option><option value="pending"<?= selected($f['status'] ?? '', 'pending') ?>>À valider</option><option value="withdrawn"<?= selected($f['status'] ?? '', 'withdrawn') ?>>Retirées</option><option value="rejected"<?= selected($f['status'] ?? '', 'rejected') ?>>Refusées</option><option value="all"<?= selected($f['status'] ?? '', 'all') ?>>Toutes</option></select></div>
  <button class="btn btn-ghost" type="submit">Filtrer</button>
</form>
<p class="muted small"><?= (int) $total ?> inscription(s)</p>
<?php if (!$rows): ?><div class="empty"><strong>Aucune inscription</strong>Les clubs inscrivent leurs athlètes depuis leur espace ; vous pouvez aussi inscrire ou importer un fichier CSV.</div><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Athlète</th><th>Club</th><th>Catégorie</th><th>Poids</th><th>Statut</th><th>Présence</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= e(url($base . '/inscriptions/' . $r['id'])) ?>"><strong><?= e($r['last_name']) ?></strong> <?= e($r['first_name']) ?></a><br><span class="small muted"><?= e(date('d/m/Y', strtotime($r['birth_date']))) ?><?= $r['grade'] ? ' · ' . e($r['grade']) : '' ?></span></td>
      <td><?= e($r['club_name'] ?? $r['club_exception_label']) ?></td>
      <td><?= e(CategoryService::label(['name' => $r['age_name'], 'name_m' => null, 'name_f' => null] + $r, $r['sex'])) ?><?= $r['natural_age_category_id'] && $r['natural_age_category_id'] !== $r['age_category_id'] ? ' <span class="badge badge-blue">surclassé</span>' : '' ?></td>
      <td class="nowrap"><?= $r['weight_label'] ? e($r['weight_label']) . ' kg' : '—' ?><?= $r['measured_weight_kg'] ? '<br><span class="small muted">pesé ' . e(kg($r['measured_weight_kg'])) . '</span>' : ($r['declared_weight_kg'] ? '<br><span class="small muted">' . e(kg($r['declared_weight_kg'])) . '</span>' : '') ?></td>
      <td><span class="badge <?= $r['status'] === 'confirmed' ? 'badge-green' : ($r['status'] === 'pending' ? 'badge-orange' : 'badge-red') ?>"><?= e(RegistrationService::STATUS[$r['status']]) ?></span><?= $r['is_late'] ? ' <span class="badge badge-orange">tardive</span>' : '' ?></td>
      <td><span class="badge <?= ['present' => 'badge-green', 'absent' => 'badge-red', 'to_check' => 'badge-orange'][$r['attendance_status']] ?? '' ?>"><?= e(RegistrationService::ATTENDANCE[$r['attendance_status']]) ?></span></td>
      <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url($base . '/inscriptions/' . $r['id'])) ?>">Ouvrir</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php $query = $f; include BASE_PATH . '/app/Views/partials/pagination.php'; ?>
<?php endif; ?>
<section class="card" style="margin-top:1.4rem"><h2 style="font-size:1.1rem">Importer des inscriptions (CSV)</h2>
  <p class="small muted">Colonnes : <code>nom;prenom;sexe;date_naissance;poids_declare;grade;licence;club;categorie_poids</code>. Sexe F/M, date JJ/MM/AAAA, club = nom exact du club. Chaque ligne est contrôlée ; les lignes en erreur sont listées et ne sont pas importées.</p>
  <form method="post" enctype="multipart/form-data" action="<?= e(url($base . '/inscriptions/import')) ?>" class="btn-group"><?= csrf_field() ?><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-ghost" type="submit">Importer</button></form>
</section>
