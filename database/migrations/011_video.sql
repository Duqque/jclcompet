-- Vidéo : régie par tapis (2 caméras), vidéo-arbitrage (VAR) et diffusion YouTube Live.
-- La vidéo elle-même ne passe jamais par ce serveur : la régie (navigateur du PC du tapis) capte, enregistre en mémoire
-- et compose l'image ; OBS l'envoie à YouTube. Ici : état partagé (VAR en cours), repères de diffusion, appairage caméras.

INSERT INTO roles (code, name, description) VALUES
 ('video', 'Vidéo-arbitrage', 'Régie vidéo des tapis : caméras, diffusion en direct, retours vidéo et ralentis');

INSERT INTO permissions (code, description) VALUES
 ('video.operate', 'Piloter la régie vidéo et la vidéo-arbitrage des tapis');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'video.operate' WHERE r.code IN ('admin', 'video');

-- Diffusion YouTube du tapis et état de la vidéo-arbitrage
ALTER TABLE tatamis
    ADD COLUMN youtube_video_id VARCHAR(20) NULL AFTER current_bout_id,
    ADD COLUMN stream_started_at_ms BIGINT NULL COMMENT 'Début du direct YouTube (repère 0:00 des replays)' AFTER youtube_video_id,
    ADD COLUMN stream_offset_s SMALLINT NOT NULL DEFAULT 0 COMMENT 'Ajustement des replays (secondes)' AFTER stream_started_at_ms,
    ADD COLUMN var_active TINYINT(1) NOT NULL DEFAULT 0 AFTER stream_offset_s,
    ADD COLUMN var_started_at_ms BIGINT NULL AFTER var_active,
    ADD COLUMN var_on_air TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ralenti diffusé à l''antenne' AFTER var_started_at_ms,
    ADD COLUMN video_seen_at DATETIME NULL COMMENT 'Dernier signe de vie de la régie' AFTER var_on_air;

-- Début réel du combat à la milliseconde (repère des replays et de la VAR)
ALTER TABLE bouts
    ADD COLUMN started_at_ms BIGINT NULL AFTER started_at,
    ADD COLUMN ended_at_ms BIGINT NULL AFTER ended_at;

-- Caméras téléphone / iPad reliées à la régie (WebRTC, appairage par code court, sans compte sur le téléphone)
CREATE TABLE video_cameras (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tatami_id    INT UNSIGNED NOT NULL,
    slot         TINYINT UNSIGNED NOT NULL COMMENT '1 = côté table, 2 = côté opposé',
    code_hash    CHAR(64) NOT NULL,
    offer_sdp    MEDIUMTEXT NULL,
    answer_sdp   MEDIUMTEXT NULL,
    session      CHAR(16) NULL COMMENT 'Tentative de connexion en cours',
    device_label VARCHAR(120) NULL,
    expires_at   DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_video_camera_code (code_hash),
    UNIQUE KEY uq_video_camera_slot (tatami_id, slot),
    CONSTRAINT fk_vc_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE CASCADE,
    CONSTRAINT fk_vc_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instant de chaque action du commissaire (horloge serveur, ms) : repères de la VAR (waza-ari, shido…)
ALTER TABLE score_events
    ADD COLUMN at_ms BIGINT NULL AFTER clock_ms;

-- Directs YouTube successifs d'un tapis (un direct relancé = une nouvelle ligne) : chaque combat retrouve sa vidéo
CREATE TABLE video_streams (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tatami_id        INT UNSIGNED NOT NULL,
    youtube_video_id VARCHAR(20) NOT NULL,
    started_at_ms    BIGINT NOT NULL,
    ended_at_ms      BIGINT NULL,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vs_tatami (tatami_id, started_at_ms),
    CONSTRAINT fk_vs_tatami FOREIGN KEY (tatami_id) REFERENCES tatamis (id) ON DELETE CASCADE,
    CONSTRAINT fk_vs_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
