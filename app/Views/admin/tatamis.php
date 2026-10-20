<?php use App\Services\TvTokenService; $heading = 'Tapis et écrans TV'; include __DIR__ . '/_head.php'; $u = url('/admin/competitions/' . $c['id'] . '/tapis');
$labels = ['online' => ['badge-green', 'Connecté'], 'idle' => ['badge-orange', 'Actif, non connecté'], 'disabled' => ['', 'Désactivé'], 'expired' => ['', 'Expiré'], 'revoked' => ['badge-ink', 'Révoqué']]; ?>
<p class="muted">Chaque tapis a sa page TV (<code>/tv/tapis/N</code>) ouverte avec un <strong>jeton d'affichage</strong> : lecture seule, limité au tapis, révocable, à durée limitée. N'utilisez jamais un compte administrateur sur un téléviseur. Le nombre de tapis se règle dans « Informations ».</p>
<?php if ($newLink): ?><div class="alert alert-success"><strong>Nouveau lien d'affichage</strong> (affiché une seule fois) :<br><code style="word-break:break-all"><?= e($newLink['url']) ?></code><br><button class="btn btn-sm btn-ghost" type="button" data-copy="<?= e($newLink['url']) ?>" style="margin-top:.5rem">Copier le lien</button> <a class="btn btn-sm btn-ghost" href="<?= e($newLink['url']) ?>" target="_blank" style="margin-top:.5rem">Ouvrir l'écran ↗</a></div><?php endif; ?>
<div class="grid grid-2">
<?php foreach ($tatamis as $t): ?>
  <section class="card"<?= (int) $t['is_active'] ? '' : ' style="opacity:.7"' ?>>
    <div class="card-head"><h2 style="margin:0;font-size:1.25rem">Tapis <?= (int) $t['number'] ?><?= $t['name'] && $t['name'] !== 'Tapis ' . $t['number'] ? ' — ' . e($t['name']) : '' ?></h2>
      <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $t['is_active'] ? 'Rendre indisponible' : 'Rendre disponible' ?></button></form></div>
    <h3>Commissaires sportifs</h3>
    <?php if (empty($assign[$t['id']])): ?><p class="muted small">Aucun commissaire affecté.</p><?php else: ?>
      <ul style="list-style:none;padding:0;margin:0 0 .6rem"><?php foreach ($assign[$t['id']] as $a): ?><li style="display:flex;justify-content:space-between;gap:.5rem;padding:.3rem 0;border-bottom:1px solid var(--line)"><span><?= e($a['display_name']) ?> <?= $a['is_active'] ? '<span class="badge badge-green">tapis actif</span>' : '' ?></span>
        <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="unassign"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $a['user_id'] ?>"><button class="btn-link small" type="submit">Retirer</button></form></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" action="<?= e($u) ?>" class="btn-group"><?= csrf_field() ?><input type="hidden" name="op" value="assign"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>">
      <label class="sr-only" for="cm<?= $t['id'] ?>">Commissaire</label><select id="cm<?= $t['id'] ?>" name="user_id" style="max-width:240px"><?php foreach ($commissioners as $cm): ?><option value="<?= (int) $cm['id'] ?>"><?= e($cm['display_name']) ?></option><?php endforeach; ?></select>
      <button class="btn btn-ghost btn-sm" type="submit"<?= $commissioners ? '' : ' disabled' ?>>Affecter</button></form>
    <?php if (!$commissioners): ?><p class="small muted">Créez d'abord des comptes « commissaire » dans <a href="<?= e(url('/admin/utilisateurs')) ?>">Comptes</a>.</p><?php endif; ?>

    <h3 style="margin-top:1.2rem">Écran TV</h3>
    <?php foreach ($tokens[$t['id']] ?? [] as $tk): $st = TvTokenService::status($tk); ?>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;padding:.35rem 0;border-bottom:1px solid var(--line)">
        <span class="badge <?= $labels[$st][0] ?>"><?= $labels[$st][1] ?></span>
        <span class="small"><?= e($tk['label']) ?> · <code><?= e($tk['token_hint']) ?>…</code> · expire le <?= e(short_datetime($tk['expires_at'])) ?><?= $tk['last_seen_at'] ? ' · vu ' . e(short_datetime($tk['last_seen_at'])) : '' ?></span>
        <?php if (!in_array($st, ['revoked'], true)): ?>
          <form method="post" action="<?= e($u) ?>"><?= csrf_field() ?><input type="hidden" name="token_id" value="<?= (int) $tk['id'] ?>"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>">
            <?php if ((int) $tk['is_active']): ?><button class="btn btn-ghost btn-sm" name="op" value="tv_disable">Désactiver</button><?php else: ?><button class="btn btn-ghost btn-sm" name="op" value="tv_enable">Réactiver</button><?php endif; ?>
            <button class="btn btn-danger btn-sm" name="op" value="tv_revoke">Révoquer</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="btn-group" style="margin-top:.6rem">
      <form method="post" action="<?= e($u) ?>" class="btn-group"><?= csrf_field() ?><input type="hidden" name="op" value="tv_create"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>">
        <label class="small" for="d<?= $t['id'] ?>">Validité</label><select id="d<?= $t['id'] ?>" name="days" style="width:auto;min-height:34px"><option value="1">1 jour</option><option value="2">2 jours</option><option value="7" selected>7 jours</option><option value="30">30 jours</option></select>
        <button class="btn btn-sm" type="submit">Créer un lien d'affichage</button></form>
      <form method="post" action="<?= e($u) ?>" data-confirm="Réinitialiser l'affichage du tapis <?= (int) $t['number'] ?> ? Les liens actuels cesseront de fonctionner."><?= csrf_field() ?><input type="hidden" name="op" value="tv_reset"><input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Réinitialiser l'affichage</button></form>
    </div>
  </section>
<?php endforeach; ?>
</div>
