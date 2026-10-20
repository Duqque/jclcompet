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
