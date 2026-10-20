<div class="card" style="max-width:520px;text-align:center">
  <img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="120" height="80" style="width:120px;margin:0 auto 1rem;display:block">
  <p class="eyebrow">Erreur <?= (int) $status ?></p>
  <h1 style="font-size:1.6rem"><?= $status === 404 ? 'Page introuvable' : ($status === 403 ? 'Accès refusé' : ($status === 423 ? 'Lecture seule' : 'Une erreur est survenue')) ?></h1>
  <p><?= e($message) ?></p>
  <div class="btn-group" style="justify-content:center"><a class="btn btn-red" href="<?= e(url('/')) ?>">Retour à l'accueil</a><button class="btn btn-ghost" type="button" data-history-back>Page précédente</button></div>
</div>
