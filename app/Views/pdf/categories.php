<?php use App\Services\CategoryService; $groups = []; foreach ($rows as $r) { $groups[CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex']) . ($r['weight_label'] ? ' ' . $r['weight_label'] . ' kg' : '')][] = $r; } ?>
<?php foreach ($groups as $label => $list): ?>
  <h2><?= e($label) ?> — <?= count($list) ?> judoka(s)</h2>
  <table><thead><tr><th style="width:6%">N°</th><th>Nom</th><th>Prénom</th><th>Club</th><th style="width:12%">Grade</th><th style="width:12%">Présent</th></tr></thead><tbody>
  <?php foreach ($list as $i => $r): ?><tr><td class="num"><?= $i + 1 ?></td><td><?= e($r['last_name']) ?></td><td><?= e($r['first_name']) ?></td><td><?= e($r['club_name'] ?? $r['club_exception_label']) ?></td><td><?= e($r['grade']) ?></td><td class="num"><?= $r['attendance_status'] === 'present' ? 'oui' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endforeach; ?>
