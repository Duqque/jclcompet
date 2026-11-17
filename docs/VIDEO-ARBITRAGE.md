# Vidéo-arbitrage et direct vidéo des tapis

Chaque tapis a sa **régie vidéo** : un ordinateur au bord du tapis, deux caméras dans l'axe (côté table et côté opposé).
La régie enregistre les deux caméras en continu, ajoute en haut à gauche l'incrustation du combat (noms, scores, shidos,
chronomètre du commissaire) et permet la **vidéo-arbitrage** : retour en arrière en direct, image par image, ralenti,
zoom, choix de l'angle. Le programme part sur **YouTube Live** (vidéo non répertoriée), que l'application JCL Live affiche
pour chaque tapis, sans compte. Chaque combat filmé a son « Revoir le combat » dans l'application.

La vidéo ne passe jamais par le serveur du site (Hostinger) : seuls l'état « vidéo-arbitrage en cours », les liens
YouTube et l'appairage des téléphones y transitent.

## Matériel par tapis

| Élément | Conseil |
|---|---|
| Ordinateur | Portable récent (processeur 4 cœurs, 8 Go de mémoire minimum), **Chrome ou Edge à jour**, sur secteur |
| Caméra 1 · côté table | Webcam USB 1080p dans l'axe du tapis (pied ou fixation), branchée à l'ordinateur |
| Caméra 2 · côté opposé | Téléphone ou iPad sur pied, dans l'axe, à l'horizontale, **branché sur secteur** (ou une 2e webcam si le câble le permet) |
| Réseau | Wi-Fi de la salle : l'ordinateur et le téléphone sur le **même réseau**. Envoi internet : environ 4 Mbit/s par tapis pour YouTube |
| OBS Studio | Gratuit (obsproject.com), installé sur l'ordinateur du tapis |

## Compte

Créez un compte au rôle **« Vidéo-arbitrage »** (Administration › Comptes). Il ouvre `/video` : choix de la compétition,
puis du tapis. Un administrateur y a aussi accès (Administration › Tapis et écrans TV › « Ouvrir la régie vidéo »).
Le compte vidéo ne peut ni saisir ni corriger un combat.

## Installation le jour J (par tapis)

1. Sur l'ordinateur du tapis, ouvrez Chrome, connectez-vous avec le compte vidéo, choisissez la compétition puis
   **« Ouvrir la régie »** du tapis.
2. **Caméra 1** : choisissez la webcam dans la liste « Source » (autorisez l'accès à la caméra si Chrome le demande).
3. **Caméra 2** (téléphone / iPad) : choisissez « Téléphone / iPad (Wi-Fi) ». Un code à 6 caractères s'affiche.
   Sur le téléphone, ouvrez l'adresse du site suivie de `/camera`, saisissez le code, puis « Démarrer la caméra ».
   Tournez l'appareil à l'horizontale. Le code reste valable 24 h ; la caméra se reconnecte seule en cas de coupure.
4. Cliquez **« Sortie programme (OBS) »** : une fenêtre 16:9 s'ouvre avec l'image diffusée. Laissez la régie ouverte.
5. Dans **YouTube Studio** › Créer › Passer au direct : visibilité **Non répertoriée**, latence « faible »,
   **DVR activé**. Copiez la clé de flux.
6. Dans **OBS** : Paramètres › Stream › YouTube (clé de flux) ; Sortie 1280×720, 30 i/s, 3 500 kbit/s ;
   Sources › **Capture de fenêtre** › la fenêtre « Programme · Tapis N » ; ajoutez le micro (audio de la salle).
   Cliquez « Démarrer le streaming ».
7. Dès que le direct apparaît sur YouTube, collez son lien dans la régie (« Direct YouTube ») et cliquez
   **« Le direct YouTube a démarré »** : l'application affiche alors la vidéo du tapis, et chaque combat qui commence
   obtient son « Revoir le combat ». Si les replays démarrent quelques secondes trop tôt ou trop tard, recalez avec −1 s / +1 s.

## Vidéo-arbitrage pendant un combat

- **V** (ou le bouton jaune) : la régie passe en vidéo-arbitrage 4 secondes en arrière.
  L'écran TV, la saisie du commissaire et l'application affichent **« Vidéo-arbitrage en cours »** ; le commissaire
  laisse le chronomètre arrêté (matte).
- **Ralenti à l'antenne** (activé par défaut) : le ralenti remplace le direct sur YouTube, avec le bandeau
  « VIDÉO-ARBITRAGE EN COURS », la vitesse, le zoom et la caméra. L'incrustation du combat reste en haut à gauche.
  Désactivez-le pour revoir l'action sans la montrer au public.
- Commandes : espace = lecture / pause · ← → = image par image · Maj + ← → = 1 seconde · J / L = 5 secondes ·
  ×1, ×½, ×¼, ×⅛ = vitesse · 1 / 2 = angle (même instant sur l'autre caméra) · molette ou + / − = zoom, glisser pour
  déplacer l'image, double-clic = zoom rapide · 0 = image entière.
- La ligne de temps couvre les 3 dernières minutes avec les repères du commissaire : hajime, waza-ari (W), ippon (I),
  yuko (Y), shido (S), osaekomi (O), matte (M). Cliquez un repère pour revoir l'action 2 secondes avant.
- **Échap** ou « Retour au direct » : fin de la vidéo-arbitrage, le direct reprend partout.

La régie garde **5 minutes** d'images par caméra en mémoire (au-delà, revoyez sur YouTube grâce au DVR).

## Questions fréquentes

- *Le téléphone reste « En attente de la régie »* : la régie du tapis doit être ouverte, et les deux appareils sur le
  même Wi-Fi (certains réseaux publics isolent les appareils : utilisez un routeur dédié ou le partage de connexion de
  l'ordinateur).
- *L'image de la sortie programme est figée* : la fenêtre de régie a été fermée ou rechargée ; rouvrez la régie puis
  « Sortie programme ».
- *Rien dans l'application* : le lien du direct n'a pas été enregistré dans la régie (étape 7).
- Les combats filmés avant l'étape 7 n'ont pas de replay dans l'application (repère de départ inconnu).

## Technique

- Régie : `public/assets/js/video.js` (MediaRecorder → MediaSource pour l'enregistrement, canvas 1280×720 pour le
  programme, `captureStream` vers la fenêtre de sortie). Caméras téléphone : `public/assets/js/camera.js` (WebRTC,
  signalisation par le serveur, serveur STUN public par défaut, réglable par `video_ice_servers` dans `config/app.php`).
- Serveur : `App\Services\VideoService`, contrôleurs `App\Controllers\Video\*`, migration `011_video.sql`
  (rôle `video`, permission `video.operate`, tables `video_streams` et `video_cameras`).
- La caméra n'est autorisée par le navigateur que sur `/video/…` et `/camera…` (en-tête Permissions-Policy).
