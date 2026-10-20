<?php
use App\Services\CategoryService;
use App\Services\RegistrationService;
$b = '/espace-club/competitions/' . $c['id'];
$active = array_values(array_filter($rows, fn ($r) => in_array($r['status'], ['pending', 'confirmed'], true)));
$others = array_values(array_filter($rows, fn ($r) => !in_array($r['status'], ['pending', 'confirmed'], true)));
$lv = $listValidation;
$count = fn (string $att) => count(array_filter($active, fn ($r) => $r['attendance_status'] === $att));
$attLabel = ['expected' => ['', 'Attendu'], 'present' => ['badge-green', 'Présent'], 'absent' => ['badge-red', 'Absent'], 'to_check' => ['badge-orange', 'À vérifier']];
$openAdd = !empty($GLOBALS['__errors']) || !empty($GLOBALS['__old']);
?>
<div class="page-head">
  <div><p class="eyebrow"><?= e($c['name']) ?> · <?= e(fr_date($c['event_date'], true)) ?></p><h1>Mes combattants</h1>
    <p class="muted" style="margin:.2rem 0 0"><?= count($active) ?> inscrit(s)<?= $attendanceOpen ? ' · ' . $count('present') . ' présent(s) · ' . $count('expected') . ' attendu(s)' : '' ?></p></div>
  <div class="btn-group">
    <a class="btn btn-ghost" href="<?= e(url($b . '/export.csv')) ?>"><?= icon('download') ?>Liste (CSV)</a>
    <?php if ($canEdit): ?><button class="btn btn-lg" type="button" data-dialog-open="add-fighter"><?= icon('plus') ?>Ajouter un combattant</button>
    <?php else: ?><button class="btn btn-lg" type="button" disabled title="Inscriptions fermées"><?= icon('plus') ?>Ajouter un combattant</button><?php endif; ?>
  </div>
</div>

<?php if (!$canEdit): ?>
  <div class="alert alert-warning small"><?= $c['status'] === 'registrations_open' && $c['registration_opens_at'] && strtotime($c['registration_opens_at']) > time() ? 'Les inscriptions en ligne ouvriront le ' . e(fr_datetime($c['registration_opens_at'])) . '.' : 'Les inscriptions en ligne sont fermées : pour ajouter un combattant, adressez-vous à l\'accueil de la compétition.' ?><?= $attendanceOpen ? ' Vous pouvez pointer la présence et le poids de vos judokas ci-dessous.' : '' ?></div>
<?php else: ?>
  <div class="alert alert-info small">Inscriptions ouvertes jusqu'au <strong><?= e(fr_datetime($c['registration_closes_at'])) ?></strong> : vous pouvez ajouter, modifier ou retirer vos combattants jusqu'à cette date.</div>
<?php endif; ?>

<?php if ($canEdit || $lv): ?>
<section class="card list-validate<?= $lv && !$lv['changed_since'] ? ' done' : '' ?>">
  <div>
    <?php if ($lv && !$lv['changed_since']): ?>
      <p class="eyebrow"><?= icon('check') ?> Liste validée</p><h2>Votre liste est confirmée</h2>
      <p class="muted small">Validée le <?= e(fr_datetime($lv['validated_at'])) ?><?= $lv['display_name'] ? ' par ' . e($lv['display_name']) : '' ?> · <?= (int) $lv['registrations_count'] ?> judoka(s) confirmé(s) automatiquement auprès de l'organisation.</p>
    <?php elseif ($lv): ?>
      <p class="eyebrow">Liste modifiée</p><h2>Revalidez votre liste</h2>
      <p class="muted small"><?= (int) $lv['changed_since'] ?> modification(s) depuis la validation du <?= e(fr_datetime($lv['validated_at'])) ?>.</p>
    <?php else: ?>
      <p class="eyebrow">Dernière étape</p><h2>Validez votre liste</h2>
      <p class="muted small">Une fois votre liste complète, validez-la : vos <?= count($active) ?> judoka(s) sont confirmés automatiquement auprès de l'organisation.</p>
    <?php endif; ?>
  </div>
  <?php if ($canEdit && $active && (!$lv || $lv['changed_since'])): ?>
    <form method="post" action="<?= e(url($b . '/valider-liste')) ?>" data-confirm="Valider la liste de <?= count($active) ?> judoka(s) ? Elle sera confirmée automatiquement auprès de l'organisation." data-confirm-label="Valider ma liste"><?= csrf_field() ?><button class="btn btn-lg" type="submit"><?= icon('check') ?>Valider ma liste</button></form>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($attendanceOpen): ?>
