<?php /** @var array $rows */ ?>
<?php if (!$rows): ?><div class="empty"><strong>Aucune action enregistrée</strong></div><?php else: ?>
<div class="table-wrap"><table class="table-compact">
  <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Élément</th><th>Détail</th></tr></thead>
  <tbody><?php foreach ($rows as $l): ?>
    <tr><td class="nowrap small"><?= e(short_datetime($l['created_at'])) ?></td><td class="small"><?= e(preg_replace('/ <.*>$/', '', (string) $l['user_label'])) ?></td>
      <td><code class="small"><?= e($l['action']) ?></code></td><td class="small"><?= e($l['entity_type']) ?> <?= e($l['entity_id']) ?></td>
      <td class="small" style="max-width:420px;overflow-wrap:anywhere"><?php if ($l['reason']): ?><strong>Motif :</strong> <?= e($l['reason']) ?><br><?php endif; ?><?= e(mb_strimwidth(trim(($l['old_values'] ? 'avant ' . $l['old_values'] . ' ' : '') . ($l['new_values'] ? '→ ' . $l['new_values'] : '')), 0, 220, '…')) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
