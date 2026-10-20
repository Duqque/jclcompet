# Architecture

## Vue d'ensemble

```
Navigateur (site, admin, club, accueil, commissaire, TV, caisse)
      │  HTML rendu serveur + Fetch JSON (polling)
      ▼
public/index.php ── Router (routes/web.php, routes/api.php) ── middlewares (auth, role, can, CSRF)
      │
      ▼
Contrôleurs (app/Controllers/*) ── minces : validation d'entrée, appel des services, rendu
      │
      ▼
Services (app/Services/*) ── toute la logique métier et les contrôles d'autorisation fins
      │   PoolGenerator, MatDistributor : algorithmes purs (testés sans base)
      ▼
Database (PDO, requêtes préparées, transactions avec rejeu sur verrou mortel) ── MySQL (source de vérité)
```

- **Pas de framework** : un noyau minimal (`app/Core`) pour rester installable sur un hébergement mutualisé sans dépendance.
  Composer n'apporte que Dompdf (PDF) et PHPUnit (tests) ; sans `vendor/`, un chargeur PSR-4 interne prend le relais et les PDF
  passent en version HTML imprimable.
- **Rendu** : gabarits PHP (`app/Views`), échappement systématique via `e()`, Markdown simplifié et sûr pour les textes éditables.
- **JavaScript** : un fichier par interface (`public/assets/js`), aucun script en ligne (CSP `script-src 'self'`), données
  initiales transmises en `<script type="application/json">`.

## Temps réel sans WebSocket

