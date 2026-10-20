<div class="cm-app" data-cm>
  <aside class="cm-side" aria-label="Combats restants">
    <div class="cm-side-head"><h2>Combats restants <span data-remaining-count>—</span></h2><span class="small" data-done-count></span></div>
    <ol class="cm-queue" data-queue><li class="muted small" style="padding:1rem">Chargement…</li></ol>
  </aside>
  <div class="cm-main">
    <header class="cm-top">
      <a class="btn btn-ghost btn-sm" href="<?= e(url('/commissaire')) ?>" data-back>← Retour</a>
      <div class="cm-title"><strong>Tapis <?= (int) $t['number'] ?></strong><span><?= e($c['name']) ?></span></div>
      <span class="cm-net" data-net title="État de la connexion"><span class="dot"></span><span data-net-label>Connecté</span></span>
      <div class="cm-clock-box">
        <span class="cm-clock" data-clock>0:00</span>
        <button class="cm-play" type="button" data-hajime aria-label="Hajime / Matte (barre d'espace)">▶</button>
      </div>
      <button class="btn btn-red btn-display" type="button" data-finish-top>Fin du combat</button>
    </header>
    <section class="cm-bout-head" data-bout-head><p class="muted">Aucun combat sélectionné.</p></section>
    <section class="cm-fighters" data-fighters hidden>
      <?php foreach (['a' => 'Premier appelé', 'b' => 'Second appelé'] as $side => $label): ?>
      <article class="cm-fighter cm-fighter-<?= $side ?>" data-side="<?= $side ?>">
        <p class="cm-tag" data-tag><?= $side === 'a' ? 'AKA' : 'SHIRO' ?></p>
        <h3 class="cm-name" data-name>—</h3>
        <p class="cm-club" data-club></p>
        <div class="cm-board">
          <div class="cm-line"><span><b>Waza-ari</b> /<span data-wz-max>2</span> → ippon</span><span class="cm-boxes" data-wz-boxes></span></div>
          <div class="cm-line"><span><b>Shido</b> /<span data-sh-max>3</span> → hansoku</span><span class="cm-boxes sh" data-sh-boxes></span></div>
          <div class="cm-line" data-yuko-line><span><b>Yuko</b></span><strong data-yuko>0</strong></div>
          <div class="cm-line"><span><b>Ippon</b></span><strong data-ippon>0</strong></div>
        </div>
        <button class="cm-osae" type="button" data-osae><span data-osae-label>Osaekomi</span> <small data-osae-th>5 s · 10 s · 20 s</small>
          <span class="cm-osae-bar"><span data-osae-fill></span></span></button>
        <div class="cm-actions">
          <div class="cm-act"><button type="button" data-score="ippon">Ippon</button><button type="button" class="minus" data-undo="ippon" aria-label="Annuler un ippon">−</button></div>
          <div class="cm-act"><button type="button" data-score="wazaari">Waza-ari <small data-count="wazaari">0</small></button><button type="button" class="minus" data-undo="wazaari" aria-label="Annuler un waza-ari">−</button></div>
          <div class="cm-act" data-yuko-act><button type="button" data-score="yuko">Yuko <small data-count="yuko">0</small></button><button type="button" class="minus" data-undo="yuko" aria-label="Annuler un yuko">−</button></div>
        </div>
        <p class="cm-sanctions-label">Sanctions</p>
        <div class="cm-actions">
          <div class="cm-act sanction"><button type="button" data-score="shido">Shido <small data-count="shido">0</small></button><button type="button" class="minus" data-undo="shido" aria-label="Annuler un shido">−</button></div>
          <div class="cm-act sanction"><button type="button" data-score="hansoku">Hansoku-make</button><button type="button" class="minus" data-undo="hansoku" aria-label="Annuler le hansoku-make">−</button></div>
        </div>
      </article>
      <?php endforeach; ?>
    </section>
    <footer class="cm-foot" data-foot></footer>
  </div>
</div>
<?= json_script('cm-config', $config) ?>
