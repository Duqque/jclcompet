<section class="section"><div class="wrap">
  <div class="section-title"><div><h1>Photos</h1><p class="muted">Les photos sont réservées aux familles et aux clubs : chaque album s'ouvre avec le mot de passe communiqué par le JC Leforest.</p></div></div>
  <?php if (!$albums): ?><div class="empty"><strong>Aucun album pour le moment</strong>Les photos des compétitions seront publiées ici.</div><?php else: ?>
  <div class="albums"><?php foreach ($albums as $a): include __DIR__ . '/_album_card.php'; endforeach; ?></div><?php endif; ?>
</div></section>
