<?php
/**
 * Régie vidéo d'un tapis (plein écran, ordinateur au bord du tapis, Chrome ou Edge).
 * @var array $t @var array $config
 */
?>
<link rel="stylesheet" href="<?= e(asset('css/video.css')) ?>">
<div class="vr" data-regie>
  <header class="vr-top">
    <a class="vr-back" href="<?= e(url('/video')) ?>" aria-label="Retour aux régies"><?= icon('arrow-left') ?></a>
    <div class="vr-title"><strong>Tapis <?= (int) $t['number'] ?> · Régie vidéo</strong><span data-bout-label><?= e($t['competition_name']) ?></span></div>
    <span class="vr-pill" data-onair-pill><span class="dot"></span><span data-onair-text>Hors antenne</span></span>
    <span class="vr-net" data-net><span class="dot"></span><span data-net-label>Connexion…</span></span>
    <button class="vr-btn vr-btn-ghost" type="button" data-open-output><?= icon('tv') ?>Sortie programme (OBS)</button>
  </header>

  <main class="vr-main">
    <!-- Moniteur : direct de la caméra choisie, ou retour vidéo pendant la vidéo-arbitrage -->
    <section class="vr-monitor-wrap">
      <div class="vr-monitor" data-monitor-box>
        <canvas class="vr-monitor-canvas" data-monitor width="1280" height="720" aria-label="Moniteur vidéo"></canvas>
        <div class="vr-monitor-tag" data-monitor-tag>DIRECT · Caméra 1</div>
        <div class="vr-monitor-time" data-monitor-time hidden></div>
        <div class="vr-zoom-tag" data-zoom-tag hidden></div>
      </div>

      <!-- Vidéo-arbitrage -->
      <section class="vr-var" data-var-panel>
        <div class="vr-var-row">
          <button class="vr-var-main" type="button" data-var-toggle><span class="dot"></span><span data-var-label>Vidéo-arbitrage</span><kbd>V</kbd></button>
          <div class="vr-group" role="group" aria-label="Retour en arrière">
            <button class="vr-btn" type="button" data-seek="-10" title="Reculer de 10 s">−10 s</button>
            <button class="vr-btn" type="button" data-seek="-5" title="Reculer de 5 s">−5 s</button>
            <button class="vr-btn" type="button" data-seek="-1" title="Reculer d'1 s (Maj + ←)">−1 s</button>
            <button class="vr-btn vr-icon" type="button" data-frame="-1" title="Image précédente (←)">◀︎❘</button>
            <button class="vr-btn vr-play" type="button" data-play title="Lecture / pause (espace)">▶︎</button>
            <button class="vr-btn vr-icon" type="button" data-frame="1" title="Image suivante (→)">❘▶︎</button>
            <button class="vr-btn" type="button" data-seek="1" title="Avancer d'1 s (Maj + →)">+1 s</button>
            <button class="vr-btn" type="button" data-seek="5" title="Avancer de 5 s">+5 s</button>
          </div>
          <div class="vr-group" role="group" aria-label="Vitesse">
            <?php foreach (['1' => '×1', '0.5' => '×½', '0.25' => '×¼', '0.125' => '×⅛'] as $v => $l): ?>
              <button class="vr-btn vr-speed<?= $v === '1' ? ' on' : '' ?>" type="button" data-speed="<?= $v ?>"><?= $l ?></button>
            <?php endforeach; ?>
          </div>
          <div class="vr-group" role="group" aria-label="Angle">
            <button class="vr-btn vr-angle on" type="button" data-angle="1" title="Caméra 1 (touche 1)">Cam 1</button>
            <button class="vr-btn vr-angle" type="button" data-angle="2" title="Caméra 2 (touche 2)">Cam 2</button>
          </div>
          <div class="vr-group" role="group" aria-label="Zoom">
            <button class="vr-btn vr-icon" type="button" data-zoom="-1" title="Dézoomer (−)">−</button>
            <button class="vr-btn" type="button" data-zoom="0" title="Image entière (0)" data-zoom-label>100 %</button>
            <button class="vr-btn vr-icon" type="button" data-zoom="1" title="Zoomer (+)">+</button>
          </div>
          <label class="vr-switch" title="Le ralenti remplace le direct sur YouTube, avec le bandeau « Vidéo-arbitrage en cours »">
            <input type="checkbox" data-onair checked><span></span>Ralenti à l'antenne
          </label>
          <button class="vr-btn vr-live" type="button" data-go-live title="Fin de la vidéo-arbitrage, retour au direct (Échap)">Retour au direct</button>
        </div>
        <div class="vr-timeline" data-timeline>
          <div class="vr-tl-buffer" data-tl-buffer></div>
          <div class="vr-tl-markers" data-tl-markers></div>
          <div class="vr-tl-head" data-tl-head></div>
          <div class="vr-tl-scale" data-tl-scale></div>
        </div>
        <p class="vr-hint">Espace : lecture/pause · ← → : image par image · Maj + ← → : 1 s · J K L : reculer / pause / avancer · molette ou + − : zoom, glisser pour déplacer · 1 / 2 : angle · Échap : retour au direct</p>
      </section>
    </section>

    <aside class="vr-side">
      <section class="vr-card">
        <h2>Programme <small>envoyé sur YouTube</small></h2>
        <canvas class="vr-program" data-program width="1280" height="720" aria-label="Programme"></canvas>
        <div class="vr-group vr-group-full" role="group" aria-label="Caméra à l'antenne">
          <button class="vr-btn vr-program-cam on" type="button" data-program-cam="1">Cam 1 à l'antenne</button>
          <button class="vr-btn vr-program-cam" type="button" data-program-cam="2">Cam 2 à l'antenne</button>
        </div>
      </section>

      <?php foreach ($config['slots'] as $slot => $label): ?>
      <section class="vr-card vr-cam" data-cam="<?= (int) $slot ?>">
        <h2><?= e($label) ?> <span class="vr-cam-state" data-cam-state>Aucune source</span></h2>
        <div class="vr-cam-preview"><video data-cam-live muted playsinline autoplay></video></div>
        <label class="vr-label" for="src<?= (int) $slot ?>">Source</label>
        <select id="src<?= (int) $slot ?>" data-cam-source><option value="">Aucune</option><option value="phone">Téléphone / iPad (Wi-Fi)</option></select>
        <div class="vr-phone" data-cam-phone hidden>
          <p>Sur le téléphone ou l'iPad (même Wi-Fi), ouvrez <strong data-phone-url></strong> et saisissez :</p>
          <p class="vr-code" data-cam-code>······</p>
          <p class="vr-small" data-cam-device></p>
        </div>
      </section>
      <?php endforeach; ?>
      <button class="vr-btn vr-btn-ghost vr-allow" type="button" data-allow-cams>Autoriser les caméras de cet ordinateur</button>

      <section class="vr-card" data-yt>
        <h2>Direct YouTube</h2>
        <div data-yt-off>
          <p class="vr-small">Lancez le direct dans OBS (capture de la fenêtre « Sortie programme »), puis collez le lien du direct ici dès qu'il est visible sur YouTube : il sert à l'application et à « Revoir le combat ».</p>
          <input type="url" data-yt-link placeholder="https://youtube.com/live/…" aria-label="Lien du direct YouTube">
          <button class="vr-btn vr-btn-red" type="button" data-yt-start>Le direct YouTube a démarré</button>
        </div>
        <div data-yt-on hidden>
          <p><span class="vr-live-dot"></span> En direct depuis <strong data-yt-since></strong></p>
          <p class="vr-small">Vidéo <a data-yt-open target="_blank" rel="noopener"></a></p>
          <div class="vr-offset"><span class="vr-small">Recaler les replays</span>
            <button class="vr-btn vr-icon" type="button" data-yt-offset="-1">−1 s</button><strong data-yt-offset-val>0 s</strong><button class="vr-btn vr-icon" type="button" data-yt-offset="1">+1 s</button></div>
          <button class="vr-btn vr-btn-ghost" type="button" data-yt-stop>Fin du direct</button>
        </div>
      </section>
    </aside>
  </main>

  <div class="vr-hidden" aria-hidden="true" data-hidden-media></div>
</div>
<?= json_script('video-config', $config) ?>