<div class="stats att-stats" style="margin-top:0">
  <div class="stat accent"><strong><?= $count('present') ?></strong><span>présents</span></div>
  <div class="stat"><strong><?= $count('expected') ?></strong><span>attendus</span></div>
  <div class="stat"><strong style="color:var(--red)"><?= $count('absent') ?></strong><span>absents</span></div>
  <div class="stat"><strong style="color:var(--orange)"><?= $count('to_check') ?></strong><span>à vérifier</span></div>
</div>
<?php endif; ?>

<?php if (!$active): ?>
  <div class="empty"><strong>Aucun combattant inscrit</strong><?= $canEdit ? 'Cliquez sur « Ajouter un combattant » pour inscrire vos judokas.' : 'Les inscriptions sont fermées.' ?></div>
<?php else: ?>
<div class="profiles club-fighters">
  <?php foreach ($active as $r): [$cls, $lab] = $attLabel[$r['attendance_status']]; $inPool = RegistrationService::inPool((int) $r['id']); ?>
    <article class="profile att-card is-<?= e($r['attendance_status']) ?>" id="j<?= (int) $r['id'] ?>">
      <div class="top"><?= avatar($r['photo_document_id'] ? (int) $r['photo_document_id'] : null, $r['first_name'] . ' ' . $r['last_name']) ?>
        <div class="name"><?= e($r['first_name']) ?> <?= e(mb_strtoupper($r['last_name'])) ?><small><?= e(date('Y', strtotime($r['birth_date']))) ?><?= $r['grade'] ? ' · ' . e($r['grade']) : '' ?></small></div></div>
      <div class="tiles">
        <div class="tile"><span class="label">Catégorie</span><strong><?= e(CategoryService::label(['name' => $r['age_name'], 'name_m' => $r['name_m'], 'name_f' => $r['name_f']], $r['sex'])) ?></strong></div>
        <div class="tile"><span class="label">Poids</span><strong><?= $r['measured_weight_kg'] !== null ? 'pesé ' . e(kg($r['measured_weight_kg'])) : ($r['weight_label'] ? e($r['weight_label']) . ' kg' : ($r['declared_weight_kg'] ? e(kg($r['declared_weight_kg'])) : '—')) ?></strong></div>
      </div>
      <div class="foot">
        <span class="badge <?= $r['status'] === 'confirmed' ? 'badge-green' : 'badge-orange' ?>"><?= e(RegistrationService::STATUS[$r['status']]) ?></span>
        <?php if ($attendanceOpen): ?><span class="badge <?= $cls ?>"><span class="dot"></span><?= e($lab) ?></span><?php endif; ?>
      </div>
      <?php if ($attendanceOpen): ?>
        <form method="post" action="<?= e(url($b . '/inscriptions/' . $r['id'] . '/presence')) ?>" class="coach-mark"><?= csrf_field() ?>
          <?php if ($weighIn && !$inPool): ?>
            <label class="kg"><span class="sr-only">Poids mesuré de <?= e($r['first_name']) ?> (kg)</span><input name="measured_weight_kg" type="text" inputmode="decimal" placeholder="Poids (kg)" value="<?= $r['measured_weight_kg'] !== null ? e(str_replace('.', ',', (string) (float) $r['measured_weight_kg'])) : '' ?>"></label>
          <?php endif; ?>
          <div class="att-actions" role="group" aria-label="Présence de <?= e($r['first_name']) ?>">
            <button type="submit" name="status" value="present" class="present" aria-pressed="<?= $r['attendance_status'] === 'present' ? 'true' : 'false' ?>"><?= icon('check') ?><span>Présent</span></button>
            <button type="submit" name="status" value="absent" class="absent" aria-pressed="<?= $r['attendance_status'] === 'absent' ? 'true' : 'false' ?>"><?= icon('x') ?><span>Absent</span></button>
          </div>
        </form>
      <?php endif; ?>
      <?php if ($canEdit): ?>
        <div class="card-links">
          <a class="btn btn-ghost btn-sm" href="<?= e(url($b . '/inscriptions/' . $r['id'])) ?>">Modifier</a>
          <form method="post" action="<?= e(url($b . '/inscriptions/' . $r['id'] . '/retirer')) ?>" data-confirm="Retirer <?= e($r['first_name'] . ' ' . $r['last_name']) ?> de la compétition ?" data-danger><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Retirer</button></form>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($others): ?>
  <details class="card" style="margin-top:1.2rem"><summary><strong><?= count($others) ?> inscription(s) retirée(s) ou refusée(s)</strong></summary>
    <ul class="small" style="margin:.6rem 0 0"><?php foreach ($others as $r): ?><li><?= e(mb_strtoupper($r['last_name']) . ' ' . $r['first_name']) ?> — <?= e(RegistrationService::STATUS[$r['status']]) ?></li><?php endforeach; ?></ul></details>
