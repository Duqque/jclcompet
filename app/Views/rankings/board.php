<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Classement des clubs · <?= e($c['name']) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/board.css')) ?>">
</head>
<body class="board" data-refresh="<?= (int) $refresh ?>" data-url="<?= e(url('/api/public/competitions/' . $c['slug'] . '/classement')) ?>">
<header class="board-head">
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="96" height="64">
  <div>
    <p class="board-kicker">Classement général des clubs</p>
    <h1><?= e($c['name']) ?><?= $c['edition_label'] ? ' · ' . e($c['edition_label']) : '' ?></h1>
  </div>
  <div class="board-meta">
    <span data-updated>—</span>
    <button class="btn btn-yellow btn-sm" type="button" data-refresh-now>Actualiser</button>
  </div>
</header>
<section class="board-totals" data-totals></section>
<main class="board-grid">
  <section aria-labelledby="by-points"><h2 id="by-points">Par points</h2><ol class="board-list" data-list="points"></ol></section>
  <section aria-labelledby="by-medals"><h2 id="by-medals">Par médailles</h2><ol class="board-list" data-list="medals"></ol></section>
</main>
<footer class="board-foot"><span>Seuls les résultats validés sont comptés · actualisation toutes les <?= (int) $refresh ?> s</span><a href="<?= e(url('/competitions/' . $c['slug'] . '/resultats')) ?>">Tous les résultats</a></footer>
<?= json_script('board-data', $board) ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/board.js')) ?>"></script>
</body>
</html>
