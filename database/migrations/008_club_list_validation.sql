-- Validation de la liste d'un club par son entraîneur : confirme automatiquement ses inscriptions
-- et prévient l'administration.
CREATE TABLE club_list_validations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id      INT UNSIGNED NOT NULL,
    club_id             INT UNSIGNED NOT NULL,
    validated_by        INT UNSIGNED NULL,
    validated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrations_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clv (competition_id, club_id),
    CONSTRAINT fk_clv_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_clv_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
    CONSTRAINT fk_clv_user FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
