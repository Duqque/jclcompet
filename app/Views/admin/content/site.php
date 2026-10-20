<?php use App\Controllers\Admin\ContentController; use App\Services\ContentService; use App\Services\DocumentService; ?>
<div class="page-head"><div><p class="eyebrow">Gestion</p><h1>Site & photos</h1><p class="sub">Tous les textes, listes, partenaires et photos du site public.</p></div><a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank">Voir le site <?= icon('arrow') ?></a></div>
<nav class="subnav" aria-label="Rubriques"><?php foreach (ContentController::TABS as $k => $label): ?><a href="?onglet=<?= $k ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<p class="small muted">Le programme de chaque compétition (horaires, déroulement, loterie…) se modifie depuis la compétition : <em>Programme (contenus)</em>.</p>

<?php if ($tab === 'reglages'): ?>
<form method="post" action="<?= e(url('/admin/site')) ?>" class="card"><?= csrf_field() ?><input type="hidden" name="op" value="site_save">
  <div class="form-grid">
    <?php foreach (ContentService::SITE_KEYS as $k => $label): $long = in_array($k, ['club_presentation', 'home_cta_text', 'legal_notice', 'privacy_notice'], true); ?>
      <div class="field<?= $long ? ' span-all' : '' ?>"><label for="<?= $k ?>"><?= e($label) ?></label>
        <?php if ($long): ?><textarea id="<?= $k ?>" name="<?= $k ?>" rows="5"><?= e(ContentService::site($k)) ?></textarea><?php else: ?><input id="<?= $k ?>" name="<?= $k ?>" value="<?= e(ContentService::site($k)) ?>"><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-red" type="submit" style="margin-top:1rem">Enregistrer</button>
</form>

<?php elseif ($tab === 'pages'): ?>
<nav class="subnav"><?php foreach (ContentService::PAGES as $k => $label): if ($k === 'competition') continue; ?><a href="?onglet=pages&amp;page=<?= $k ?>"<?= $page === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<section class="card">
  <?php foreach ($sections as $sec): ?><details style="border-bottom:1px solid var(--line);padding:.45rem 0"><summary style="cursor:pointer"><strong><?= e($sec['title'] ?: '(sans titre)') ?></strong> <code class="small muted"><?= e($sec['section_key']) ?></code></summary><?php $pageKey = $page; include __DIR__ . '/_section_form.php'; ?></details><?php endforeach; ?>
  <details style="margin-top:.8rem"><summary class="btn btn-ghost btn-sm">Ajouter une section</summary><?php $sec = null; $pageKey = $page; include __DIR__ . '/_section_form.php'; ?></details>
</section>

<?php elseif ($tab === 'listes'): ?>
<nav class="subnav"><?php foreach ($lists as $k => $def): ?><a href="?onglet=listes&amp;liste=<?= $k ?>"<?= $list === $k ? ' aria-current="page"' : '' ?>><?= e($def['label']) ?></a><?php endforeach; ?></nav>
<section class="card"><h2 style="font-size:1.15rem"><?= e($lists[$list]['label']) ?></h2><?php $listKey = $list; $def = $lists[$list]; include __DIR__ . '/_items.php'; ?></section>