<?php endif; ?>

<?php if ($canEdit): ?>
<dialog class="add-dialog" id="add-fighter" aria-labelledby="add-fighter-t"<?= $openAdd ? ' data-open-on-load' : '' ?>>
  <div class="cd-head"><h2 id="add-fighter-t">Ajouter un combattant</h2><span style="flex:1"></span><button type="button" class="icon-btn" data-dialog-close aria-label="Fermer"><?= icon('x') ?></button></div>
  <div class="add-body">
    <div class="segmented add-tabs" role="tablist">
      <button type="button" role="tab" aria-pressed="true" data-tab="new">Nouveau judoka</button>
      <?php if ($athletes): ?><button type="button" role="tab" aria-pressed="false" data-tab="known">Déjà venu (<?= count($athletes) ?>)</button><?php endif; ?>
      <button type="button" role="tab" aria-pressed="false" data-tab="csv">Importer un fichier</button>
    </div>
    <section data-panel="new">
      <form method="post" enctype="multipart/form-data" action="<?= e(url($b . '/inscriptions')) ?>"><?= csrf_field() ?>
        <?php include BASE_PATH . '/app/Views/partials/registration_form.php'; ?>
        <div class="btn-group" style="margin-top:1rem;justify-content:flex-end"><button class="btn btn-ghost" type="button" data-dialog-close>Annuler</button><button class="btn" type="submit"><?= icon('plus') ?>Inscrire ce combattant</button></div>
      </form>
    </section>
    <?php if ($athletes): ?>
    <section data-panel="known" hidden>
      <p class="muted small">Vos judokas des compétitions précédentes : choisissez-le, puis sa catégorie de poids.</p>
      <form method="post" action="<?= e(url($b . '/inscriptions')) ?>" class="stack"><?= csrf_field() ?>
        <div class="field"><label for="athlete_id">Judoka</label><select id="athlete_id" name="athlete_id"><?php foreach ($athletes as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e(mb_strtoupper($a['last_name']) . ' ' . $a['first_name'] . ' (' . date('Y', strtotime($a['birth_date'])) . ')') ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="known_weight">Catégorie de poids</label><select id="known_weight" name="weight_category_id"><option value="">Automatique (selon le poids déclaré)</option><?php foreach ($formConfig['weights'] as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['label']) ?> kg (<?= e($w['sex'] === 'F' ? 'filles' : 'garçons') ?>, <?= e(array_column($formConfig['ages'], 'name', 'id')[$w['age']] ?? '') ?>)</option><?php endforeach; ?></select></div>
        <div class="field"><label for="known_kg">Poids déclaré (kg)</label><input id="known_kg" name="declared_weight_kg" type="number" step="0.1" min="10" max="250" inputmode="decimal"></div>
        <div class="btn-group" style="justify-content:flex-end"><button class="btn" type="submit"><?= icon('plus') ?>Inscrire</button></div>
      </form>
    </section>
    <?php endif; ?>
    <section data-panel="csv" hidden>
      <p class="muted small">Colonnes : <code>nom;prenom;sexe;date_naissance;categorie_poids;poids_declare;grade;licence</code> — sexe F/M, date JJ/MM/AAAA, catégorie de poids « -28 ». Les lignes en erreur sont listées et ne sont pas importées.</p>
      <form method="post" enctype="multipart/form-data" action="<?= e(url($b . '/import')) ?>" class="btn-group"><?= csrf_field() ?><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn" type="submit"><?= icon('download') ?>Importer</button></form>
    </section>
  </div>
</dialog>
<?php endif; ?>
