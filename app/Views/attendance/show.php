<?php $back = url(\App\Core\Auth::hasRole('admin') ? '/admin/competitions/' . $c['id'] . '/inscriptions' : '/accueil'); ?>
<header class="att-bar">
  <a class="icon-btn" href="<?= e($back) ?>" aria-label="Retour"><?= icon('arrow-left') ?></a>
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="42" height="28">
  <span class="att-comp"><?= e($c['name']) ?></span>
  <span class="att-sync" data-sync title="Synchronisé"><span class="dot"></span><span class="lbl">En ligne</span></span>
</header>

<main class="att-page" id="contenu">
  <div class="att-title">
    <div><p class="eyebrow">Accueil · jour J</p><h1>Présences</h1><p class="muted" data-sub>—</p></div>
  </div>

  <div class="stats att-stats" role="group" aria-label="Filtrer par présence" data-counts>
    <button type="button" class="stat accent" data-att="present"><strong data-n="present">—</strong><span>présents</span></button>
    <button type="button" class="stat" data-att="expected"><strong data-n="expected">—</strong><span>attendus</span></button>
    <button type="button" class="stat" data-att="absent"><strong data-n="absent">—</strong><span>absents</span></button>
    <button type="button" class="stat" data-att="to_check"><strong data-n="to_check">—</strong><span>à vérifier</span></button>
  </div>

  <nav class="chips" aria-label="Filtrer par club" data-clubs>
    <button type="button" class="chip plain" data-club="" aria-current="true">Tous les clubs <span class="n" data-n="total">—</span></button>
    <?php foreach ($clubs as $cl): ?>
      <button type="button" class="chip" data-club="<?= (int) $cl['id'] ?>" title="<?= e($cl['name']) ?>"><?php $logo = $cl['logo_path']; $name = $cl['name']; $size = 24; include BASE_PATH . '/app/Views/partials/club_logo.php'; ?><?= e($cl['short_name'] ?: $cl['name']) ?> <span class="n"><?= (int) $cl['n'] ?></span></button>
    <?php endforeach; ?>
  </nav>

  <div class="toolbar">
    <label class="search"><?= icon('search') ?><span class="sr-only">Rechercher un judoka</span><input type="search" placeholder="Rechercher un judoka…" autocomplete="off" data-q></label>
    <select data-f="age_category_id" aria-label="Catégorie"><option value="">Catégories</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
    <select data-f="sex" aria-label="Sexe"><option value="">Tous</option><option value="F">Filles</option><option value="M">Garçons</option></select>
  </div>

  <?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <div class="profiles att-grid" data-list><p class="empty"><span class="spinner"></span> Chargement…</p></div>
</main>

<?php if ($lateAllowed || \App\Core\Auth::can('registrations.manage')): ?><a class="btn btn-lg att-fab" href="<?= e(url('/accueil/' . $c['id'] . '/sur-place')) ?>"><?= icon('plus') ?>Inscription sur place</a><?php endif; ?>
<?= json_script('att-config', $config) ?>