<?php elseif ($tab === 'partenaires'): ?>
<?php $tiers = ContentService::PARTNER_TIERS; $form = function (?array $p) use ($tiers, $competitions) { ob_start(); ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/site')) ?>" style="margin:.5rem 0"><?= csrf_field() ?><input type="hidden" name="op" value="partner_save"><?php if ($p): ?><input type="hidden" name="partner_id" value="<?= (int) $p['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="field"><label class="required">Nom</label><input name="name" required value="<?= e($p['name'] ?? '') ?>"></div>
      <div class="field"><label>Catégorie</label><select name="tier"><?php foreach ($tiers as $k => $l): ?><option value="<?= $k ?>"<?= selected($p['tier'] ?? 'partner', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Lien (site ou Facebook)</label><input name="url" value="<?= e($p['url'] ?? '') ?>" placeholder="https://"></div>
      <div class="field"><label>Compétition</label><select name="partner_competition_id"><option value="">Toutes (site)</option><?php foreach ($competitions as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($p['competition_id'] ?? '', $c['id']) ?>><?= e($c['name'] . ' ' . $c['edition_label']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Logo</label><input name="logo" type="file" accept="image/png,image/jpeg,image/webp"><?php if (!empty($p['logo_document_id'])): ?><img src="<?= e(url('/media/' . $p['logo_document_id'])) ?>" alt="" style="max-width:100px"><label class="check"><input type="checkbox" name="remove_logo" value="1"> Retirer</label><?php endif; ?></div>
      <div class="field"><label>Encart publicitaire</label><input name="ad" type="file" accept="image/png,image/jpeg,image/webp"><?php if (!empty($p['ad_document_id'])): ?><img src="<?= e(url('/media/' . $p['ad_document_id'])) ?>" alt="" style="max-width:140px"><label class="check"><input type="checkbox" name="remove_ad" value="1"> Retirer</label><?php endif; ?></div>
      <div class="field"><label>Ordre</label><input name="sort_order" type="number" value="<?= (int) ($p['sort_order'] ?? 0) ?>"></div>
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($p['is_active'] ?? 1) ?>> Affiché</label>
    </div>
    <div class="field" style="margin-top:.5rem"><label>Description (adresse, téléphone, rôle…)</label><textarea name="description" rows="2"><?= e($p['description'] ?? '') ?></textarea></div>
    <button class="btn btn-red btn-sm" type="submit" style="margin-top:.5rem"><?= $p ? 'Enregistrer' : 'Ajouter le partenaire' ?></button></form>
<?php return ob_get_clean(); }; ?>
<section class="card">
  <?php $last = null; foreach ($partners as $p): if ($p['tier'] !== $last): $last = $p['tier']; ?><h3 style="margin-top:1rem"><?= e($tiers[$p['tier']] ?? $p['tier']) ?></h3><?php endif; ?>
    <details style="border-bottom:1px solid var(--line);padding:.4rem 0"><summary style="cursor:pointer;display:flex;gap:.6rem;align-items:center"><?php if ($p['logo_document_id'] || $p['ad_document_id']): ?><img src="<?= e(url('/media/' . ($p['logo_document_id'] ?: $p['ad_document_id']))) ?>" alt="" style="width:48px;height:32px;object-fit:contain"><?php endif; ?><strong><?= e($p['name']) ?></strong><?= $p['is_active'] ? '' : ' <span class="badge">masqué</span>' ?><?= $p['competition_name'] ? ' <span class="badge">' . e($p['competition_name']) . '</span>' : '' ?></summary>
      <?= $form($p) ?>
      <form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer le partenaire « <?= e($p['name']) ?> » ?" data-danger><?= csrf_field() ?><input type="hidden" name="op" value="partner_delete"><input type="hidden" name="partner_id" value="<?= (int) $p['id'] ?>"><button class="btn-link small" type="submit">Supprimer</button></form>
    </details>
  <?php endforeach; ?>
  <details style="margin-top:1rem"><summary class="btn btn-ghost btn-sm">Ajouter un partenaire</summary><?= $form(null) ?></details>
</section>

<?php elseif ($tab === 'historique'): ?>
<?php $hform = function (?array $h) { ob_start(); ?>
  <form method="post" action="<?= e(url('/admin/site')) ?>" style="margin:.5rem 0"><?= csrf_field() ?><input type="hidden" name="op" value="history_save"><?php if ($h): ?><input type="hidden" name="history_id" value="<?= (int) $h['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="field"><label class="required">Saison</label><input name="season_label" required placeholder="2026-2027" value="<?= e($h['season_label'] ?? '') ?>"></div>
      <div class="field"><label>Nombre de clubs</label><input name="clubs_count" type="number" min="0" value="<?= e($h['clubs_count'] ?? '') ?>"></div>
      <div class="field"><label>Total participants</label><input name="total_participants" type="number" min="0" value="<?= e($h['total_participants'] ?? '') ?>"><span class="hint">Vide = somme des catégories.</span></div>
      <div class="field"><label>Combats arbitrés</label><input name="bouts_count" type="number" min="0" value="<?= e($h['bouts_count'] ?? '') ?>"><span class="hint">Vide = « ND ».</span></div>
      <div class="field"><label>Ordre</label><input name="sort_order" type="number" value="<?= (int) ($h['sort_order'] ?? 0) ?>"></div>
    </div>
    <div class="field" style="margin-top:.5rem"><label>Participants par catégorie (une ligne « Catégorie : nombre », « - » si absente)</label><textarea name="category_counts" rows="6"><?= e(implode("\n", array_map(fn ($c) => $c['label'] . ' : ' . ($c['count'] === null ? '-' : $c['count']), $h['categories'] ?? []))) ?></textarea></div>
    <button class="btn btn-red btn-sm" type="submit" style="margin-top:.5rem"><?= $h ? 'Enregistrer' : 'Ajouter la saison' ?></button></form>
<?php return ob_get_clean(); }; ?>
<section class="card" style="margin-bottom:1rem"><h2 style="font-size:1.1rem">Aperçu</h2><?php include BASE_PATH . '/app/Views/public/_history.php'; ?></section>
<section class="card">
  <?php foreach ($history as $h): ?><details style="border-bottom:1px solid var(--line);padding:.4rem 0"><summary style="cursor:pointer"><strong><?= e($h['season_label']) ?></strong> — <?= (int) $h['total_participants'] ?> participants</summary><?= $hform($h) ?>
    <form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer la saison <?= e($h['season_label']) ?> ?" data-danger><?= csrf_field() ?><input type="hidden" name="op" value="history_delete"><input type="hidden" name="history_id" value="<?= (int) $h['id'] ?>"><button class="btn-link small" type="submit">Supprimer</button></form></details><?php endforeach; ?>
  <details style="margin-top:1rem"><summary class="btn btn-ghost btn-sm">Ajouter une saison</summary><?= $hform(null) ?></details>
  <?php if ($competitions): ?><form method="post" action="<?= e(url('/admin/site')) ?>" class="btn-group" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="op" value="history_from_competition">
    <label for="src">Calculer depuis une compétition gérée ici</label><select id="src" name="source_competition_id" style="width:auto"><?php foreach ($competitions as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name'] . ' ' . $c['edition_label']) ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm" type="submit">Ajouter à l'historique</button></form><?php endif; ?>
</section>

<?php elseif ($tab === 'photos'): ?>
<form method="get" class="toolbar"><input type="hidden" name="onglet" value="photos">
  <label class="sr-only" for="album">Album</label>
  <select id="album" name="album" data-autosubmit><?php foreach ($competitions as $co): ?><option value="<?= (int) $co['id'] ?>"<?= $album && (int) $album['id'] === (int) $co['id'] ? ' selected' : '' ?>><?= e($co['name'] . ' ' . $co['edition_label']) ?></option><?php endforeach; ?></select>
  <?php if ($album && $hasPassword && $album['status'] !== 'draft'): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/photos/' . $album['slug'])) ?>" target="_blank">Voir l'album public <?= icon('arrow') ?></a><?php endif; ?>
</form>
<?php if ($album): ?>
<div class="grid grid-2" style="margin-bottom:1rem">
  <section class="card">
    <h2 class="card-title" style="margin-bottom:.4rem"><?= icon('camera') ?> Ajouter des photos</h2>
    <p class="small muted">Sélectionnez plusieurs photos à la fois (JPEG, PNG, WebP). Elles sont redimensionnées et une vignette est créée.</p>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/site')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="op" value="gallery_upload"><input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
      <input type="file" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp">
      <div class="field"><label for="caption">Légende commune (facultative)</label><input id="caption" name="caption" maxlength="190" placeholder="ex. Podium des Poussines"></div>
      <button class="btn" type="submit"><?= icon('upload') ?> Envoyer les photos</button></form>
  </section>
  <section class="card">
    <h2 class="card-title" style="margin-bottom:.4rem"><?= icon('lock') ?> Accès par mot de passe</h2>
    <p class="small muted"><?= $hasPassword ? 'L\'album est publié : visible uniquement avec le mot de passe. Le changer révoque les accès déjà ouverts.' : 'Album non publié : définissez un mot de passe à transmettre aux familles et aux clubs.' ?></p>
    <form method="post" action="<?= e(url('/admin/site')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="op" value="gallery_password"><input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
      <div class="field"><label for="gpw"><?= $hasPassword ? 'Nouveau mot de passe' : 'Mot de passe de l\'album' ?></label><input id="gpw" name="password" type="text" minlength="6" required autocomplete="off" placeholder="6 caractères minimum"></div>
      <div class="btn-group"><button class="btn" type="submit"><?= $hasPassword ? 'Changer le mot de passe' : 'Publier l\'album' ?></button></div></form>
    <?php if ($hasPassword): ?><form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Fermer l'album au public ? Les photos restent enregistrées." style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="op" value="gallery_close"><input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>"><button class="btn-link small" type="submit">Fermer l'album</button></form><?php endif; ?>
  </section>
</div>
<?php if (!$photos): ?><div class="empty"><strong>Aucune photo</strong>Ajoutez les photos prises pendant la compétition.</div><?php else: ?>
<p class="small muted"><?= count($photos) ?> photo(s)</p>
<div class="admin-gallery">
  <?php foreach ($photos as $ph): ?>
    <figure class="card" style="padding:.5rem">
      <img src="<?= e(url('/media/' . $ph['id'], ['mini' => 1])) ?>" alt="" loading="lazy">
      <form method="post" action="<?= e(url('/admin/site')) ?>" class="btn-group" style="margin-top:.4rem"><?= csrf_field() ?><input type="hidden" name="op" value="gallery_caption"><input type="hidden" name="document_id" value="<?= (int) $ph['id'] ?>">
        <input name="title" value="<?= e($ph['title']) ?>" aria-label="Légende" style="min-height:32px;flex:1;font-size:.82rem"><button class="icon-btn" type="submit" aria-label="Enregistrer la légende"><?= icon('check') ?></button></form>
      <form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer cette photo ?" data-danger><?= csrf_field() ?><input type="hidden" name="op" value="gallery_delete"><input type="hidden" name="document_id" value="<?= (int) $ph['id'] ?>"><button class="btn-link small" type="submit">Supprimer</button></form>
    </figure>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php else: ?>
<section class="card" style="margin-bottom:1rem"><h2 style="font-size:1.1rem">Ajouter un document ou une image</h2>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/site')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="op" value="document_upload">
    <div class="field"><label class="required">Fichier</label><input name="file" type="file" required accept="application/pdf,image/png,image/jpeg,image/webp,image/gif"></div>
    <div class="field"><label>Titre</label><input name="title"></div>
    <div class="field"><label>Type</label><select name="type"><?php foreach (DocumentService::TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <label class="check"><input type="checkbox" name="private" value="1"> Réservé aux utilisateurs connectés</label>
    <div><button class="btn btn-red" type="submit">Ajouter</button></div>
  </form></section>
<div class="table-wrap"><table class="table-compact"><thead><tr><th></th><th>Titre</th><th>Type</th><th>Compétition</th><th>Taille</th><th>Lien</th><th></th></tr></thead><tbody>
<?php foreach ($documents as $d): ?><tr><td style="width:60px"><?php if (str_starts_with($d['mime_type'], 'image/')): ?><img src="<?= e(url('/media/' . $d['id'])) ?>" alt="" style="width:52px;height:36px;object-fit:contain" loading="lazy"><?php else: ?><span class="badge">PDF</span><?php endif; ?></td>
  <td><?= e($d['title']) ?><?= $d['is_public'] ? '' : ' <span class="badge">privé</span>' ?></td><td class="small"><?= e(DocumentService::TYPES[$d['type']] ?? $d['type']) ?></td><td class="small"><?= e($d['competition_name']) ?></td><td class="num small"><?= number_format($d['size_bytes'] / 1024, 0, ',', ' ') ?> Ko</td>
  <td><button class="btn-link small" type="button" data-copy="<?= e(absolute_url('/media/' . $d['id'])) ?>">Copier le lien</button></td>
  <td><form method="post" action="<?= e(url('/admin/site')) ?>" data-confirm="Supprimer « <?= e($d['title']) ?> » ?" data-danger><?= csrf_field() ?><input type="hidden" name="op" value="document_delete"><input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>"><button class="btn-link small" type="submit">Supprimer</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
