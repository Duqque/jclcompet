-- Tableaux à double repêchage (catégories de poids officielles France Judo) :
-- une catégorie d'âge peut être en « poules morphologiques » (pools), en « tableaux officiels » (brackets) ou en animation.
ALTER TABLE age_categories MODIFY format ENUM('pools','brackets','animation') NOT NULL DEFAULT 'pools';

-- Une « poule » peut être une poule (toutes rondes), un tableau à double repêchage (bracket)
-- ou le tableau final croisé de deux poules (final_block : 1er A – 2e B, 1er B – 2e A, finale).
ALTER TABLE pools
    ADD COLUMN kind ENUM('pool','bracket','final_block') NOT NULL DEFAULT 'pool' AFTER label,
    ADD COLUMN bracket_size SMALLINT UNSIGNED NULL AFTER kind;

-- Position dans le tableau (seed = case du tirage, 1..taille) et tête de série (1..8).
ALTER TABLE pool_athletes
    MODIFY seed SMALLINT UNSIGNED NOT NULL,
    ADD COLUMN draw_seed TINYINT UNSIGNED NULL AFTER seed;

-- Combats de tableau : clé de position (M2-1 = 2e tour combat 2, R0-1 = repêchage du quart 1, RF0, B0, M4-0 = finale…)
-- et intitulé lisible (« Quart de finale », « Repêchage », « Place de 3e », « Finale »).
ALTER TABLE bouts
    ADD COLUMN bracket_key VARCHAR(20) NULL AFTER phase,
    ADD COLUMN stage_label VARCHAR(60) NULL AFTER bracket_key,
    ADD UNIQUE KEY uq_bouts_bracket (pool_id, bracket_key);
