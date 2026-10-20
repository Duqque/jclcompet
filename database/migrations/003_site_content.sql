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
