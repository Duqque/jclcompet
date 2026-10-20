<?php use App\Services\CompetitionService; ?>
<div class="page-head"><div><p class="eyebrow">CRM</p><h1>Compétitions</h1></div><a class="btn btn-red" href="<?= e(url('/admin/competitions/nouvelle')) ?>">Créer une compétition</a></div>
<?php if (!$rows): ?><div class="empty"><strong>Aucune compétition</strong>Créez votre première compétition pour ouvrir les inscriptions.</div><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Compétition</th><th>Statut</th><th>Inscrits</th><th>Tapis</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $c): ?>
    <tr><td class="nowrap"><?= e(date('d/m/Y', strtotime($c['event_date']))) ?></td>
      <td><a href="<?= e(url('/admin/competitions/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a><?= $c['edition_label'] ? ' <span class="muted">' . e($c['edition_label']) . '</span>' : '' ?><?= $c['is_demo'] ? ' <span class="badge badge-yellow">démo</span>' : '' ?></td>
      <td><span class="badge <?= $c['status'] === 'in_progress' ? 'badge-red' : ($c['status'] === 'registrations_open' ? 'badge-green' : ($c['status'] === 'archived' ? 'badge-ink' : '')) ?>"><?= e(CompetitionService::statusLabel($c['status'])) ?></span></td>
      <td class="num"><?= (int) $c['regs'] ?></td><td class="num"><?= (int) $c['mat_count'] ?></td>
      <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/competitions/' . $c['id'])) ?>">Ouvrir</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
