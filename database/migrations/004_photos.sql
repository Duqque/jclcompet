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
