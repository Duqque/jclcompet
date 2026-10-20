-- Chaque combat garde une copie de sa configuration (barème, immobilisations, règles particulières)
-- au moment de sa création : modifier une configuration ne change pas un combat en cours.
ALTER TABLE bouts ADD COLUMN rules_json JSON NULL AFTER recovery_seconds;
