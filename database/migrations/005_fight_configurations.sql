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
