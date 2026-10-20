<div class="page-head"><div><p class="eyebrow">Organisation</p><h1>Comptes</h1></div></div>
<?php if (array_filter($rows, fn ($u) => $u['is_demo'] && $u['is_active'])): ?><div class="alert alert-warning">Des comptes de démonstration sont actifs. Désactivez-les avant toute utilisation réelle.
  <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" data-confirm="Désactiver tous les comptes de démonstration (sauf le vôtre) ?" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="op" value="delete_demo"><button class="btn btn-sm btn-danger" type="submit">Désactiver les comptes de démonstration</button></form></div><?php endif; ?>
<div class="table-wrap" style="margin-bottom:1.2rem"><table>
  <thead><tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>Club</th><th>Dernière connexion</th><th>État</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $u): ?>
    <tr<?= $u['is_active'] ? '' : ' style="opacity:.55"' ?>><td><strong><?= e($u['display_name']) ?></strong><?= $u['is_demo'] ? ' <span class="badge badge-yellow">démo</span>' : '' ?></td><td class="small"><?= e($u['email']) ?></td><td><?= e($u['role_name']) ?></td><td class="small"><?= e($u['club_name']) ?></td>
      <td class="small"><?= e($u['last_login_at'] ? short_datetime($u['last_login_at']) : '—') ?></td><td><?= $u['is_active'] ? '<span class="badge badge-green">actif</span>' : '<span class="badge">désactivé</span>' ?><?= $u['must_change_password'] ? ' <span class="badge badge-orange">mdp provisoire</span>' : '' ?></td>
      <td class="actions"><details><summary class="btn btn-ghost btn-sm">Gérer</summary><div style="text-align:left;padding:.5rem 0;display:grid;gap:.4rem;min-width:240px">
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
        <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" class="btn-group"><?= csrf_field() ?><input type="hidden" name="op" value="reset_password"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input name="password" type="text" minlength="12" required placeholder="Mot de passe provisoire" aria-label="Mot de passe provisoire" autocomplete="off" style="min-height:34px"><button class="btn btn-ghost btn-sm" type="submit">Réinitialiser</button></form>
      </div></details></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<section class="card"><h2 style="font-size:1.15rem">Créer un compte</h2>
  <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="create">
    <div class="form-grid">
      <div class="field"><label class="required">Nom affiché</label><input name="display_name" required></div>
      <div class="field"><label class="required">E-mail (identifiant)</label><input name="email" type="email" required></div>
      <div class="field"><label class="required">Rôle</label><select name="role_id"><?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select><span class="hint">Commissaire : saisie des combats · Accueil : présences · Vendeur : caisse.</span></div>
      <div class="field"><label>Club (comptes entraîneurs)</label><select name="club_id"><option value="">—</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"><?= e($cl['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Téléphone</label><input name="phone"></div>
      <div class="field"><label class="required">Mot de passe provisoire</label><input name="password" type="text" minlength="12" required autocomplete="off"></div>
    </div>
    <button class="btn btn-red" type="submit" style="margin-top:.8rem">Créer le compte</button>
  </form>
</section>
