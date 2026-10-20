# Guide d'utilisation

1. [Administrateur](#1-administrateur) · 2. [Club / entraîneur](#2-club--entraîneur) · 3. [Accueil le jour J](#3-accueil-le-jour-j)
· 4. [Poules et tapis](#4-poules-et-tapis) · 5. [Commissaire sportif](#5-guide-du-commissaire-sportif)
· 6. [Écrans TV](#6-guide-des-écrans-tv) · 7. [Classement public](#7-classement-public) · 8. [Buvette](#8-buvette-et-boutique)
· 9. [Archivage](#9-fin-de-compétition-et-archivage) · 10. [Site vitrine](#10-site-vitrine)

---

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

Participants, listes par catégorie, poules (A4 portrait/paysage, 1/2/4/6 par page, options d'affichage, cases de saisie
manuelle), feuilles de résultats, planning des tapis, classement final, classement des clubs.

### Journal d'activité

Toutes les actions sensibles sont journalisées (connexion, inscriptions, présences, poules, combats, corrections, contenus…).
Le journal n'est pas modifiable depuis l'interface ; « Vérifier l'intégrité du journal » détecte toute modification directe en base.

---

## 2. Club / entraîneur

1. Se connecter sur **/connexion** avec le compte fourni par l'organisation (changer le mot de passe provisoire).
2. **Mes compétitions** › « Inscrire mes judokas » : nom, prénom, sexe, date de naissance, **catégorie de poids** (la catégorie
   d'âge s'affiche automatiquement), grade et licence facultatifs. Un judoka déjà inscrit à une édition précédente se
   réinscrit en un clic.
3. Modifier ou retirer une inscription jusqu'à la date limite ; télécharger la liste (CSV) ; importer un fichier CSV.
4. Le jour J : **Poules et horaires** (poules publiées, tapis, ordre de passage) et **Résultats du club** (places, médailles,
   rang du club, combats de vos judokas).

Un club ne voit jamais les données privées des autres clubs et ne peut pas modifier un résultat sportif.

---

## 3. Accueil le jour J

Interface mobile **/accueil** (compte « Accueil » ou administrateur) :

- Recherche instantanée par nom ou prénom ; filtres club, catégorie, sexe, présence (mémorisés sur l'appareil).
- Boutons **Présent**, **Absent / forfait**, **À vérifier** (avec note) ; l'heure et la personne ayant pointé sont enregistrées.
- En mode « pesée » (poids indiqué par le club), le poids mesuré peut être saisi au pointage ; il redétermine la catégorie de poids.
- **+ Inscription sur place** : club (ou exception autorisée), identité, catégorie de poids ; l'athlète est pointé présent
  immédiatement (validation administrateur requise si le réglage l'exige).

Seuls les athlètes **présents et confirmés** sont utilisés pour composer les poules.

---

## 4. Poules et tapis

1. **Générer les poules** : le système regroupe les présents par catégorie d'âge × sexe (× catégorie de poids selon le réglage),
   trie par poids, choisit la meilleure combinaison de tailles autorisées (3, 4, 5) en minimisant les écarts de poids et les
   poules déséquilibrées, sépare les combattants d'un même club quand c'est possible, calcule les combats
   (n × (n − 1) / 2) et répartit les poules sur les tapis en équilibrant la charge.
2. **Aperçu** : charge et temps estimé de chaque tapis, écart entre le tapis le plus et le moins chargé, alertes (écart de poids,
   même club, taille hors règlement, durées manquantes).
3. **Groupes sans solution** (ex. 2 athlètes alors que les poules de 2 ne sont pas autorisées) : rien n'est supprimé ;
   choisir une solution proposée (poule de 2, regroupement avec la catégorie voisine — exception explicite —, ou laisser hors poule).
4. **Ajuster** : glisser une poule vers un autre tapis, un athlète vers une autre poule, un athlète sur un autre pour les échanger
   (sur tablette : menus « Déplacer » et formulaire en bas de page). Verrouiller une poule la protège des régénérations.
5. **Valider la composition** : les combats sont créés avec la durée applicable (figée) et apparaissent chez les commissaires.
6. Après validation : une poule dont un combat a commencé n'est plus modifiable. Un athlète arrivé en retard apparaît dans
   « non placés » avec des suggestions (poule compatible non commencée, recalcul du groupe) — aucun résultat existant n'est écrasé.

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

`/classement/{compétition}` (lien depuis la page Résultats ou la vue d'ensemble) : écran distinct, à afficher en salle.
Classements des clubs par points et par médailles, totaux, combats validés, heure de mise à jour, actualisation automatique
toutes les 120 s (réglable) et bouton « Actualiser ». Seuls les résultats validés sont comptés.

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

Mise en forme des textes : `**gras**`, `*italique*`, `- ` en début de ligne pour une liste, `[texte](https://lien)`,
`^e^` pour un exposant, une ligne vide pour un nouveau paragraphe.
