<?php $profileActions = '<a class="btn" href="#nouveau">' . icon('plus') . 'Créer un profil</a>'; include __DIR__ . '/_profiles_tabs.php'; ?>
<?php if ($requests): ?>
<section class="acc-requests" id="demandes">
  <h2>Demandes de compte coach <span class="count"><?= count($requests) ?></span></h2>
  <div class="acc-grid">
  <?php foreach ($requests as $q): ?>
    <article class="acc-card">
      <header>
        <?php if ($q['club_id']): $logo = $q['club_logo']; $name = $q['club_label']; $size = 44; include BASE_PATH . '/app/Views/partials/club_logo.php'; else: ?><span class="club-mono"><?= icon('plus') ?></span><?php endif; ?>
        <div><strong><?= e($q['display_name']) ?></strong><small><?= e($q['club_id'] ? ($q['club_short'] ?: $q['club_label']) : $q['club_name'] . ' · nouveau club' . ($q['club_city'] ? ' (' . $q['club_city'] . ')' : '')) ?></small></div>
        <span class="badge"><?= $q['source'] === 'app' ? 'Application' : 'Site' ?></span>
      </header>
      <dl><dt>E-mail</dt><dd><?= e($q['email']) ?></dd><dt>Téléphone</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $q['phone'])) ?>"><?= e($q['phone']) ?></a></dd><dt>Demande</dt><dd><?= e(short_datetime($q['created_at'])) ?></dd></dl>
      <?php if ($q['message']): ?><p class="acc-msg"><?= e($q['message']) ?></p><?php endif; ?>
      <div class="acc-actions">
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="approve_request"><input type="hidden" name="request_id" value="<?= (int) $q['id'] ?>">
          <?php if (!$q['club_id']): ?><label class="sr-only" for="acc-club-<?= (int) $q['id'] ?>">Club</label>
            <select id="acc-club-<?= (int) $q['id'] ?>" name="club_id"><option value="">Créer « <?= e($q['club_name']) ?> »</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"><?= e($cl['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
          <button class="btn btn-red btn-sm" type="submit"><?= icon('check') ?>Accepter</button>
        </form>
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" data-confirm="Refuser la demande de <?= e($q['display_name']) ?> ? Le compte restera inactif."><?= csrf_field() ?><input type="hidden" name="op" value="reject_request"><input type="hidden" name="request_id" value="<?= (int) $q['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit">Refuser</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if (array_filter($rows, fn ($u) => $u['is_demo'] && $u['is_active'])): ?><div class="alert alert-warning">Des comptes de démonstration sont actifs. Désactivez-les avant toute utilisation réelle.
  <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" data-confirm="Désactiver tous les comptes de démonstration (sauf le vôtre) ?" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="op" value="delete_demo"><button class="btn btn-sm btn-danger" type="submit">Désactiver les comptes de démonstration</button></form></div><?php endif; ?>
<div class="table-wrap" style="margin-bottom:1.2rem"><table>
  <thead><tr><th></th><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>Club / tapis</th><th>Dernière connexion</th><th>État</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $u): ?>
    <tr<?= $u['is_active'] ? '' : ' style="opacity:.55"' ?>><td style="width:48px"><span class="avatar-mono"><?= e(initials($u['display_name'])) ?></span></td><td><strong><?= e($u['display_name']) ?></strong><?= $u['is_demo'] ? ' <span class="badge badge-yellow">démo</span>' : '' ?></td><td class="small"><?= e($u['email']) ?></td><td><?= e($u['role_name']) ?></td><td class="small"><?= e($u['club_name'] ?? '') ?><?= $u['tatamis'] ? 'Tapis ' . e($u['tatamis']) : '' ?></td>
      <td class="small"><?= e($u['last_login_at'] ? short_datetime($u['last_login_at']) : '—') ?></td><td><?= $u['is_active'] ? '<span class="badge badge-green">actif</span>' : '<span class="badge">désactivé</span>' ?><?= $u['must_change_password'] ? ' <span class="badge badge-orange">mdp provisoire</span>' : '' ?></td>
      <td class="actions"><details><summary class="btn btn-ghost btn-sm">Gérer</summary><div style="text-align:left;padding:.5rem 0;display:grid;gap:.4rem;min-width:240px">
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" class="btn-group"><?= csrf_field() ?><input type="hidden" name="op" value="reset_password"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input name="password" type="text" minlength="12" required placeholder="Mot de passe provisoire" aria-label="Mot de passe provisoire" autocomplete="off" style="min-height:34px"><button class="btn btn-ghost btn-sm" type="submit">Réinitialiser</button></form>
      </div></details></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<section class="card" id="nouveau"><h2 style="font-size:1.15rem">Créer un profil</h2>
  <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="create">
    <div class="form-grid">
      <div class="field"><label class="required">Nom affiché</label><input name="display_name" required></div>
      <div class="field"><label class="required">E-mail (identifiant)</label><input name="email" type="email" required></div>
      <div class="field"><label class="required">Rôle</label><select name="role_id"><?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $role === $r['code'] ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select><span class="hint">Commissaire : saisie des combats · Vidéo-arbitrage : régie vidéo des tapis (caméras, ralentis, direct YouTube) · Accueil : présences · Vendeur : caisse.</span></div>
      <div class="field"><label>Club (comptes entraîneurs)</label><select name="club_id"><option value="">—</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"><?= e($cl['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Téléphone</label><input name="phone"></div>
      <div class="field"><label class="required">Mot de passe provisoire</label><input name="password" type="text" minlength="12" required autocomplete="off"></div>
    </div>
    <button class="btn btn-red" type="submit" style="margin-top:.8rem">Créer le compte</button>
  </form>
</section>
