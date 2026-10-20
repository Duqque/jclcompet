<?php use App\Services\RegistrationService; ?>
<p><?= count($rows) ?> participant(s)</p>
<table><thead><tr><th>Nom</th><th>Prénom</th><th>Sexe</th><th>Naissance</th><th>Club</th><th>Catégorie</th><th>Poids</th><th>Présence</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['last_name']) ?></td><td><?= e($r['first_name']) ?></td><td class="num"><?= e($r['sex']) ?></td><td><?= e(date('d/m/Y', strtotime($r['birth_date']))) ?></td><td><?= e($r['club_name'] ?? $r['club_exception_label']) ?></td><td><?= e($r['age_name']) ?></td><td><?= e($r['weight_label'] ? $r['weight_label'] . ' kg' : '') ?></td><td><?= e(RegistrationService::ATTENDANCE[$r['attendance_status']]) ?></td></tr><?php endforeach; ?>
</tbody></table>
