# Guide d'utilisation

1. [Administrateur](#1-administrateur) · 2. [Club / entraîneur](#2-club--entraîneur) · 3. [Accueil le jour J](#3-accueil-le-jour-j)
· 4. [Poules et tapis](#4-poules-et-tapis) · 5. [Commissaire sportif](#5-guide-du-commissaire-sportif)
· 6. [Écrans TV](#6-guide-des-écrans-tv) · 7. [Classement public](#7-classement-public) · 8. [Buvette](#8-buvette-et-boutique)
· 9. [Archivage](#9-fin-de-compétition-et-archivage) · 10. [Site vitrine](#10-site-vitrine)

---

## Connexion et navigation

La page **Connexion** propose quatre profils en bas à gauche (Club, Commissaire, Accueil & buvette, Organisation) et le
formulaire à droite. Le profil choisi n'accorde aucun droit : ce sont les rôles du compte, vérifiés côté serveur, qui décident.

Menu de l'administration (la compétition se choisit en haut du menu) :

| Rubrique | Contenu |
|---|---|
| **Inscrits** | cartes des judokas (photo facultative) ou liste, filtres par club, catégorie, sexe, présence |
| **Poules** | bouton de génération, poules par catégorie et âge, onglet « Répartition des tapis » |
| **Combats** | archive de tous les combats, filtres, détail et corrections |
| **Résultats** | podiums par catégorie |
| **Classements** | clubs par points et par médailles, classement individuel, recalcul |
| **Administration** | statut, informations, paramètres, catégories, durées, barème, tapis & TV, programme, clubs présents, PDF, journal |
| **Profils** | comptes (commissaires, accueil, buvette, entraîneurs…) et clubs |
| **Boutique** | produits et ventes |
| **Site & photos** | contenu du site vitrine et albums photos |

## 1. Administrateur

### Préparer une compétition (liste de contrôle)

| Étape | Où | À vérifier |
|---|---|---|
| 1. Créer la compétition — ou **« Préparer l'édition suivante »** depuis l'édition précédente (copie catégories avec années +1, durées, barème, buvette, textes) | Compétitions | Date, lieu, nombre de tapis, dates d'ouverture/clôture des inscriptions, date de référence des âges |
| 2. Catégories | Catégories | Années de naissance de chaque catégorie, tableaux ouverts (filles/garçons), catégories de poids, quotas, surclassements, catégories « animation » (École de Judo : sans combat) |
| 3. Durées de combat | Durées de combat | Une règle par catégorie (durée, golden score). Les règles « exemple à confirmer » sont signalées. |
| 4. Paramètres sportifs | Paramètres sportifs | Regroupement des poules (par catégorie de poids ou morphologique), saisie du poids (le club choisit la catégorie = pas de pesée), modèle de score (yuko, osaekomi 5/10/20 s), classement des poules, égalités, forfaits, ordre des médailles, couleur rouge ou bleue, rafraîchissement des écrans |
| 5. Barème | Barème de points | Points et médaille par place (exemple : 1er 10 pts or, 2e 7 argent, 3e 5 bronze, 4e 3, 5e 2, 7e 1) |
| 6. Clubs et comptes | Clubs | Un compte entraîneur par club (mot de passe provisoire, changé à la première connexion) |
| 7. Tapis et écrans | Tapis et écrans TV | Commissaires affectés à chaque tapis ; un lien d'écran TV par tapis |
| 8. Programme | Programme (contenus) | Horaires, déroulement, plan de la salle, loterie… |
| 9. Ouvrir les inscriptions | Vue d'ensemble › Statut | Statut « Inscriptions ouvertes » : les clubs peuvent inscrire jusqu'à la date de clôture |

### Statuts d'une compétition

Brouillon (invisible au public) → Inscriptions ouvertes → Inscriptions clôturées → Contrôle des présences → Préparation des poules
→ Compétition en cours → Compétition terminée → Archivée (lecture seule). Le passage « en cours » exige une composition des poules
validée et signale les durées manquantes. Le premier combat démarré passe automatiquement la compétition « en cours ».

### Inscriptions

- Liste filtrable (club, catégorie, sexe, présence, statut), recherche, pagination, export CSV, import CSV.
- Contrôles automatiques : champs obligatoires, catégorie d'âge calculée depuis l'**année de naissance**, tableau ouvert,
  catégorie de poids cohérente avec le poids ou choisie par le club, **doublons** (même nom, prénom, date de naissance),
  quotas, dates limites. Après la clôture, seul l'administrateur peut inscrire (inscription marquée « tardive »).
- Les inscriptions tardives / sur place peuvent exiger une validation (réglage) : bouton « Valider l'inscription tardive ».

### Résultats et corrections

**Combats et résultats** : vue de tous les combats (filtres tapis, catégorie, sexe, poids, poule, état). Un résultat validé se
corrige à tout moment : saisie des scores, du vainqueur et du type de victoire, **motif obligatoire**, confirmation. L'ancienne
et la nouvelle valeur, l'auteur et l'heure sont conservés (historique du combat + journal) ; classements et écrans sont
recalculés immédiatement.

### Classements

Chaque poule est classée quand **tous** ses combats sont validés (jamais de médaille sur un résultat incomplet) :
victoires, puis points de valeur des victoires (ippon 10, waza-ari 7, yuko 5, décision 1 — modifiable), puis confrontation directe.
Égalité persistante : « Égalité à départager » — saisir l'ordre final (avec motif) ou ajouter un **combat de barrage**.
Classement des clubs par points (somme du barème) et, indépendamment, par médailles (ordre des critères configurable).
« Recalculer tous les classements » reconstruit tout depuis les résultats validés.

### Documents PDF

Tous les PDF reprennent le style du site (police Inter, cartes arrondies, logos des clubs dans une carte blanche).
**Résultats par poule** (feuilles complètes dans l'ordre des âges, puis des poids) et **Résultats par club** (catégorie par
catégorie, ordre des âges, des poids et du classement) s'ajoutent aux listes, poules, planning et classements.

Participants, listes par catégorie, poules (A4 portrait/paysage, 1/2/4/6 par page, options d'affichage, cases de saisie
manuelle), feuilles de résultats, planning des tapis, classement final, classement des clubs.

### Journal d'activité

Toutes les actions sensibles sont journalisées (connexion, inscriptions, présences, poules, combats, corrections, contenus…).
Le journal n'est pas modifiable depuis l'interface ; « Vérifier l'intégrité du journal » détecte toute modification directe en base.

---

## 2. Club / entraîneur

Le menu de l'espace club affiche en permanence la compétition en cours et toutes ses rubriques :

- **Mes combattants** : cartes des judokas (photo facultative, catégorie, poids, statut). Bouton **Ajouter un combattant** :
  nouveau judoka, judoka déjà venu à une édition précédente, ou import d'un fichier CSV. Modification et retrait jusqu'à la
  date limite.
- **Valider ma liste** : une fois la liste complète, le coach la valide ; toutes ses inscriptions sont **confirmées
  automatiquement** et l'organisation est prévenue (notification + compteur « listes validées » sur la page Inscrits).
  Toute modification ultérieure demande une nouvelle validation.
- **Le jour J** : le coach pointe lui-même **Présent / Absent** et saisit le **poids mesuré** de ses judokas (tant qu'ils ne
  sont pas placés en poule).
- **Poules en direct** : feuilles de poule de ses judokas (surlignés) ou de toutes les poules, recherche par nom, mise à jour
  automatique dès qu'un commissaire valide un résultat.
- **Résultats du club**, **Résultats en PDF** (catégorie par catégorie), **Classement en direct**, **Programme**.
- **Photos** : consulter l'album de la compétition et **ajouter ses propres photos** (l'organisation est prévenue).
- **Informations du club** (coordonnées, logo) et **Mot de passe**.

Un club ne voit jamais les données privées des autres clubs et ne peut pas modifier un résultat sportif.

---

## 3. Accueil le jour J

Interface mobile **/accueil** (compte « Accueil » ou administrateur) :

- Recherche instantanée par nom ou prénom ; filtres club, catégorie, sexe, présence (mémorisés sur l'appareil).
- Boutons **Présent**, **Absent / forfait**, **À vérifier** (avec note) ; l'heure et la personne ayant pointé sont enregistrées.
- En mode « pesée » (poids indiqué par le club), le poids mesuré peut être saisi au pointage ; il redétermine la catégorie de poids.
- Sélecteur de club en fenêtre (cartes aux couleurs de chaque club, logo et nom) ; cartes des judokas avec photo.
- **+ Inscription sur place** : club (ou exception autorisée), identité, catégorie de poids ; l'athlète est pointé présent
  immédiatement (validation administrateur requise si le réglage l'exige).

Seuls les athlètes **présents et confirmés** sont utilisés pour composer les poules.

---

## 4. Poules et tapis

La page **Poules** suit 5 étapes, affichées en haut de page :

1. **Générer / Mettre à jour les poules** : recalcule les poules à chaque clic tant qu'aucun combat n'a commencé (les poules
   verrouillées sont conservées). Âges et sexes ne sont jamais mélangés ; les poids sont rapprochés, les clubs séparés.
2. **Répartir sur les tapis** : équilibre la durée entre les tapis.
3. **Ajuster par glisser-déposer** (vue « Répartition des tapis ») : cliquer-glisser un judoka (poignée ⋮⋮) vers une autre
   poule, ou sur un autre judoka pour les échanger ; glisser une poule vers un autre tapis. Sur tablette : appui long puis
   glisser. Une poule commencée ou verrouillée ne se modifie plus (cadenas).
4. **Valider les poules** : les combats sont créés avec la configuration de leur catégorie et apparaissent chez les commissaires.
5. **Imprimer les feuilles** : par tapis ou toutes, **avec résultats** ou **vierges** ; plusieurs poules d'un même tapis par page
   quand elles tiennent entièrement, nouvelle page à chaque tapis. Chaque feuille a aussi son bouton PDF.

Affichages : **Feuilles de poule** (grille 1 à 5, cases noires en diagonale, scores au format **IWY-shido** — ex. « 100-1 » —,
victoires, points, place ; ordre des combats avec le premier appelé en ceinture rouge et le second en ceinture blanche),
**Cartes**, **Liste**, **Répartition des tapis**. La recherche retrouve une poule par nom de judoka, club ou numéro.
Chaque case de résultat ouvre le combat pour le corriger (motif obligatoire, historique conservé).

Un seul numéro de poule est utilisé partout (feuilles, combats, TV). Ordre des combats : poules de 3 « 1×2 / 2×3 / 1×3 »,
de 4 « 1×2 / 3×4 / 1×3 / 2×4 / 1×4 / 2×3 », de 5 « 1×2 / 3×4 / 1×5 / 2×4 / 3×5 / 1×4 / 2×3 / 4×5 / 1×3 / 2×5 ».
Sur un tapis, les poules d'une même catégorie d'âge s'enchaînent **par blocs de 2 combats** (A, B, C, puis A, B, C…) pour la
récupération des judokas (réglable dans Paramètres : taille des blocs, poules d'une même catégorie ou de tout le tapis).

### Configurations de combat (Administration › Combats)

Une configuration par catégorie (âge, sexe, phase) : durée, golden score (illimité, limité, aucun), premier avantage au golden
score, récupération, **temps d'immobilisation** yuko / waza-ari / ippon, yuko compté ou non, nombre de waza-ari pour un ippon,
nombre de shido pour hansoku-make et **règles particulières** (rappelées au commissaire pendant le combat). Un champ vide reprend
les paramètres sportifs. Chaque combat garde sa configuration au moment de sa création ; « Réappliquer » met à jour les combats
à venir.

---

## 5. Guide du commissaire sportif

**Avant la compétition** : se connecter, ouvrir **Mes tapis**, choisir le tapis (un seul tapis actif à la fois).

### L'écran de saisie

- **À gauche** : tous les combats restants du tapis, dans l'ordre. Le combat chargé est en rouge. Toucher un combat pour le charger
  (impossible tant que le combat en cours n'est pas validé).
- **En haut** : « ← Retour » vers vos paramètres (l'écran TV n'est pas affecté), état de la connexion, **chronomètre**,
  bouton ▶ / ❚❚ (**Hajime / Matte**, aussi avec la **barre d'espace**), « Fin du combat ».
- **Au centre** : une carte par combattant (AKA rouge — ou bleu — et SHIRO) : waza-ari (2 → victoire), shido (3 → hansoku-make),
  yuko, ippon ; boutons **Ippon**, **Waza-ari**, **Yuko**, **Shido**, **Hansoku-make**, chacun avec « − » pour annuler une erreur.
- **Osaekomi** : toucher « Osaekomi » du combattant qui immobilise (chronomètre en marche). La barre progresse ; « Toketa »
  arrête l'immobilisation et attribue automatiquement **yuko à 5 s, waza-ari à 10 s, ippon à 20 s** (seuils réglables).
  À 20 s, l'ippon est envoyé automatiquement. « Matte » met aussi fin à l'immobilisation.

### Déroulé d'un combat

1. Charger le combat (« Combat suivant » ou liste de gauche) → l'écran TV affiche « Prochain combat ».
2. **Hajime** → le temps défile ; **Matte** l'arrête. Le temps est conservé par le serveur : recharger la page ne le remet pas à zéro.
3. Ippon, 2 waza-ari ou hansoku-make terminent le combat automatiquement. Sinon « Fin du combat » : vainqueur aux avantages ;
   en cas d'égalité, choisir **Golden score** (temps écoulé) ou une **décision**. En golden score, le premier avantage gagne.
4. Vérifier puis **Valider le résultat** (la double validation est sans effet). L'écran TV affiche **VICTOIRE**, puis le combat
   suivant après 20 s.
5. « Forfait / abandon » : désigner le vainqueur (fusen-gachi ou kiken-gachi).
6. Erreur après validation : « Corriger ce résultat » (motif obligatoire) — possible sur vos tapis tant que la compétition
   n'est pas terminée.

### Coupure réseau

Le voyant passe à « Hors ligne » : les actions sont mises en attente et renvoyées automatiquement, **sans risque de double
comptage**. Ne fermez pas la page tant que des actions sont en attente. Si un autre poste a modifié le combat, un message
l'indique et l'affichage se met à jour.

---

## 6. Guide des écrans TV

1. Administration › **Tapis et écrans TV** › « Créer un lien d'affichage » (validité 1 à 30 jours). Le lien complet n'est affiché
   **qu'une fois** : le copier (bouton « Copier le lien ») ou l'ouvrir directement.
2. Sur le téléviseur (navigateur, clé HDMI, mini-PC…), ouvrir le lien : `https://…/tv/tapis/1?jeton=…`. **Ne jamais se
   connecter avec un compte sur un téléviseur.**
3. Double-cliquer (ou bouton « Plein écran » au mouvement de la souris) pour passer en plein écran ; le curseur se masque et
   l'écran reste allumé si le navigateur le permet.
4. L'écran affiche : catégorie, poids, numéro du combat dans la poule, temps restant (ou golden score), pour chaque combattant
   nom, club, logo, score **I W Y** (ex. `100` ippon, `020` deux waza-ari, `001` yuko), shidos, la **barre d'osaekomi** (repères
   5 / 10 / 20 s ; le chiffre qui va changer **clignote** dès le seuil franchi), « VICTOIRE », et les combats suivants.
5. **Désactiver** un écran (ex. TV volée ou inutilisée), **réinitialiser** (nouveau lien, les anciens cessent de fonctionner) ou
   **révoquer** depuis la même page. L'état de chaque écran (connecté, non connecté) est visible sur le tableau de bord.
6. Coupure : l'écran garde le dernier état, affiche « Connexion perdue » et reprend seul.

---

## 7. Classement public

`/classement/{compétition}` : écran de salle au style du site, tous les clubs engagés (même à 0 point), par points et par
médailles. **Mise à jour en direct** : dès qu'un commissaire valide un résultat, le classement se met à jour en quelques secondes.
Quand une poule est terminée, une **annonce des podiums** apparaît (photo du judoka si l'organisation l'autorise dans les
Paramètres — avec l'accord des familles —, sinon logo du club, nom, catégorie d'âge et de poids, place et médaille).
Bouton **Plein écran**, défilement automatique lent pour voir tout le classement, carrousel des partenaires en bas d'écran.

---

## 8. Buvette et boutique

- **Administration › Buvette et boutique** : produits et tarifs (affichés aussi sur la page publique, rubrique Buvette),
  chiffre d'affaires **brut**, **remboursements**, **recettes nettes**, par produit, par journée, par moyen de paiement ;
  export CSV ; remboursement d'une vente (motif). Les montants ne sont pas des bénéfices (coûts d'achat inconnus).
- **Caisse** (`/caisse`, compte Vendeur) : toucher les produits, choisir le paiement, « Encaisser ». Aucun paiement réel n'est
  traité ; une vente renvoyée après coupure n'est jamais enregistrée deux fois.

---

## 9. Fin de compétition et archivage

1. Vérifier qu'aucune égalité n'est à départager (Classements) et télécharger les PDF de résultats.
2. Statut « Compétition terminée ».
3. **Archiver** : résultats, poules, clubs, paramètres et journal sont conservés ; tout passe en **lecture seule** et les écrans
   TV sont désactivés. Une réouverture exige une action explicite avec motif.
4. Site vitrine › Historique › « Calculer depuis une compétition » ajoute l'édition au tableau historique.

---

## 10. Site vitrine

**Administration › Site vitrine** :

- *Coordonnées et textes* : nom, adresse, téléphones, e-mail, réseaux sociaux, chiffres du club, présentation, mentions légales.
- *Pages* : sections des pages Notre club, Taïso, Accueil (titre, texte mis en forme, image).
- *Listes* : staff, résultats de la saison, formés au club, horaires des cours, infos Taïso, photos du dojo.
- *Partenaires* : par catégorie (institutionnels, fédéraux, évènement et loterie, membres de l'UTIL, mécènes, fournisseurs),
  logo, encart publicitaire, lien, description, rattachement à une compétition.
- *Historique* : saisons, clubs, participants par catégorie, combats arbitrés.
- *Documents et images* : téléversement (PDF, PNG, JPEG, WebP), lien à copier, suppression.

Le programme de chaque compétition se modifie depuis la compétition : **Programme (contenus)** (sections et listes : horaires,
déroulement, accès au plateau, lots de la loterie…), **Clubs présents**, **Buvette**, **Catégories**, **Durées**.

### Photos de la compétition

**Site & photos › Photos** : choisir la compétition, définir le **mot de passe de l'album** (6 caractères minimum), puis
téléverser les photos (JPEG, PNG, WebP ; orientation corrigée, miniatures générées). Les photos sont des fichiers privés :
elles ne sont servies qu'après saisie du mot de passe sur la page **Photos** du site (essais limités), ou à un compte
connecté de l'organisation. « Fermer l'album » retire l'accès public sans supprimer les photos. Changer le mot de passe
déconnecte les appareils déjà déverrouillés. En démonstration, l'album fictif s'ouvre avec `photos2026`.

Mise en forme des textes : `**gras**`, `*italique*`, `- ` en début de ligne pour une liste, `[texte](https://lien)`,
`^e^` pour un exposant, une ligne vide pour un nouveau paragraphe.
