-- JCL Compét — schéma complet (généré par bin/build-schema.php à partir des migrations, ne pas modifier à la main)
-- Installation : mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/schema.sql
-- Compatible MySQL 8.0+ (et MariaDB 10.6+).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================================
-- 001_initial_schema.sql
-- ======================================================================
-- JCL Compét — migration 001 : schéma initial
-- Compatible MySQL 8.0+ et MariaDB 10.6+ (InnoDB, utf8mb4).
-- Toutes les heures sont stockées en heure locale de la compétition (Europe/Paris par défaut),
-- les horodatages du chronomètre en millisecondes depuis l'epoch (BIGINT) pour éviter toute ambiguïté.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Comptes, rôles, permissions
-- ---------------------------------------------------------------------------
CREATE TABLE roles (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(32)  NOT NULL,
    name        VARCHAR(80)  NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(64)  NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email                VARCHAR(190) NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    display_name         VARCHAR(120) NOT NULL,
    role_id              SMALLINT UNSIGNED NOT NULL,
    phone                VARCHAR(40)  NULL,
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    is_demo              TINYINT(1) NOT NULL DEFAULT 0,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at        DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier   VARCHAR(190) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_identifier (identifier, attempted_at),
    KEY idx_login_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Clubs
-- ---------------------------------------------------------------------------
CREATE TABLE clubs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(150) NOT NULL,
    short_name   VARCHAR(40)  NULL,
    logo_path    VARCHAR(255) NULL,
    city         VARCHAR(120) NOT NULL,
    country      CHAR(2)      NOT NULL DEFAULT 'FR',
    contact_name VARCHAR(120) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    phone        VARCHAR(40)  NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    is_demo      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clubs_name_city (name, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE club_users (
    club_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (club_id, user_id),
    UNIQUE KEY uq_club_users_user (user_id),
    CONSTRAINT fk_cu_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Documents (règlements, visuels, logos) — stockés hors racine publique
-- ---------------------------------------------------------------------------
CREATE TABLE documents (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NULL,
    type           ENUM('regulation','visual','partner_logo','club_logo','coach_info','other') NOT NULL DEFAULT 'other',
    title          VARCHAR(190) NOT NULL,
    original_name  VARCHAR(255) NOT NULL,
    stored_name    VARCHAR(80)  NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    size_bytes     INT UNSIGNED NOT NULL,
    is_public      TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by    INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_stored (stored_name),
    KEY idx_documents_competition (competition_id, type),
    CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Compétitions et paramètres
-- ---------------------------------------------------------------------------
CREATE TABLE competitions (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                     CHAR(36)     NOT NULL,
    slug                     VARCHAR(160) NOT NULL,
    name                     VARCHAR(190) NOT NULL,
    tagline                  VARCHAR(255) NULL,
    description              TEXT NULL,
    event_date               DATE NOT NULL,
    start_time               TIME NOT NULL DEFAULT '09:00:00',
    venue                    VARCHAR(190) NULL,
    address                  VARCHAR(255) NOT NULL,
    city                     VARCHAR(120) NOT NULL,
    registration_opens_at    DATETIME NULL,
    registration_closes_at   DATETIME NULL,
    age_reference_date       DATE NULL,
    mat_count                TINYINT UNSIGNED NOT NULL DEFAULT 1,
    max_athletes             SMALLINT UNSIGNED NULL,
    expected_participants    SMALLINT UNSIGNED NULL,
    pool_sizes               VARCHAR(20) NOT NULL DEFAULT '3,4,5',
    schedule_text            TEXT NULL,
    practical_info           TEXT NULL,
    coach_info               TEXT NULL,
    cover_document_id        INT UNSIGNED NULL,
    regulation_document_id   INT UNSIGNED NULL,
    status                   ENUM('draft','registrations_open','registrations_closed','attendance','pool_preparation','in_progress','finished','archived') NOT NULL DEFAULT 'draft',
    is_featured              TINYINT(1) NOT NULL DEFAULT 0,
    is_demo                  TINYINT(1) NOT NULL DEFAULT 0,
    created_by               INT UNSIGNED NULL,
    archived_at              DATETIME NULL,
    archived_by              INT UNSIGNED NULL,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_competitions_uuid (uuid),
    UNIQUE KEY uq_competitions_slug (slug),
    KEY idx_competitions_status_date (status, event_date),
    CONSTRAINT fk_comp_cover FOREIGN KEY (cover_document_id) REFERENCES documents (id) ON DELETE SET NULL,
    CONSTRAINT fk_comp_regulation FOREIGN KEY (regulation_document_id) REFERENCES documents (id) ON DELETE SET NULL,
    CONSTRAINT fk_comp_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_comp_archiver FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE documents
    ADD CONSTRAINT fk_documents_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE;

-- Paramètres clé/valeur (JSON) propres à une compétition : modèle de score, règles de poules,
-- couleurs d'affichage, rafraîchissement TV, etc. Les valeurs par défaut vivent dans le code
-- (App\Services\SettingsService::DEFAULTS) et sont copiées à la création de la compétition.
CREATE TABLE competition_settings (
    competition_id INT UNSIGNED NOT NULL,
    setting_key    VARCHAR(64) NOT NULL,
    setting_value  TEXT NOT NULL,
    updated_by     INT UNSIGNED NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (competition_id, setting_key),
    CONSTRAINT fk_cs_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'âge propres à chaque compétition (copiées depuis les modèles de CategoryService::TEMPLATES ou une autre compétition).
-- Une compétition garde ses propres catégories : modifier le modèle ne change pas l'historique.
CREATE TABLE age_categories (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id          INT UNSIGNED NULL,
    code                    VARCHAR(20)  NOT NULL,
    name                    VARCHAR(80)  NOT NULL,
    birth_year_min          SMALLINT UNSIGNED NOT NULL,
    birth_year_max          SMALLINT UNSIGNED NOT NULL,
    upgrade_to_id           INT UNSIGNED NULL COMMENT 'Surclassement autorisé vers cette catégorie',
    max_participants        SMALLINT UNSIGNED NULL,
    sort_order              SMALLINT NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_age_cat_code (competition_id, code),
    KEY idx_age_cat_comp (competition_id, sort_order),
    CONSTRAINT fk_agecat_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_agecat_upgrade FOREIGN KEY (upgrade_to_id) REFERENCES age_categories (id) ON DELETE SET NULL,
    CONSTRAINT chk_agecat_years CHECK (birth_year_min <= birth_year_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tableaux ouverts dans une compétition : catégorie d'âge × sexe.
-- merge_group : exception explicite autorisant le regroupement de plusieurs tableaux dans les mêmes poules.
CREATE TABLE competition_categories (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id   INT UNSIGNED NOT NULL,
    age_category_id  INT UNSIGNED NOT NULL,
    sex              ENUM('M','F') NOT NULL,
    is_open          TINYINT(1) NOT NULL DEFAULT 1,
    merge_group      VARCHAR(40) NULL,
    max_participants SMALLINT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cc (competition_id, age_category_id, sex),
    CONSTRAINT fk_cc_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_age FOREIGN KEY (age_category_id) REFERENCES age_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weight_categories (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id   INT UNSIGNED NULL,
    age_category_id  INT UNSIGNED NOT NULL,
    sex              ENUM('M','F') NOT NULL,
    label            VARCHAR(20)  NOT NULL,
    min_weight_kg    DECIMAL(5,2) NULL,
    max_weight_kg    DECIMAL(5,2) NULL COMMENT 'NULL = catégorie ouverte (+)',
    max_participants SMALLINT UNSIGNED NULL,
    sort_order       SMALLINT NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_weight_cat (age_category_id, sex, label),
    KEY idx_weight_comp (competition_id),
    CONSTRAINT fk_wc_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_wc_age FOREIGN KEY (age_category_id) REFERENCES age_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Règles de durée de combat. Les valeurs de démonstration sont marquées is_example = 1 :
-- elles doivent être confirmées par l'organisateur selon le règlement retenu.
CREATE TABLE fight_duration_rules (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id       INT UNSIGNED NULL,
    age_category_id      INT UNSIGNED NULL COMMENT 'NULL = règle par défaut de la compétition',
    sex                  ENUM('M','F') NULL,
    phase                ENUM('all','pool','final') NOT NULL DEFAULT 'all',
    label                VARCHAR(120) NULL,
    duration_seconds     SMALLINT UNSIGNED NOT NULL,
    golden_score_mode    ENUM('none','limited','unlimited') NOT NULL DEFAULT 'unlimited',
    golden_score_seconds SMALLINT UNSIGNED NULL,
    recovery_seconds     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    overtime_rule        VARCHAR(255) NULL,
    priority             SMALLINT NOT NULL DEFAULT 0,
    valid_from           DATE NULL,
    valid_until          DATE NULL,
    is_example           TINYINT(1) NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fdr_lookup (competition_id, age_category_id, sex, phase),
    CONSTRAINT fk_fdr_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_fdr_age FOREIGN KEY (age_category_id) REFERENCES age_categories (id) ON DELETE CASCADE,
    CONSTRAINT chk_fdr_duration CHECK (duration_seconds > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tapis, commissaires, écrans TV
-- ---------------------------------------------------------------------------
CREATE TABLE tatamis (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id  INT UNSIGNED NOT NULL,
    number          TINYINT UNSIGNED NOT NULL,
    name            VARCHAR(60) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    current_bout_id INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tatami_number (competition_id, number),
    CONSTRAINT fk_tatami_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commissioner_tatamis (
    user_id     INT UNSIGNED NOT NULL,
    tatami_id   INT UNSIGNED NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tapis actif dans l\'interface de saisie',
    assigned_by INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, tatami_id),
    KEY idx_ct_tatami (tatami_id),
    CONSTRAINT fk_ct_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons d'affichage : seul le hachage SHA-256 est conservé. Lecture seule, révocables, à durée limitée.
CREATE TABLE tv_tokens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tatami_id    INT UNSIGNED NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    token_hint   CHAR(6)  NOT NULL,
    label        VARCHAR(80) NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    expires_at   DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    last_seen_ip VARCHAR(45) NULL,
    created_by   INT UNSIGNED NULL,
    revoked_at   DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tv_token (token_hash),
    KEY idx_tv_tatami (tatami_id, is_active),
    CONSTRAINT fk_tv_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE CASCADE,
    CONSTRAINT fk_tv_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Athlètes, inscriptions, présences
-- ---------------------------------------------------------------------------
CREATE TABLE athletes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    club_id        INT UNSIGNED NULL,
    last_name      VARCHAR(80) NOT NULL,
    first_name     VARCHAR(80) NOT NULL,
    sex            ENUM('M','F') NOT NULL,
    birth_date     DATE NOT NULL,
    grade          VARCHAR(40) NULL,
    license_number VARCHAR(40) NULL,
    is_demo        TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_athlete_identity (club_id, last_name, first_name, birth_date),
    KEY idx_athlete_name (last_name, first_name),
    CONSTRAINT fk_athlete_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registrations (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id          INT UNSIGNED NOT NULL,
    athlete_id              INT UNSIGNED NOT NULL,
    club_id                 INT UNSIGNED NULL,
    club_exception_label    VARCHAR(150) NULL COMMENT 'Athlète sans club enregistré, exception autorisée par l\'admin',
    age_category_id         INT UNSIGNED NOT NULL,
    natural_age_category_id INT UNSIGNED NULL,
    weight_category_id      INT UNSIGNED NULL,
    declared_weight_kg      DECIMAL(5,2) NULL,
    measured_weight_kg      DECIMAL(5,2) NULL,
    status                  ENUM('pending','confirmed','withdrawn','rejected') NOT NULL DEFAULT 'confirmed',
    attendance_status       ENUM('expected','present','absent','to_check') NOT NULL DEFAULT 'expected',
    attendance_at           DATETIME NULL,
    attendance_by           INT UNSIGNED NULL,
    is_late                 TINYINT(1) NOT NULL DEFAULT 0,
    is_onsite               TINYINT(1) NOT NULL DEFAULT 0,
    needs_validation        TINYINT(1) NOT NULL DEFAULT 0,
    validated_by            INT UNSIGNED NULL,
    validated_at            DATETIME NULL,
    admin_comment           VARCHAR(500) NULL,
    created_by              INT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registration (competition_id, athlete_id),
    KEY idx_reg_club (competition_id, club_id),
    KEY idx_reg_category (competition_id, age_category_id, weight_category_id),
    KEY idx_reg_attendance (competition_id, attendance_status),
    CONSTRAINT fk_reg_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reg_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_age FOREIGN KEY (age_category_id) REFERENCES age_categories (id),
    CONSTRAINT fk_reg_natural_age FOREIGN KEY (natural_age_category_id) REFERENCES age_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_weight FOREIGN KEY (weight_category_id) REFERENCES weight_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_att_by FOREIGN KEY (attendance_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_val_by FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_records (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id INT UNSIGNED NOT NULL,
    status          ENUM('expected','present','absent','to_check') NOT NULL,
    measured_weight_kg DECIMAL(5,2) NULL,
    note            VARCHAR(255) NULL,
    recorded_by     INT UNSIGNED NULL,
    recorded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_att_reg (registration_id, recorded_at),
    CONSTRAINT fk_att_reg FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE,
    CONSTRAINT fk_att_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Poules et combats
-- ---------------------------------------------------------------------------
CREATE TABLE pool_generations (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NOT NULL,
    status         ENUM('draft','validated','cancelled') NOT NULL DEFAULT 'draft',
    report_json    MEDIUMTEXT NULL,
    created_by     INT UNSIGNED NULL,
    validated_by   INT UNSIGNED NULL,
    validated_at   DATETIME NULL,
    cancelled_at   DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pg_comp (competition_id, status),
    CONSTRAINT fk_pg_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_pg_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_pg_validated FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pools (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id      INT UNSIGNED NOT NULL,
    generation_id       INT UNSIGNED NOT NULL,
    group_key           VARCHAR(120) NOT NULL COMMENT 'Groupe compatible (âge × sexe × poids éventuel)',
    age_category_id     INT UNSIGNED NOT NULL,
    sex                 ENUM('M','F') NOT NULL,
    weight_category_id  INT UNSIGNED NULL,
    number              SMALLINT UNSIGNED NOT NULL,
    label               VARCHAR(80) NOT NULL,
    tatami_id           INT UNSIGNED NULL,
    order_on_tatami     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status              ENUM('draft','validated','in_progress','finished') NOT NULL DEFAULT 'draft',
    is_locked           TINYINT(1) NOT NULL DEFAULT 0,
    weight_min_kg       DECIMAL(5,2) NULL,
    weight_max_kg       DECIMAL(5,2) NULL,
    estimated_seconds   INT UNSIGNED NOT NULL DEFAULT 0,
    needs_tiebreak      TINYINT(1) NOT NULL DEFAULT 0,
    ranking_complete    TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pool_number (generation_id, number),
    KEY idx_pool_comp (competition_id, status),
    KEY idx_pool_tatami (tatami_id, order_on_tatami),
    CONSTRAINT fk_pool_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_pool_gen FOREIGN KEY (generation_id) REFERENCES pool_generations (id) ON DELETE CASCADE,
    CONSTRAINT fk_pool_age FOREIGN KEY (age_category_id) REFERENCES age_categories (id),
    CONSTRAINT fk_pool_weight FOREIGN KEY (weight_category_id) REFERENCES weight_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_pool_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pool_athletes (
    pool_id               INT UNSIGNED NOT NULL,
    registration_id       INT UNSIGNED NOT NULL,
    generation_id         INT UNSIGNED NOT NULL,
    seed                  TINYINT UNSIGNED NOT NULL,
    reference_weight_kg   DECIMAL(5,2) NULL,
    wins                  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    losses                TINYINT UNSIGNED NOT NULL DEFAULT 0,
    value_points          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    computed_rank         TINYINT UNSIGNED NULL,
    manual_rank           TINYINT UNSIGNED NULL COMMENT 'Départage décidé par l\'administrateur',
    final_rank            TINYINT UNSIGNED NULL,
    excluded_from_ranking TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (pool_id, registration_id),
    UNIQUE KEY uq_pa_generation (generation_id, registration_id),
    UNIQUE KEY uq_pa_seed (pool_id, seed),
    CONSTRAINT fk_pa_pool FOREIGN KEY (pool_id) REFERENCES pools (id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_reg FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_pa_gen FOREIGN KEY (generation_id) REFERENCES pool_generations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Combats. Côté A = premier appelé (couleur rouge ou bleue selon la compétition), côté B = blanc.
-- L'état du chronomètre est la référence côté serveur : clock_elapsed_ms accumulé + (maintenant - clock_started_at) si en marche.
CREATE TABLE bouts (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id        INT UNSIGNED NOT NULL,
    pool_id               INT UNSIGNED NOT NULL,
    tatami_id             INT UNSIGNED NULL,
    order_in_pool         TINYINT UNSIGNED NOT NULL,
    order_on_tatami       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    phase                 ENUM('pool','final') NOT NULL DEFAULT 'pool',
    a_registration_id     INT UNSIGNED NOT NULL,
    b_registration_id     INT UNSIGNED NOT NULL,
    status                ENUM('scheduled','in_progress','finished','validated','cancelled') NOT NULL DEFAULT 'scheduled',
    duration_rule_id      INT UNSIGNED NULL,
    duration_seconds      SMALLINT UNSIGNED NOT NULL,
    golden_score_mode     ENUM('none','limited','unlimited') NOT NULL DEFAULT 'unlimited',
    golden_score_seconds  SMALLINT UNSIGNED NULL,
    recovery_seconds      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    clock_running         TINYINT(1) NOT NULL DEFAULT 0,
    clock_started_at_ms   BIGINT NULL,
    clock_elapsed_ms      INT UNSIGNED NOT NULL DEFAULT 0,
    is_golden_score       TINYINT(1) NOT NULL DEFAULT 0,
    osaekomi_side         ENUM('a','b') NULL,
    osaekomi_started_at_ms BIGINT NULL,
    a_ippon               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    a_wazaari             TINYINT UNSIGNED NOT NULL DEFAULT 0,
    a_yuko                TINYINT UNSIGNED NOT NULL DEFAULT 0,
    a_shido               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    a_hansoku             TINYINT(1) NOT NULL DEFAULT 0,
    b_ippon               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    b_wazaari             TINYINT UNSIGNED NOT NULL DEFAULT 0,
    b_yuko                TINYINT UNSIGNED NOT NULL DEFAULT 0,
    b_shido               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    b_hansoku             TINYINT(1) NOT NULL DEFAULT 0,
    winner_side           ENUM('a','b') NULL,
    win_method            ENUM('ippon','wazaari','yuko','decision','hansoku_make','fusen_gachi','kiken_gachi','shido') NULL,
    final_elapsed_ms      INT UNSIGNED NULL,
    started_at            DATETIME NULL,
    ended_at              DATETIME NULL,
    commissioner_id       INT UNSIGNED NULL,
    validated_by          INT UNSIGNED NULL,
    validated_at          DATETIME NULL,
    version               INT UNSIGNED NOT NULL DEFAULT 1,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_bout_pool_order (pool_id, order_in_pool),
    KEY idx_bout_tatami (tatami_id, status, order_on_tatami),
    KEY idx_bout_comp (competition_id, status),
    CONSTRAINT fk_bout_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_bout_pool FOREIGN KEY (pool_id) REFERENCES pools (id) ON DELETE CASCADE,
    CONSTRAINT fk_bout_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE SET NULL,
    CONSTRAINT fk_bout_a FOREIGN KEY (a_registration_id) REFERENCES registrations (id),
    CONSTRAINT fk_bout_b FOREIGN KEY (b_registration_id) REFERENCES registrations (id),
    CONSTRAINT fk_bout_rule FOREIGN KEY (duration_rule_id) REFERENCES fight_duration_rules (id) ON DELETE SET NULL,
    CONSTRAINT fk_bout_commissioner FOREIGN KEY (commissioner_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_bout_validator FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_bout_fighters CHECK (a_registration_id <> b_registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tatamis
    ADD CONSTRAINT fk_tatami_current_bout FOREIGN KEY (current_bout_id) REFERENCES bouts (id) ON DELETE SET NULL;

-- Historique des résultats validés : chaque validation ou correction crée une ligne ; une seule est « courante ».
CREATE TABLE bout_results (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bout_id                INT UNSIGNED NOT NULL,
    revision               SMALLINT UNSIGNED NOT NULL,
    is_current             TINYINT(1) NOT NULL DEFAULT 1,
    winner_registration_id INT UNSIGNED NULL,
    loser_registration_id  INT UNSIGNED NULL,
    winner_side            ENUM('a','b') NULL,
    win_method             VARCHAR(20) NULL,
    score_json             TEXT NOT NULL,
    kind                   ENUM('validation','correction') NOT NULL DEFAULT 'validation',
    reason                 VARCHAR(500) NULL,
    recorded_by            INT UNSIGNED NULL,
    recorded_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bout_revision (bout_id, revision),
    KEY idx_br_current (bout_id, is_current),
    CONSTRAINT fk_br_bout FOREIGN KEY (bout_id) REFERENCES bouts (id) ON DELETE CASCADE,
    CONSTRAINT fk_br_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Événements de saisie (points, pénalités, chronomètre). client_event_id rend chaque envoi idempotent
-- (un renvoi après perte de connexion n'est jamais compté deux fois).
CREATE TABLE score_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bout_id         INT UNSIGNED NOT NULL,
    client_event_id VARCHAR(64) NULL,
    action          VARCHAR(32) NOT NULL,
    side            ENUM('a','b') NULL,
    payload_json    VARCHAR(500) NULL,
    clock_ms        INT UNSIGNED NULL,
    user_id         INT UNSIGNED NULL,
    created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_score_client_event (bout_id, client_event_id),
    KEY idx_se_bout (bout_id, id),
    CONSTRAINT fk_se_bout FOREIGN KEY (bout_id) REFERENCES bouts (id) ON DELETE CASCADE,
    CONSTRAINT fk_se_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Classements
-- ---------------------------------------------------------------------------
-- Barème : points et médaille par place finale. Valeurs de démonstration, entièrement modifiables.
CREATE TABLE ranking_rules (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NOT NULL,
    place          TINYINT UNSIGNED NOT NULL,
    points         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    medal          ENUM('gold','silver','bronze') NULL,
    counts_in_table TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ranking_place (competition_id, place),
    CONSTRAINT fk_rr_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tables matérialisées, entièrement recalculables depuis les résultats validés (RankingService::recomputeCompetition).
CREATE TABLE athlete_rankings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id  INT UNSIGNED NOT NULL,
    pool_id         INT UNSIGNED NOT NULL,
    registration_id INT UNSIGNED NOT NULL,
    club_id         INT UNSIGNED NULL,
    place           TINYINT UNSIGNED NOT NULL,
    points          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    medal           ENUM('gold','silver','bronze') NULL,
    computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ar (competition_id, registration_id),
    KEY idx_ar_pool (pool_id),
    KEY idx_ar_club (competition_id, club_id),
    CONSTRAINT fk_ar_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_pool FOREIGN KEY (pool_id) REFERENCES pools (id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_reg FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE club_rankings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id  INT UNSIGNED NOT NULL,
    club_id         INT UNSIGNED NOT NULL,
    participants    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    points          INT UNSIGNED NOT NULL DEFAULT 0,
    gold            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    silver          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bronze          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fourth          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fifth           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    other_places    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    rank_points     SMALLINT UNSIGNED NULL,
    rank_medals     SMALLINT UNSIGNED NULL,
    computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cr (competition_id, club_id),
    CONSTRAINT fk_cr_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Buvette et boutique (registre, sans encaissement bancaire)
-- ---------------------------------------------------------------------------
CREATE TABLE products (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NULL COMMENT 'NULL = produit disponible pour toutes les compétitions',
    category       ENUM('buvette','boutique') NOT NULL DEFAULT 'buvette',
    name           VARCHAR(120) NOT NULL,
    price_cents    INT UNSIGNED NOT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    sort_order     SMALLINT NOT NULL DEFAULT 0,
    is_demo        TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_comp (competition_id, is_active),
    CONSTRAINT fk_products_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id   INT UNSIGNED NOT NULL,
    kind             ENUM('sale','refund') NOT NULL DEFAULT 'sale',
    refund_of_sale_id INT UNSIGNED NULL,
    payment_method   ENUM('cash','card','check','other') NOT NULL DEFAULT 'cash',
    total_cents      INT NOT NULL COMMENT 'Négatif pour un remboursement',
    note             VARCHAR(255) NULL,
    seller_id        INT UNSIGNED NULL,
    sold_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    client_ref       VARCHAR(64) NULL COMMENT 'Idempotence des envois depuis la caisse',
    is_demo          TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_client_ref (client_ref),
    KEY idx_sales_comp (competition_id, sold_at),
    CONSTRAINT fk_sales_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_refund FOREIGN KEY (refund_of_sale_id) REFERENCES sales (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id          INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NULL,
    product_name     VARCHAR(120) NOT NULL,
    category         ENUM('buvette','boutique') NOT NULL,
    unit_price_cents INT UNSIGNED NOT NULL,
    quantity         INT NOT NULL COMMENT 'Négatif pour un remboursement',
    line_total_cents INT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_si_sale (sale_id),
    KEY idx_si_product (product_id),
    CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Contenus du site vitrine, partenaires, notifications, journal d'audit
-- ---------------------------------------------------------------------------
CREATE TABLE site_contents (
    content_key VARCHAR(64) NOT NULL,
    content     TEXT NOT NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (content_key),
    CONSTRAINT fk_sc_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partners (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(120) NOT NULL,
    url              VARCHAR(255) NULL,
    logo_document_id INT UNSIGNED NULL,
    tier             VARCHAR(40) NULL,
    sort_order       SMALLINT NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    is_demo          TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_partner_logo FOREIGN KEY (logo_document_id) REFERENCES documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NULL,
    club_id        INT UNSIGNED NULL,
    user_id        INT UNSIGNED NULL,
    type           VARCHAR(40) NOT NULL,
    message        VARCHAR(500) NOT NULL,
    link           VARCHAR(255) NULL,
    read_at        DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_club (club_id, read_at),
    KEY idx_notif_user (user_id, read_at),
    CONSTRAINT fk_notif_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'audit : l'application n'expose ni modification ni suppression.
-- Chaque ligne est chaînée à la précédente (hash SHA-256) : toute altération directe en base est détectable
-- (App\Services\AuditLogger::verifyChain, page Admin > Journal).
CREATE TABLE audit_logs (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NULL,
    user_id        INT UNSIGNED NULL,
    user_label     VARCHAR(190) NULL,
    action         VARCHAR(64) NOT NULL,
    entity_type    VARCHAR(40) NULL,
    entity_id      VARCHAR(40) NULL,
    old_values     TEXT NULL,
    new_values     TEXT NULL,
    reason         VARCHAR(500) NULL,
    ip_address     VARCHAR(45) NULL,
    prev_hash      CHAR(64) NULL,
    row_hash       CHAR(64) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_comp (competition_id, id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('001_initial_schema.sql');

-- ======================================================================
-- 002_roles_permissions.sql
-- ======================================================================
-- JCL Compét — migration 002 : rôles et permissions de référence.
-- Les contrôles d'accès sont effectués côté serveur sur chaque route (middleware « can: ») et dans les services.

INSERT INTO roles (code, name, description) VALUES
 ('admin',        'Administrateur',      'Accès complet à toutes les compétitions et fonctionnalités'),
 ('coach',        'Club / entraîneur',   'Espace privé du club : athlètes, inscriptions, poules et résultats du club'),
 ('commissioner', 'Commissaire sportif', 'Saisie des combats sur le ou les tapis affectés'),
 ('reception',    'Accueil',             'Contrôle des présences le jour de la compétition'),
 ('seller',       'Vendeur',             'Enregistrement des ventes buvette et boutique');

INSERT INTO permissions (code, description) VALUES
 ('competitions.manage',  'Créer, modifier, paramétrer les compétitions'),
 ('clubs.manage',         'Gérer les clubs et leurs comptes'),
 ('users.manage',         'Gérer les comptes utilisateurs'),
 ('registrations.manage', 'Gérer toutes les inscriptions'),
 ('registrations.own',    'Gérer les inscriptions de son club'),
 ('club.self',            'Modifier les informations autorisées de son club'),
 ('attendance.manage',    'Contrôler les présences'),
 ('pools.manage',         'Générer et modifier les poules, répartir les tapis'),
 ('bouts.score',          'Saisir les combats de ses tapis'),
 ('results.correct',      'Corriger tout résultat validé'),
 ('rankings.manage',      'Configurer les barèmes et recalculer les classements'),
 ('sales.record',         'Enregistrer des ventes'),
 ('sales.manage',         'Gérer les produits et consulter les recettes'),
 ('content.manage',       'Gérer les contenus du site vitrine'),
 ('documents.manage',     'Gérer les documents et visuels'),
 ('tv.manage',            'Gérer les affichages TV'),
 ('audit.view',           'Consulter le journal d''activité'),
 ('archive.manage',       'Archiver et rouvrir les compétitions'),
 ('stats.view',           'Consulter les statistiques'),
 ('pdf.export',           'Générer les documents PDF');

-- Administrateur : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('registrations.own', 'club.self') WHERE r.code = 'coach';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('bouts.score') WHERE r.code = 'commissioner';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('attendance.manage') WHERE r.code = 'reception';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('sales.record') WHERE r.code = 'seller';

-- Contenus initiaux du site vitrine (modifiables dans Admin > Site vitrine)
INSERT INTO site_contents (content_key, content) VALUES
 ('club_name',        'JC Leforest'),
 ('club_tagline',     'Le judo à Leforest depuis 2000'),
 ('club_presentation','Club de judo de Leforest, le JC Leforest accueille judokas de tous âges, de l''éveil judo aux compétiteurs. Le club organise chaque saison des rencontres et tournois ouverts aux clubs de la région.'),
 ('club_address',     'Leforest (62)'),
 ('club_email',       'contact@exemple.fr'),
 ('club_phone',       ''),
 ('home_cta_title',   'Clubs, inscrivez vos judokas en ligne'),
 ('home_cta_text',    'Chaque club dispose d''un espace privé pour inscrire ses athlètes, suivre les poules publiées et consulter les résultats en direct.'),
 ('legal_notice',     'Éditeur du site : JC Leforest (association loi 1901). Directeur de la publication : le président du club. Hébergement : à compléter (ex. Hostinger International Ltd.).'),
 ('privacy_notice',   'Les données collectées (nom, prénom, sexe, date de naissance, club, catégorie, poids, grade et licence facultatifs) servent uniquement à l''organisation des compétitions et à la publication des résultats. Elles sont conservées le temps nécessaire à l''archivage sportif. Pour exercer vos droits, contactez le club organisateur.');

INSERT INTO schema_migrations (version) VALUES ('002_roles_permissions.sql');

-- ======================================================================
-- 003_site_content.sql
-- ======================================================================
-- JCL Compét — migration 003 : contenus éditables du site et du programme (Programme de la compétition amicale),
-- clubs présents, historique des éditions, catégories « animation » (sans combats), saisie directe de la catégorie de poids.

-- Clubs : département et liens (Facebook / site)
ALTER TABLE clubs
    ADD COLUMN department   VARCHAR(3)   NULL AFTER city,
    ADD COLUMN facebook_url VARCHAR(255) NULL AFTER phone,
    ADD COLUMN website_url  VARCHAR(255) NULL AFTER facebook_url;

-- Clubs présents à une compétition (liste publique « Merci aux clubs présents »), indépendante des inscriptions en ligne
CREATE TABLE competition_clubs (
    competition_id INT UNSIGNED NOT NULL,
    club_id        INT UNSIGNED NOT NULL,
    is_host        TINYINT(1) NOT NULL DEFAULT 0,
    sort_order     SMALLINT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (competition_id, club_id),
    CONSTRAINT fk_ccl_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ccl_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'âge : intitulés masculin / féminin et format (poules ou animation sans combats, médaille pour tous)
ALTER TABLE age_categories
    ADD COLUMN name_m VARCHAR(80) NULL AFTER name,
    ADD COLUMN name_f VARCHAR(80) NULL AFTER name_m,
    ADD COLUMN format ENUM('pools','animation') NOT NULL DEFAULT 'pools' AFTER name_f;

-- Documents : types libres (programme, plan de salle, encart publicitaire, photo…)
ALTER TABLE documents MODIFY COLUMN type VARCHAR(30) NOT NULL DEFAULT 'other';

-- Compétitions : intitulé d'édition
ALTER TABLE competitions ADD COLUMN edition_label VARCHAR(80) NULL AFTER name;

-- Buvette / boutique : rubriques libres (restauration, boissons, boutique, loterie…) et précision (contenance)
ALTER TABLE products
    MODIFY COLUMN category VARCHAR(30) NOT NULL DEFAULT 'restauration',
    ADD COLUMN description VARCHAR(255) NULL AFTER name,
    ADD COLUMN note VARCHAR(255) NULL AFTER description;
ALTER TABLE sale_items MODIFY COLUMN category VARCHAR(30) NOT NULL;

-- Partenaires : rattachement facultatif à une compétition, catégorie, description, encart publicitaire
ALTER TABLE partners
    ADD COLUMN competition_id  INT UNSIGNED NULL AFTER id,
    ADD COLUMN description     TEXT NULL AFTER url,
    ADD COLUMN ad_document_id  INT UNSIGNED NULL AFTER logo_document_id,
    MODIFY COLUMN tier VARCHAR(40) NOT NULL DEFAULT 'partner',
    ADD CONSTRAINT fk_partner_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_partner_ad FOREIGN KEY (ad_document_id) REFERENCES documents (id) ON DELETE SET NULL;

-- Sections de texte éditables (format Markdown simplifié). competition_id NULL = page du site (club, Taïso…)
CREATE TABLE content_sections (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id    INT UNSIGNED NULL,
    page_key          VARCHAR(40) NOT NULL,
    section_key       VARCHAR(60) NOT NULL,
    title             VARCHAR(190) NULL,
    body              MEDIUMTEXT NULL,
    image_document_id INT UNSIGNED NULL,
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    is_published      TINYINT(1) NOT NULL DEFAULT 1,
    updated_by        INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cs_page (competition_id, page_key, sort_order),
    CONSTRAINT fk_csec_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_csec_image FOREIGN KEY (image_document_id) REFERENCES documents (id) ON DELETE SET NULL,
    CONSTRAINT fk_csec_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listes éditables (horaires, lots de la loterie, staff, résultats, horaires des cours…)
CREATE TABLE content_items (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id    INT UNSIGNED NULL,
    list_key          VARCHAR(40) NOT NULL,
    group_label       VARCHAR(120) NULL,
    title             VARCHAR(255) NOT NULL,
    subtitle          VARCHAR(255) NULL,
    body              TEXT NULL,
    link_url          VARCHAR(255) NULL,
    image_document_id INT UNSIGNED NULL,
    is_highlight      TINYINT(1) NOT NULL DEFAULT 0,
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    is_published      TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ci_list (competition_id, list_key, sort_order),
    CONSTRAINT fk_ci_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_image FOREIGN KEY (image_document_id) REFERENCES documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des éditions (tableau « L'historique de notre compétition »)
CREATE TABLE competition_history (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id  INT UNSIGNED NULL COMMENT 'Édition gérée dans l''application, le cas échéant',
    season_label    VARCHAR(20) NOT NULL,
    clubs_count     SMALLINT UNSIGNED NULL,
    category_counts TEXT NULL COMMENT 'JSON : [{"label":"Poussin(e)s","count":115}, …]',
    total_participants SMALLINT UNSIGNED NULL,
    bouts_count     SMALLINT UNSIGNED NULL COMMENT 'NULL = non disponible (ND)',
    notes           VARCHAR(255) NULL,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_history_season (season_label),
    CONSTRAINT fk_hist_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscriptions : catégorie de poids choisie directement par le club (compétition sans pesée)
ALTER TABLE registrations ADD COLUMN weight_declared_by_category TINYINT(1) NOT NULL DEFAULT 0 AFTER weight_category_id;

INSERT INTO schema_migrations (version) VALUES ('003_site_content.sql');

-- ======================================================================
-- 004_photos.sql
-- ======================================================================
-- JCL Compét — migration 004 : photos des judokas (facultatives, privées) et galeries photos des compétitions.

-- Photo de profil facultative d'un athlète (document privé : visible uniquement des utilisateurs connectés).
ALTER TABLE athletes
    ADD COLUMN photo_document_id INT UNSIGNED NULL AFTER license_number,
    ADD CONSTRAINT fk_athlete_photo FOREIGN KEY (photo_document_id) REFERENCES documents (id) ON DELETE SET NULL;

-- Vignette générée à l'envoi (images), largeur et hauteur d'origine, ordre d'affichage (galeries).
ALTER TABLE documents
    ADD COLUMN thumb_name VARCHAR(80) NULL AFTER stored_name,
    ADD COLUMN width SMALLINT UNSIGNED NULL AFTER size_bytes,
    ADD COLUMN height SMALLINT UNSIGNED NULL AFTER width,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_public,
    ADD KEY idx_documents_gallery (type, competition_id, sort_order);

INSERT INTO schema_migrations (version) VALUES ('004_photos.sql');

-- ======================================================================
-- 005_fight_configurations.sql
-- ======================================================================
-- Configurations de combat par catégorie : en plus de la durée, chaque règle peut redéfinir
-- le barème (yuko, waza-ari pour ippon, shido pour hansoku), les seuils d'immobilisation
-- et des règles particulières (ex. clés de bras et étranglements interdits).
-- NULL = valeur des paramètres sportifs de la compétition.
ALTER TABLE fight_duration_rules
    ADD COLUMN use_yuko             TINYINT(1)       NULL AFTER overtime_rule,
    ADD COLUMN osaekomi_yuko_s      TINYINT UNSIGNED NULL AFTER use_yuko,
    ADD COLUMN osaekomi_wazaari_s   TINYINT UNSIGNED NULL AFTER osaekomi_yuko_s,
    ADD COLUMN osaekomi_ippon_s     TINYINT UNSIGNED NULL AFTER osaekomi_wazaari_s,
    ADD COLUMN wazaari_for_ippon    TINYINT UNSIGNED NULL AFTER osaekomi_ippon_s,
    ADD COLUMN shido_for_hansoku    TINYINT UNSIGNED NULL AFTER wazaari_for_ippon,
    ADD COLUMN golden_first_score_wins TINYINT(1)  NULL AFTER shido_for_hansoku,
    ADD COLUMN special_rules        TEXT             NULL AFTER golden_first_score_wins;

INSERT INTO schema_migrations (version) VALUES ('005_fight_configurations.sql');

-- ======================================================================
-- 006_bout_rules_snapshot.sql
-- ======================================================================
-- Chaque combat garde une copie de sa configuration (barème, immobilisations, règles particulières)
-- au moment de sa création : modifier une configuration ne change pas un combat en cours.
ALTER TABLE bouts ADD COLUMN rules_json JSON NULL AFTER recovery_seconds;

INSERT INTO schema_migrations (version) VALUES ('006_bout_rules_snapshot.sql');

-- ======================================================================
-- 007_club_colors.sql
-- ======================================================================
-- Couleur principale du club (tirée de son logo, modifiable) : cartes du sélecteur de club.
ALTER TABLE clubs ADD COLUMN brand_color CHAR(7) NULL AFTER logo_path;

INSERT INTO schema_migrations (version) VALUES ('007_club_colors.sql');

SET FOREIGN_KEY_CHECKS = 1;
