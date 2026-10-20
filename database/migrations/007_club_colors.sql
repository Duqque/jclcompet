-- Couleur principale du club (tirée de son logo, modifiable) : cartes du sélecteur de club.
ALTER TABLE clubs ADD COLUMN brand_color CHAR(7) NULL AFTER logo_path;
