<div class="page-head"><div><p class="eyebrow">Club</p><h1>Informations du club</h1></div></div>
<form method="post" enctype="multipart/form-data" action="<?= e(url('/espace-club/profil')) ?>" class="card" style="max-width:760px"><?= csrf_field() ?>
  <p class="muted small">Le nom et la ville du club sont gérés par l'organisation. Vous pouvez mettre à jour vos coordonnées et votre logo (affiché sur les écrans TV et le programme).</p>
  <div class="form-grid">
    <div class="field"><label>Club</label><input value="<?= e($club['name']) ?>" disabled></div>
    <div class="field"><label class="required" for="contact_name">Nom du contact</label><input id="contact_name" name="contact_name" required value="<?= e(old('contact_name', $club['contact_name'])) ?>"></div>
    <div class="field"><label class="required" for="email">E-mail</label><input id="email" name="email" type="email" required value="<?= e(old('email', $club['email'])) ?>"></div>
    <div class="field"><label for="phone">Téléphone</label><input id="phone" name="phone" value="<?= e(old('phone', $club['phone'])) ?>"></div>
    <div class="field"><label for="facebook_url">Page Facebook</label><input id="facebook_url" name="facebook_url" type="url" value="<?= e(old('facebook_url', $club['facebook_url'])) ?>"></div>
    <div class="field"><label for="website_url">Site web</label><input id="website_url" name="website_url" type="url" value="<?= e(old('website_url', $club['website_url'])) ?>"></div>
    <div class="field"><label for="logo">Logo</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><?php if ($club['logo_path']): ?><img src="<?= e(url('/' . $club['logo_path'])) ?>" alt="Logo actuel" style="width:80px;margin-top:.4rem"><?php endif; ?></div>
  </div>
  <button class="btn btn-red" type="submit" style="margin-top:1rem">Enregistrer</button>
</form>
