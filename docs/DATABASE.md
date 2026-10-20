# Base de données

MySQL 8.0+ (MariaDB 10.6+ compatible), InnoDB, `utf8mb4_unicode_ci`, clés étrangères, contraintes d'unicité et `CHECK`.

## Installation et migrations

- **Migrations versionnées** : `database/migrations/NNN_nom.sql`, appliquées dans l'ordre par `php bin/migrate.php`
  (table `schema_migrations`). `php bin/migrate.php --status` liste l'état.
- **Schéma complet** : `database/schema.sql`, généré par `php bin/build-schema.php` (installation sur base vide, phpMyAdmin).
- **Données** : `php bin/seed.php --content` (contenu réel du programme, idempotent) · `php bin/seed.php --demo` (fictif, refusé
  en production) · `php bin/reset-dev.php` (développement uniquement).

| Migration | Contenu |
|---|---|
| 001_initial_schema | comptes, rôles, clubs, compétitions, catégories, durées, tapis, jetons TV, athlètes, inscriptions, présences, poules, combats, résultats, événements, classements, ventes, documents, notifications, journal |
| 002_roles_permissions | 5 rôles, 20 permissions, contenus initiaux du site |
| 003_site_content | contenus éditables (sections, listes), clubs présents, historique des éditions, catégories « animation », saisie directe de la catégorie de poids, partenaires détaillés |

## Tables principales

| Domaine | Tables | Points clés |
|---|---|---|
| Comptes | `users`, `roles`, `permissions`, `role_permissions`, `login_attempts` | e-mail unique ; `is_demo` ; `must_change_password` |
| Clubs | `clubs`, `club_users`, `competition_clubs` | un compte entraîneur rattaché à un seul club ; liste « clubs présents » indépendante des inscriptions |
| Compétitions | `competitions`, `competition_settings` (clé/valeur JSON), `competition_categories` (tableaux âge × sexe ouverts, regroupement explicite, quotas) | statuts ; `uuid` et `slug` uniques ; paramètres propres à chaque compétition |
| Catégories | `age_categories`, `weight_categories`, `fight_duration_rules` | propres à chaque compétition (l'évolution d'une compétition ultérieure ne modifie pas l'historique) ; surclassement autorisé ; format poules / animation |
| Tapis | `tatamis`, `commissioner_tatamis`, `tv_tokens` | tapis créés selon `mat_count` ; tapis actif par commissaire ; jetons hachés |
| Athlètes | `athletes`, `registrations`, `attendance_records` | identité unique par club ; inscription unique par compétition ; catégorie naturelle et retenue ; poids déclaré / mesuré ; historique des présences |
| Poules | `pool_generations`, `pools`, `pool_athletes` | brouillon → validée → annulée ; un athlète dans une seule poule par génération ; poule verrouillable ; rang calculé / manuel / final |
| Combats | `bouts`, `bout_results`, `score_events` | durée figée à la création ; chronomètre en ms ; `version` (verrou optimiste) ; `client_event_id` unique (idempotence) ; révisions de résultats (validation, corrections avec motif) |
| Classements | `ranking_rules`, `athlete_rankings`, `club_rankings` | barème par place ; tables matérialisées recalculables |
| Ventes | `products`, `sales`, `sale_items` | prix figé dans chaque ligne ; remboursement = vente négative liée ; `client_ref` unique |
| Contenus | `site_contents`, `content_sections`, `content_items`, `partners`, `competition_history`, `documents` | tout le texte public est en base |
| Suivi | `notifications`, `audit_logs` | journal chaîné (`prev_hash`, `row_hash` HMAC) |

## Recalcul des classements

Les classements sont dérivés des combats **validés** :

- automatiquement à chaque validation ou correction (même transaction) ;
- manuellement : Administration › Classements › « Recalculer tous les classements » (`RankingService::recomputeCompetition`) ;
- à l'archivage (recalcul final avant passage en lecture seule).

## Conservation

Une compétition archivée conserve inscriptions, présences, poules, combats, révisions, événements de saisie, classements,
ventes, paramètres et journal. Rien n'est supprimé à l'archivage.
