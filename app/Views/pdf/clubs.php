<?php foreach (['Par points' => [$byPoints, 'rank_points'], 'Par médailles' => [$byMedals, 'rank_medals']] as $label => [$rows, $rk]): ?>
  <h2>Classement des clubs — <?= e($label) ?></h2>
  <table><thead><tr><th>Rang</th><th>Club</th><th>Participants</th><th>Points</th><th>Or</th><th>Argent</th><th>Bronze</th><th>4e</th><th>5e</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="num"><?= (int) $r[$rk] ?></td><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['participants'] ?></td><td class="num"><?= (int) $r['points'] ?></td><td class="num"><?= (int) $r['gold'] ?></td><td class="num"><?= (int) $r['silver'] ?></td><td class="num"><?= (int) $r['bronze'] ?></td><td class="num"><?= (int) $r['fourth'] ?></td><td class="num"><?= (int) $r['fifth'] ?></td></tr><?php endforeach; ?>
  </tbody></table>
<?php endforeach; ?>
