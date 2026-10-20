<?php use App\Controllers\Admin\PdfController; $heading = 'Documents PDF'; include __DIR__ . '/_head.php'; $base = url('/admin/competitions/' . $c['id'] . '/pdf/'); ?>
<?php if (!$dompdf): ?><div class="alert alert-warning">Dompdf n'est pas installé (<code>composer install</code>) : les documents s'ouvrent en version HTML imprimable.</div><?php endif; ?>
<p class="muted">Documents générés à partir des données enregistrées en base.</p>
<div class="grid grid-2">
  <section class="card"><h2 style="font-size:1.15rem">Listes et classements</h2>
    <form method="get" target="_blank" class="stack" action="<?= e($base . 'participants') ?>" id="lists-form">
      <div class="field"><label>Catégorie d'âge</label><select name="age_category_id"><option value="">Toutes</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <label class="check"><input type="checkbox" name="present" value="1"> Présents uniquement</label>
      <div class="btn-group">
        <button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'participants') ?>">Participants</button>
        <button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'categories') ?>">Listes par catégorie</button>
        <button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'classement') ?>">Classement final</button>
        <button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'clubs') ?>">Classement des clubs</button>
      </div>
    </form>
  </section>
  <section class="card"><h2 style="font-size:1.15rem">Poules, planning et feuilles</h2>
    <?php if (!$g): ?><p class="muted">Générez les poules pour accéder à ces documents.</p><?php else: ?>
    <form method="get" target="_blank" action="<?= e($base . 'poules') ?>"><input type="hidden" name="opts" value="1">
      <div class="form-grid">
        <div class="field"><label>Format</label><select name="orientation"><option value="portrait">A4 portrait</option><option value="landscape">A4 paysage</option></select></div>
        <div class="field"><label>Poules par page</label><select name="per_page"><option value="1">1</option><option value="2" selected>2</option><option value="4">4</option><option value="6">6</option></select></div>
        <div class="field"><label>Tapis</label><select name="tatami_id"><option value="">Tous</option><?php foreach ($tatamis as $t): ?><option value="<?= (int) $t['id'] ?>">Tapis <?= (int) $t['number'] ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Catégorie</label><select name="age_category_id"><option value="">Toutes</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="btn-group" style="margin:.7rem 0">
        <?php foreach (['show_competition' => 'Nom de la compétition', 'show_tatami' => 'Numéro du tapis', 'show_category' => 'Sexe et catégorie d\'âge', 'show_weight' => 'Catégorie de poids', 'show_names' => 'Noms et clubs', 'show_blank' => 'Cases de saisie manuelle'] as $k => $l): ?><label class="check"><input type="checkbox" name="<?= $k ?>" value="1" checked> <?= e($l) ?></label><?php endforeach; ?>
      </div>
      <div class="btn-group"><button class="btn btn-red" type="submit">Poules</button><button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'feuilles') ?>">Feuilles de résultats</button><button class="btn btn-ghost" type="submit" formaction="<?= e($base . 'planning') ?>">Planning des tapis</button></div>
    </form><?php endif; ?>
  </section>
</div>
