<?php
use App\Services\CategoryService;
use App\Services\RegistrationService;
$base = '/admin/competitions/' . $c['id'];
$q = fn (array $over) => '?' . http_build_query(array_filter(array_merge($f, ['vue' => $mode], $over), fn ($v) => $v !== null && $v !== ''));
$heading = 'Inscrits';
$sub = $stats['registered'] . ' judokas · ' . $stats['clubs'] . ' clubs · ' . $stats['present'] . ' présents';
$actions = '<a class="btn btn-ghost" href="' . e(url($base . '/inscriptions/export.csv')) . '">' . icon('download') . 'Exporter</a>'
    . '<a class="btn" href="' . e(url($base . '/inscriptions/nouvelle')) . '">' . icon('plus') . 'Inscrire un judoka</a>';
include dirname(__DIR__) . '/_head.php';
$attLabel = ['expected' => ['', 'Attendu'], 'present' => ['badge-green', 'Présent'], 'absent' => ['badge-red', 'Absent'], 'to_check' => ['badge-orange', 'À vérifier']];
?>
<div class="stats">
  <div class="stat accent"><strong><?= (int) $stats['registered'] ?></strong><span>inscrits</span></div>
  <div class="stat"><strong><?= (int) $stats['clubs'] ?></strong><span>clubs</span></div>
  <div class="stat"><strong><?= (int) $stats['present'] ?><small> / <?= (int) $stats['registered'] ?></small></strong><span>présents</span></div>
  <div class="stat<?= $stats['absent'] ? ' warn' : '' ?>"><strong><?= (int) $stats['absent'] ?></strong><span>absents / forfaits</span></div>
  <?php if ($stats['pending_validation']): ?><a class="stat warn" style="text-decoration:none;color:inherit" href="<?= e($q(['status' => 'pending', 'page' => null])) ?>"><strong><?= (int) $stats['pending_validation'] ?></strong><span>à valider</span></a><?php endif; ?>
</div>

