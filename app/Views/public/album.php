<section class="section"><div class="wrap">
  <p class="eyebrow"><a href="<?= e(url('/photos')) ?>">Photos</a></p>
  <div class="section-title"><div><h1><?= e($c['name']) ?></h1><p class="muted"><?= e(fr_date($c['event_date'], true)) ?><?= $access ? ' · ' . count($photos) . ' photos' : '' ?></p></div>
    <?php if ($access): ?><form method="post" action="<?= e(url('/photos/' . $c['slug'])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="lock"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('lock') ?> Verrouiller sur cet appareil</button></form><?php endif; ?></div>
  <?php if (!$open): ?>
    <div class="empty"><strong>Album pas encore disponible</strong>Les photos seront bientôt publiées.</div>
  <?php elseif (!$access): ?>
    <div class="s-card unlock">
      <span class="lock"><?= icon('lock') ?></span>
      <h2>Album protégé</h2>
      <p class="muted"><?= (int) $count ?> photos. Saisissez le mot de passe communiqué par le club.</p>
      <form method="post" action="<?= e(url('/photos/' . $c['slug'])) ?>" class="stack" style="text-align:left"><?= csrf_field() ?>
        <div class="field"><label for="pw">Mot de passe de l'album</label><input id="pw" name="password" type="password" required autocomplete="off" autofocus></div>
        <button class="btn btn-lg btn-block" type="submit">Voir les photos</button></form>
    </div>
  <?php else: ?>
    <div class="gallery" data-gallery>
      <?php foreach ($photos as $i => $p): ?><a href="<?= e(url('/media/' . $p['id'])) ?>" data-index="<?= $i ?>" data-caption="<?= e($p['title']) ?>"><img src="<?= e(url('/media/' . $p['id'], ['mini' => 1])) ?>" alt="<?= e($p['title']) ?>" loading="lazy"<?= $p['width'] ? ' width="' . (int) $p['width'] . '" height="' . (int) $p['height'] . '"' : '' ?>></a><?php endforeach; ?>
    </div>
    <p class="small muted" style="margin-top:1rem">Photos réservées à un usage privé : merci de ne pas les republier sans l'accord des familles.</p>
  <?php endif; ?>
</div></section>