| Écran | Mécanisme | Fréquence |
|---|---|---|
| TV | `GET /api/tv/etat?tapis=&jeton=` | `tv_refresh_ms` (1 s par défaut), recul progressif en cas d'erreur |
| Commissaire | `GET /api/commissaire/tapis/{id}` + réponses des actions | 2 s (6 s si l'onglet est masqué) |
| Direct public | `GET /api/public/…/direct` | 5 s |
| Classement public | `GET /api/public/…/classement` | 120 s (réglable) + manuel |

**Chronomètre** : le serveur stocke `clock_elapsed_ms` (temps accumulé) et `clock_started_at_ms` (horodatage du dernier départ,
en millisecondes). Chaque client calcule `écoulé = accumulé + (maintenant_serveur − départ)` en corrigeant le décalage d'horloge
mesuré à chaque réponse (`server_now_ms`, moitié du temps aller-retour). Le temps ne dépend donc ni du navigateur ni d'un
rechargement. L'osaekomi suit le même principe (`osaekomi_started_at_ms`).

**Cohérence des saisies** (`BoutService::act`) :

1. transaction + `SELECT … FOR UPDATE` sur le combat ;
2. **idempotence** : chaque action porte un `client_event_id` unique (contrainte unique en base) — un renvoi après coupure
   renvoie l'état sans réappliquer ;
3. **verrou optimiste** : l'action porte la `version` connue ; si un autre poste a modifié le combat → 409 + état à jour ;
4. horodatage du poste accepté s'il est cohérent (±5 s) pour qu'une action différée par une coupure garde le bon temps ;
5. validation : écriture du résultat (`bout_results`, révisions), statut de la poule, recalcul du classement de la poule et des
   clubs, le tout dans la même transaction.

Côté interface, les actions partent dans une **file** (une à la fois, reprise exponentielle jusqu'à 10 s), l'état local n'est
jamais la source de vérité (aucun `localStorage` pour les données métier).

## Algorithmes

### Composition des poules (`PoolGenerator`)

Pour chaque groupe compatible (âge × sexe, × catégorie de poids si ce mode est choisi ; regroupement d'âges uniquement par
exception explicite `merge_group`, jamais filles/garçons) :

1. tri par poids de référence (mesuré si autorisé, sinon déclaré ; tirage au sort reproductible si aucun poids) ;
2. **programmation dynamique** sur les découpages contigus en tailles autorisées :
   coût = Σ écart de poids relatif (%) + 6 × Σ (taille − taille préférée)² → poules homogènes et équilibrées
   (11 athlètes en 3/4/5 → 4 + 4 + 3) ;
3. échanges entre poules voisines pour séparer les combattants d'un même club (tolérance de poids 6 %) ;
4. aucune solution → aucune poule créée, alternatives calculées (poule de 2 ou 6, regroupement, hors poule) et décision de
   l'administrateur enregistrée (`pool_group_overrides`) — aucun athlète n'est écarté silencieusement.

Ordre des combats : ordres fédéraux usuels (3 : 1-2, 2-3, 1-3 ; 4 : 1-2, 3-4, 1-3, 2-4, 1-4, 2-3 ; 5 : sans combat consécutif),
méthode du cercle au-delà ; option d'alternance de deux poules d'un même tapis pour la récupération.

### Répartition sur les tapis (`MatDistributor`)

Charge d'une poule = combats × (durée réglementaire + battement). Affectation des plus grosses poules d'abord au tapis le moins
chargé (LPT), puis recherche locale (déplacements et échanges entre deux tapis quelconques) minimisant l'écart max − min puis la
somme des carrés, avec des essais supplémentaires à graine fixe (résultat reproductible). Les poules verrouillées ou commencées
gardent leur tapis. L'écart résiduel est affiché.

### Classements (`RankingService`)

Classement de poule : victoires → points de valeur → confrontation directe (2 athlètes) ou mini-classement (3+) → départage
manuel (ou places partagées selon le réglage). Places, points (barème) et médailles uniquement pour les poules complètes ;
politique de forfait configurable ; exclusions motivées. Clubs : somme des points, comptage des médailles, rangs « sportifs »
(1, 1, 3) pour chacun des deux classements. Les tables `athlete_rankings` / `club_rankings` sont recalculables à tout moment
depuis les résultats validés.

## Sécurité

| Mesure | Mise en œuvre |
|---|---|
| Mots de passe | `password_hash` / `password_verify`, réhachage automatique, 12 caractères minimum, changement imposé des mots de passe provisoires |
| Sessions | cookie HttpOnly, SameSite=Lax, Secure en HTTPS, mode strict, régénération à la connexion, expiration d'inactivité |
| Connexion | limitation par identifiant et par IP (table `login_attempts`), temps de réponse constant, comptes démo refusés en production |
| CSRF | jeton de session sur tout POST (formulaires et en-tête `X-CSRF-Token` pour l'API) |
| Autorisations | rôles → permissions en base ; middleware `can:` sur chaque route sensible **et** contrôles dans les services (club propriétaire, tapis affecté et actif, compétition archivée → 423) |
| Injections | PDO requêtes préparées partout (`ATTR_EMULATE_PREPARES=false`), noms de colonnes jamais issus de l'utilisateur |
| XSS | échappement systématique, Markdown échappé, CSP stricte, `X-Content-Type-Options`, `X-Frame-Options` |
| Fichiers | stockés hors racine (`storage/documents`), type vérifié par `finfo`, SVG refusé, servis par `/media/{id}` |
| Écrans TV | jeton aléatoire 192 bits, seul le SHA-256 est stocké, limité à un tapis, lecture seule, expiration, désactivation, révocation |
| Secrets | `.env` hors racine, jamais versionné ; `.htaccess` bloquant `app/`, `config/`, `storage/`… |
| Erreurs | pages génériques, détails uniquement dans `storage/logs` |
| Traçabilité | journal d'audit en ajout seul, chaîné par HMAC (détection de toute altération) |
| Données personnelles | minimum nécessaire (licence et grade facultatifs) ; les pages publiques n'exposent ni dates de naissance, ni licences, ni coordonnées des clubs |

## Décisions prises (à confirmer si besoin)

- **Durées, barème, points de valeur, seuils d'osaekomi** : valeurs de départ marquées comme exemples ou reprises du programme
  2026 ; tout est modifiable par compétition, aucune valeur n'est codée en dur dans le moteur.
- **Golden score** des catégories du programme 2026 : « illimité » par défaut (le programme ne le précise pas).
- **Édition 2026** : importée avec son statut réel (« terminée », le 26/09/2026 est passé), sans inscrits ni résultats (non fournis).
  « Préparer l'édition suivante » crée l'édition 2027 en un clic.
- **Adresse de la salle de compétition** : non indiquée dans le programme (« à compléter » dans Informations).
- **Format** : poules toutes rondes (le programme : « 2 à 5 combats selon le nombre de judokas ») ; tailles 3, 4, 5 autorisées,
  poule de 2 ou de 6 sur décision de l'administrateur ; pas de tableau à élimination ni de repêchage (combats de barrage
  manuels possibles).
- **Deux waza-ari** : victoire (awasete), le score reste affiché « 020 » sur les écrans, type de victoire « ippon ».
