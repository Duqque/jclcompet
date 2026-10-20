<?php $v = fn ($k, $d = '') => old($k, $club[$k] ?? $d); ?>
<?php include dirname(__DIR__) . '/_profiles_tabs.php'; ?>
<h2 style="margin-bottom:1rem"><?= $club ? e($club['name']) : 'Nouveau club' ?></h2>
<div class="grid grid-2">
<form method="post" enctype="multipart/form-data" action="<?= e(url($club ? '/admin/clubs/' . $club['id'] : '/admin/clubs')) ?>" class="card"><?= csrf_field() ?>
  <div class="form-grid">
    <div class="field span-all"><label class="required" for="name">Nom</label><input id="name" name="name" required value="<?= e($v('name')) ?>"></div>
    <div class="field"><label for="short_name">Nom court (écrans)</label><input id="short_name" name="short_name" maxlength="40" value="<?= e($v('short_name')) ?>"></div>
    <div class="field"><label for="city">Ville</label><input id="city" name="city" value="<?= e($v('city')) ?>"></div>
    <div class="field"><label for="department">Département</label><input id="department" name="department" maxlength="3" value="<?= e($v('department')) ?>"></div>
    <div class="field"><label for="country">Pays (code)</label><input id="country" name="country" maxlength="2" value="<?= e($v('country', 'FR')) ?>"></div>
    <div class="field"><label for="contact_name">Nom du contact</label><input id="contact_name" name="contact_name" value="<?= e($v('contact_name')) ?>"></div>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($v('email')) ?>"></div>
    <div class="field"><label for="phone">Téléphone</label><input id="phone" name="phone" value="<?= e($v('phone')) ?>"></div>
    <div class="field"><label for="facebook_url">Page Facebook</label><input id="facebook_url" name="facebook_url" type="url" value="<?= e($v('facebook_url')) ?>"></div>
    <div class="field"><label for="website_url">Site web</label><input id="website_url" name="website_url" type="url" value="<?= e($v('website_url')) ?>"></div>
    <div class="field"><label for="logo">Logo</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><?php if (!empty($club['logo_path'])): ?><img src="<?= e(url('/' . $club['logo_path'])) ?>" alt="Logo actuel" style="width:80px;margin-top:.4rem"><label class="check"><input type="checkbox" name="remove_logo" value="1"> Retirer le logo</label><?php endif; ?></div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($v('is_active', 1)) ?>> Club actif</label>
  </div>
  <button class="btn btn-red" type="submit" style="margin-top:1rem">Enregistrer</button>
</form>
<?php if ($club): ?>
<section class="card"><h2 style="font-size:1.15rem">Comptes entraîneurs</h2>
  <?php if (!$accounts): ?><p class="muted">Aucun compte : le club ne peut pas encore inscrire ses athlètes.</p><?php else: ?>
  <ul style="list-style:none;padding:0;margin:0 0 1rem"><?php foreach ($accounts as $a): ?><li style="display:flex;justify-content:space-between;gap:.5rem;padding:.4rem 0;border-bottom:1px solid var(--line)"><span><?= e($a['display_name']) ?><br><span class="small muted"><?= e($a['email']) ?><?= $a['must_change_password'] ? ' · mot de passe provisoire' : '' ?><?= $a['last_login_at'] ? ' · connecté le ' . e(short_datetime($a['last_login_at'])) : '' ?></span></span>
    <form method="post" action="<?= e(url('/admin/clubs/' . $club['id'] . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="account_toggle"><input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $a['is_active'] ? 'Désactiver' : 'Réactiver' ?></button></form></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/clubs/' . $club['id'] . '/action')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="op" value="account_create">
    <div class="field"><label>Nom affiché</label><input name="display_name" required></div>
    <div class="field"><label>E-mail (identifiant)</label><input name="email" type="email" required></div>
    <div class="field"><label>Mot de passe provisoire</label><input name="password" type="text" minlength="12" required autocomplete="off"><span class="hint">12 caractères minimum avec lettres et chiffres ; à changer à la première connexion.</span></div>
    <button class="btn btn-ghost" type="submit">Créer le compte entraîneur</button></form>
</section>
<?php endif; ?>
</div>
