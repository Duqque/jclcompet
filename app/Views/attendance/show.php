<header class="att-head">
  <div class="row"><a class="btn btn-sm btn-outline-white" href="<?= e(url(\App\Core\Auth::hasRole('admin') ? '/admin/competitions/' . $c['id'] : '/accueil')) ?>" aria-label="Retour">←</a><h1><?= e($c['name']) ?></h1><span class="small" data-sync style="color:#3ccf7a">●</span></div>
  <label class="sr-only" for="q">Rechercher un judoka</label>
  <input id="q" type="search" placeholder="Rechercher un nom ou un prénom…" autocomplete="off" data-q>
</header>
<div class="att-filters" role="group" aria-label="Filtres">
  <select data-f="club_id" aria-label="Club"><option value="">Tous les clubs</option><?php foreach ($clubs as $cl): ?><option value="<?= (int) $cl['id'] ?>"><?= e($cl['name']) ?></option><?php endforeach; ?></select>
  <select data-f="age_category_id" aria-label="Catégorie"><option value="">Toutes catégories</option><?php foreach ($ages as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select data-f="sex" aria-label="Sexe"><option value="">Filles et garçons</option><option value="F">Filles</option><option value="M">Garçons</option></select>
  <select data-f="attendance" aria-label="Présence"><option value="">Tous</option><option value="expected">Attendus</option><option value="present">Présents</option><option value="absent">Absents / forfaits</option><option value="to_check">À vérifier</option></select>
</div>
<div class="att-counts" data-counts></div>
<?php include BASE_PATH . '/app/Views/partials/flash.php'; ?>
<ul class="att-list" data-list><li class="empty"><span class="spinner"></span> Chargement…</li></ul>
<?php if ($lateAllowed || \App\Core\Auth::can('registrations.manage')): ?><a class="btn btn-red btn-lg btn-display att-fab" href="<?= e(url('/accueil/' . $c['id'] . '/sur-place')) ?>">+ Inscription sur place</a><?php endif; ?>
<?= json_script('att-config', $config) ?>