<?php if ($clubChips): ?>
<nav class="chips" aria-label="Filtrer par club">
  <a class="chip plain" href="<?= e($q(['club_id' => null, 'page' => null])) ?>"<?= empty($f['club_id']) ? ' aria-current="true"' : '' ?>>Tous les clubs <span class="n"><?= (int) $stats['registered'] ?></span></a>
  <?php foreach ($clubChips as $cl): ?>
    <a class="chip" href="<?= e($q(['club_id' => $cl['id'], 'page' => null])) ?>"<?= (string) ($f['club_id'] ?? '') === (string) $cl['id'] ? ' aria-current="true"' : '' ?>>
      <?php $logo = $cl['logo_path']; $name = $cl['name']; $size = 24; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?><?= e($cl['short_name'] ?: $cl['name']) ?> <span class="n"><?= (int) $cl['n'] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<form method="get" class="toolbar">
  <input type="hidden" name="vue" value="<?= e($mode) ?>"><?php if (!empty($f['club_id'])): ?><input type="hidden" name="club_id" value="<?= (int) $f['club_id'] ?>"><?php endif; ?>
  <label class="search"><?= icon('search') ?><span class="sr-only">Rechercher</span><input type="search" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Rechercher un judoka ou un club…"></label>
  <select name="age_category_id" data-autosubmit aria-label="Catégorie"><option value="">Toutes catégories</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($f['age_category_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select name="sex" data-autosubmit aria-label="Sexe"><option value="">Filles et garçons</option><option value="F"<?= selected($f['sex'] ?? '', 'F') ?>>Filles</option><option value="M"<?= selected($f['sex'] ?? '', 'M') ?>>Garçons</option></select>
  <select name="attendance" data-autosubmit aria-label="Présence"><option value="">Toutes présences</option><?php foreach (RegistrationService::ATTENDANCE as $k => $l): ?><option value="<?= $k ?>"<?= selected($f['attendance'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <select name="status" data-autosubmit aria-label="Statut"><option value="active"<?= selected($f['status'] ?? 'active', 'active') ?>>Inscriptions actives</option><option value="pending"<?= selected($f['status'] ?? '', 'pending') ?>>À valider</option><option value="withdrawn"<?= selected($f['status'] ?? '', 'withdrawn') ?>>Retirées</option><option value="all"<?= selected($f['status'] ?? '', 'all') ?>>Toutes</option></select>
  <span style="flex:1"></span>
  <div class="segmented" role="group" aria-label="Affichage"><a href="<?= e($q(['vue' => 'cartes'])) ?>"<?= $mode === 'cartes' ? ' aria-current="page"' : '' ?>>Cartes</a><a href="<?= e($q(['vue' => 'liste'])) ?>"<?= $mode === 'liste' ? ' aria-current="page"' : '' ?>>Liste</a></div>
</form>

<?php if (!$rows): ?>
  <div class="empty"><strong>Aucun judoka</strong>Les clubs inscrivent leurs judokas depuis leur espace ; vous pouvez aussi inscrire ou importer un fichier.</div>
<?php elseif ($mode === 'cartes'): ?>
  <div class="profiles">
    <?php foreach ($rows as $r): $cat = CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex']); [$cls, $lab] = $attLabel[$r['attendance_status']]; ?>
      <a class="profile" href="<?= e(url($base . '/inscriptions/' . $r['id'])) ?>">
        <div class="top"><?= avatar($r['photo_document_id'] ? (int) $r['photo_document_id'] : null, $r['first_name'] . ' ' . $r['last_name']) ?>
          <div class="name"><?= e($r['first_name']) ?> <?= e($r['last_name']) ?><small><?= e(date('Y', strtotime($r['birth_date']))) ?><?= $r['grade'] ? ' · ' . e($r['grade']) : '' ?></small></div></div>
        <div class="tiles">
          <div class="tile"><span class="label">Catégorie</span><strong><?= e($cat) ?></strong></div>
          <div class="tile"><span class="label">Poids</span><strong><?= $r['weight_label'] ? e($r['weight_label']) . ' kg' : ($r['declared_weight_kg'] ? e(kg($r['declared_weight_kg'])) : '—') ?></strong></div>
        </div>
        <div class="foot"><span class="club"><?php $logo = $r['club_logo']; $name = $r['club_name'] ?? $r['club_exception_label'] ?? '?'; $size = 22; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?><span><?= e($r['club_short'] ?: ($r['club_name'] ?? $r['club_exception_label'])) ?></span></span>
          <span class="foot-badges"><?php if ($r['needs_validation']): ?><span class="badge badge-orange">à valider</span><?php elseif ($r['is_late']): ?><span class="badge badge-outline">tardive</span><?php elseif ($r['status'] !== 'confirmed'): ?><span class="badge"><?= e(RegistrationService::STATUS[$r['status']]) ?></span><?php endif; ?><span class="badge <?= $cls ?>"><span class="dot"></span><?= e($lab) ?></span></span></div>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th></th><th>Judoka</th><th>Club</th><th>Catégorie</th><th>Poids</th><th>Statut</th><th>Présence</th></tr></thead>
    <tbody><?php foreach ($rows as $r): [$cls, $lab] = $attLabel[$r['attendance_status']]; ?>
      <tr><td style="width:48px"><?= avatar($r['photo_document_id'] ? (int) $r['photo_document_id'] : null, $r['first_name'] . ' ' . $r['last_name']) ?></td>
        <td><a href="<?= e(url($base . '/inscriptions/' . $r['id'])) ?>"><strong><?= e($r['last_name']) ?></strong> <?= e($r['first_name']) ?></a><br><span class="small muted"><?= e(date('d/m/Y', strtotime($r['birth_date']))) ?></span></td>
        <td><?= e($r['club_name'] ?? $r['club_exception_label']) ?></td>
        <td><?= e(CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex'])) ?></td>
        <td class="nowrap"><?= $r['weight_label'] ? e($r['weight_label']) . ' kg' : '—' ?></td>
        <td><span class="badge <?= $r['status'] === 'confirmed' ? 'badge-green' : ($r['status'] === 'pending' ? 'badge-orange' : '') ?>"><?= e(RegistrationService::STATUS[$r['status']]) ?></span></td>
        <td><span class="badge <?= $cls ?>"><span class="dot"></span><?= e($lab) ?></span></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
<?php endif; ?>
<?php $query = array_merge($f, ['vue' => $mode]); include BASE_PATH . '/app/Views/partials/pagination.php'; ?>

<details class="card" style="margin-top:1.4rem"><summary style="cursor:pointer;font-weight:600"><?= icon('upload') ?> Importer des inscriptions (CSV)</summary>
  <p class="small muted" style="margin-top:.8rem">Colonnes : <code>nom;prenom;sexe;date_naissance;poids_declare;grade;licence;club;categorie_poids</code>. Chaque ligne est contrôlée ; les lignes en erreur sont listées et ne sont pas importées.</p>
  <form method="post" enctype="multipart/form-data" action="<?= e(url($base . '/inscriptions/import')) ?>" class="btn-group"><?= csrf_field() ?><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-ghost" type="submit">Importer</button></form>
</details>
