# JCL Compét — compétitions de judo du JC Leforest

Application web complète pour organiser, administrer et suivre les compétitions du **Judo Club Leforest** :
des inscriptions des clubs jusqu'à l'archivage des résultats, avec combats en direct sur écrans TV.

- **PHP 8.2+** (testé en 8.5) · **Apache 2.4** · **MySQL 8** (testé en 8.4 ; MariaDB 10.6+ compatible) · PDO, requêtes préparées
- HTML / CSS / JavaScript natif (Fetch, aucun framework, aucun Node.js côté serveur)
- Temps réel par **polling** (≈ 1 s pour les écrans TV) : fonctionne sur un hébergement mutualisé (Hostinger)
- MySQL est la seule source de vérité : chronomètre, scores, classements, écrans TV lisent la même base

## Les espaces

| Espace | Adresse | Pour qui |
|---|---|---|
| Site vitrine | `/` | Public : accueil, programme de chaque compétition (horaires, déroulement, catégories, règlement, buvette, clubs, partenaires, loterie, historique), résultats, club, Taïso |
| Classement public | `/classement/{compétition}` | Écran de salle : clubs par points et par médailles, actualisation toutes les 120 s |
| Direct public | `/competitions/{compétition}/direct` | Combat en cours sur chaque tapis |
| Administration | `/admin` | Administrateur : CRM complet (compétitions, clubs, inscriptions, poules, résultats, classements, buvette, PDF, contenus du site, journal) |
| Espace club | `/espace-club` | Entraîneurs : inscriptions de leurs judokas, poules publiées, résultats du club |
| Accueil (jour J) | `/accueil` | Pointage des présences sur mobile, inscription sur place |
| Commissaire sportif | `/commissaire` | Saisie des combats de son tapis (chronomètre, avantages, pénalités, osaekomi) |
| Écran TV | `/tv/tapis/{n}?jeton=…` | Affichage plein écran d'un tapis, lecture seule, lien révocable |
| Caisse | `/caisse` | Vendeur : buvette et boutique |

## Démarrage rapide (poste local)

```bash
composer install                                   # Dompdf (PDF) et PHPUnit (tests)
cp .env.example .env                               # puis renseigner la base, APP_ENV=local, APP_URL…
php bin/migrate.php                                # crée les tables (ou : mysql … < database/schema.sql)
php bin/seed.php --content                         # contenu réel du programme JC Leforest
php bin/seed.php --demo                            # données FICTIVES de démonstration (refusé en production)
php bin/create-admin.php vous@exemple.fr "Votre nom"   # premier compte administrateur réel
```

Faire pointer Apache sur le dossier `public/` (voir [docs/INSTALLATION.md](docs/INSTALLATION.md)).
Réinitialisation complète en développement : `php bin/reset-dev.php` (refusé si `APP_ENV` n'est pas `local`).

### Comptes de démonstration (environnement local ou de démonstration uniquement)

| Rôle | Identifiant | Mot de passe |
|---|---|---|
| Administrateur | admin@demo-judo.test | AdminDemo2026! |
| Commissaire tapis 1 | commissaire1@demo-judo.test | CommissaireDemo2026! |
| Commissaire tapis 2 (et 3) | commissaire2@demo-judo.test | CommissaireDemo2026! |
| Entraîneur (JC Lensois, démo) | coach@demo-judo.test | CoachDemo2026! |
| Entraîneure (Hénin Judo, démo) | coach2@demo-judo.test | CoachDemo2026! |
| Accueil | accueil@demo-judo.test | AccueilDemo2026! |
| Buvette | buvette@demo-judo.test | VendeurDemo2026! |

Ces comptes sont marqués « démo » : ils sont **refusés à la connexion quand `APP_ENV=production`**, et le seed de démonstration
refuse de s'exécuter en production. Désactivez-les (Administration › Comptes) avant toute utilisation réelle.

La compétition de démonstration utilise les vrais clubs de l'édition 2026 (avec leurs logos) ; les judokas, résultats et
ventes sont inventés et marqués « démo ».

## Documentation

**Installer la démonstration sur Hostinger en SSH : [docs/HOSTINGER-SSH.md](docs/HOSTINGER-SSH.md)** (une commande : `php bin/install.php --demo`).

| Document | Contenu |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation locale et **sur Hostinger**, variables de configuration, HTTPS, mises à jour, **sauvegarde et restauration** |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | Guide administrateur, guide club, accueil, **guide commissaire sportif**, **guide des écrans TV**, buvette |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture, flux temps réel, algorithmes (poules, tapis, classements), sécurité, décisions prises |
| [docs/DATABASE.md](docs/DATABASE.md) | Modèle de données, migrations, recalcul des classements |
| [docs/TESTS.md](docs/TESTS.md) | Lancer les tests, fonctionnalités vérifiées, limites et points à finaliser |

## Organisation du code

```
public/            Racine web (seul dossier servi) : index.php (contrôleur frontal), assets (CSS, JS, polices, logo)
app/Core/          Noyau : routeur, requête/réponse, sessions, CSRF, authentification, validation, base de données
app/Services/      Logique métier : inscriptions, poules (PoolGenerator, MatDistributor), combats (BoutService),
                   classements, écrans TV, ventes, contenus, documents, PDF, journal d'audit
app/Controllers/   Contrôleurs par espace : Public, Admin, Club, Attendance, Commissioner, Tv, Sales
app/Views/         Gabarits PHP (échappement systématique) par espace + PDF
routes/            web.php (pages) · api.php (API JSON interne)
config/            app.php, database.php (lisent .env, hors racine publique)
database/          migrations/ versionnées · schema.sql (généré) · seeds/ (programme réel + démonstration fictive)
bin/               migrate, seed, reset-dev, create-admin, build-schema, backup.sh, restore.sh
storage/           logs, documents téléversés, exports, cache (jamais servis)
tests/             Unit, Integration (MySQL), Http (Apache)
```

## Licences des ressources embarquées

- Polices **Poppins** et **Barlow** — SIL Open Font License (`public/assets/fonts/*-OFL.txt`)
- **Dompdf** (génération PDF, via Composer) — LGPL 2.1
- Logos des clubs et des partenaires, encarts publicitaires et photos : issus du programme 2026 du JC Leforest,
  propriété de leurs titulaires.
