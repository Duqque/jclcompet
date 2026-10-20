<?php
/** Bloc d'accueil d'une compétition. @var array $hc @var int $mats @var int $clubsCount @var int $athletesCount @var bool $regOpen @var bool $live */
use App\Services\CompetitionService;
$titleTag = $titleTag ?? 'h1';
?>
<section class="hero" aria-labelledby="hero-title">
  <div>
    <span class="pill"><img src="<?= e(asset('img/logo-jcl.png')) ?>" alt="" width="27" height="18"><?= e($hc['edition_label'] ?: CompetitionService::statusLabel($hc['status'])) ?><?= $live ? ' · en direct' : '' ?></span>
    <<?= $titleTag ?> id="hero-title"><?= e($hc['name']) ?></<?= $titleTag ?>>
    <p class="lead"><?= e(fr_date($hc['event_date'], true)) ?> · <?= e($hc['venue'] ? $hc['venue'] . ', ' : '') ?><?= e($hc['city']) ?></p>
    <div class="btn-group">
      <?php if ($live): ?><a class="btn btn-lg" href="<?= e(url('/competitions/' . $hc['slug'] . '/direct')) ?>">Suivre en direct</a><?php endif; ?>
      <?php if (!empty($onHome)): ?><a class="btn btn-lg<?= $live ? ' btn-ghost' : '' ?>" href="<?= e(url('/competitions/' . $hc['slug'])) ?>">Voir le programme</a><?php endif; ?>
      <?php if ($regOpen): ?><a class="btn btn-lg btn-ghost" href="<?= e(url('/connexion', ['profil' => 'club'])) ?>">Inscrire mon club</a><?php endif; ?>
      <?php if (in_array($hc['status'], ['finished', 'archived', 'in_progress'], true)): ?><a class="btn btn-lg btn-ghost" href="<?= e(url('/competitions/' . $hc['slug'] . '/resultats')) ?>">Résultats</a><?php endif; ?>
      <?php if (empty($onHome) && $hc['regulation_document_id']): ?><a class="btn btn-lg btn-ghost" href="<?= e(url('/media/' . $hc['regulation_document_id'], ['telecharger' => 1])) ?>"><?= icon('download') ?>Programme PDF</a><?php endif; ?>
    </div>
  </div>
  <dl class="hero-facts">
    <div class="big"><dt>Clubs</dt><dd><?= (int) $clubsCount ?: '—' ?></dd></div>
    <div class="big"><dt><?= $athletesCount ? 'Judokas inscrits' : 'Judokas attendus' ?></dt><dd><?= $athletesCount ?: ($hc['expected_participants'] ?: '—') ?></dd></div>
    <div><dt>Accueil</dt><dd><?= e(fr_time($hc['start_time'])) ?></dd></div>
    <div><dt>Tapis</dt><dd><?= (int) $mats ?></dd></div>
  </dl>
</section>
