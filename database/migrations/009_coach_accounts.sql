-- Création de compte par les coachs (site et application JCL Live) : la demande crée un compte inactif,
-- l'organisation l'accepte (compte activé et rattaché au club) ou la refuse.
CREATE TABLE account_requests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    club_id       INT UNSIGNED NULL,
    club_name     VARCHAR(150) NULL,
    club_city     VARCHAR(120) NULL,
    message       VARCHAR(500) NULL,
    source        ENUM('web','app') NOT NULL DEFAULT 'web',
    ip_address    VARCHAR(45) NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    decided_by    INT UNSIGNED NULL,
    decided_at    DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ar_status (status),
    KEY idx_ar_ip (ip_address, created_at),
    CONSTRAINT fk_areq_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_areq_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL,
    CONSTRAINT fk_areq_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de connexion de l'application (seule l'empreinte SHA-256 est enregistrée).
CREATE TABLE api_tokens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    device_name  VARCHAR(120) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_token (token_hash),
    KEY idx_api_user (user_id),
    CONSTRAINT fk_api_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
