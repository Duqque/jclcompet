# Installation, configuration, sauvegarde

## 1. Prérequis

| Élément | Version | Remarque |
|---|---|---|
| PHP | 8.2 ou supérieure | extensions `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `json`, `dom` (Dompdf), `gd` conseillée |
| Serveur web | Apache 2.4 avec `mod_rewrite` | `AllowOverride All` sur le dossier `public/` (fichiers `.htaccess` fournis) |
| Base | MySQL 8.0+ (ou MariaDB 10.6+) | encodage `utf8mb4` |
| Composer | 2.x | pour installer Dompdf ; disponible en SSH sur Hostinger |

Aucun Node.js, Docker ni serveur WebSocket n'est nécessaire.

## 2. Variables de configuration (`.env`)

Le fichier `.env` est placé à la racine du projet (hors du dossier public, jamais versionné). Modèle : `.env.example`.

| Variable | Exemple | Rôle |
|---|---|---|
| `APP_NAME` | `JCL Compét` | Nom affiché |
| `APP_ENV` | `production` | `local`, `demo` ou `production`. En production : comptes de démonstration refusés, seed de démo et réinitialisation interdits |
| `APP_DEBUG` | `false` | `true` uniquement en local (affiche le détail des erreurs) |
| `APP_URL` | `https://compet.jcleforest.fr` | Adresse publique sans barre finale (liens TV, liens absolus) |
| `APP_BASE_PATH` | *(vide)* ou `/compet` | Sous-dossier si l'application n'est pas à la racine du domaine |
| `APP_TIMEZONE` | `Europe/Paris` | Fuseau horaire |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | | Accès MySQL |
| `SESSION_NAME` | `jclc_session` | Nom du cookie de session |
| `SESSION_LIFETIME` | `43200` | Déconnexion après 12 h d'inactivité (une journée de compétition) |
| `FORCE_SECURE_COOKIES` | `true` | Cookies `Secure` + en-tête HSTS : **obligatoire en HTTPS** |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_MINUTES` | `5`, `15` | Limitation des tentatives de connexion (par identifiant, et ×4 par adresse IP) |
| `AUDIT_SECRET` | 64 caractères aléatoires | Clé du chaînage du journal d'audit : `php -r "echo bin2hex(random_bytes(32));"`. **Ne plus la changer** ensuite |
| `TV_TOKEN_DAYS` | `7` | Validité par défaut d'un lien d'écran TV |
| `UPLOAD_MAX_BYTES` | `8388608` | Taille maximale d'un document téléversé |
| `ORGANIZER_NAME`, `ORGANIZER_LOGO` | `JC Leforest`, `img/logo-jcl.png` | Organisateur (bandeau des écrans TV) |

## 3. Installation sur Hostinger (hébergement mutualisé / Business)

1. **hPanel › Avancé › Configuration PHP** : choisir PHP **8.2 ou plus** ; vérifier que `intl`, `fileinfo`, `pdo_mysql` sont cochées.
2. **hPanel › Bases de données › Gestion** : créer une base MySQL et son utilisateur (noter nom, utilisateur, mot de passe ;
   l'hôte est `localhost`).
3. **Déposer le code** (SSH conseillé : hPanel › Avancé › Accès SSH) :
   ```bash
   cd ~/domains/VOTRE-DOMAINE
   git clone https://github.com/Duqque/jclcompet.git jclcompet
   cd jclcompet
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && nano .env      # renseigner la base, APP_URL, AUDIT_SECRET, APP_ENV=production
   ```
   Sans SSH : envoyer le dossier par le gestionnaire de fichiers (y compris `vendor/`, préparé en local avec
   `composer install --no-dev`).
4. **Racine du site** — deux possibilités :
   - *(recommandé)* faire pointer le domaine ou le sous-domaine sur `jclcompet/public` : remplacer le dossier
     `public_html` par un lien symbolique : `rm -rf ~/domains/VOTRE-DOMAINE/public_html && ln -s ~/domains/VOTRE-DOMAINE/jclcompet/public ~/domains/VOTRE-DOMAINE/public_html` ;
   - *(sinon)* placer tout le projet dans `public_html` : le `.htaccess` racine fourni redirige vers `public/` et bloque
     l'accès à `app/`, `config/`, `database/`, `storage/`, `vendor/`, `.env`, etc.
5. **Créer les tables** : `php bin/migrate.php` (SSH) — ou importer `database/schema.sql` dans **phpMyAdmin**.
6. **Contenu du club et du programme** : `php bin/seed.php --content` (club, Taïso, édition 2026, clubs, partenaires…).
   Ne **pas** lancer `--demo` sur le site public.
7. **Premier administrateur** : `php bin/create-admin.php presidente@exemple.fr "Marie-Paule Kowalski"` (mot de passe saisi
   de façon masquée, 12 caractères minimum).
8. **HTTPS** : hPanel › Sécurité › SSL (Let's Encrypt gratuit), puis `FORCE_SECURE_COOKIES=true`.
9. **Droits d'écriture** : `storage/` (logs, documents, cache) doit être accessible en écriture par PHP (`chmod -R 775 storage`).
10. Se connecter sur `/connexion`, puis vérifier : Administration › Compétitions, Site vitrine, Comptes (aucun compte « démo » actif).

### Mise à jour

```bash
cd ~/domains/VOTRE-DOMAINE/jclcompet
bin/backup.sh                      # sauvegarde avant mise à jour
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/migrate.php                # applique les nouvelles migrations (les données sont conservées)
```

### Performance et charge

Chaque écran TV interroge le serveur environ une fois par seconde (réglable par compétition) ; une requête TV fait quelques
requêtes SQL indexées. 6 écrans + 6 commissaires + l'accueil représentent ~15 requêtes/s, compatibles avec un hébergement
mutualisé. En cas de lenteur : passer le rafraîchissement TV à 1 500–2 000 ms (Paramètres sportifs › Planning et affichages).

## 4. Installation locale (macOS, Homebrew)

```bash
brew install php mysql@8.4 httpd composer
/opt/homebrew/opt/mysql@8.4/bin/mysqld --initialize-insecure --datadir=/opt/homebrew/var/mysql-8.4
/opt/homebrew/opt/mysql@8.4/bin/mysqld --datadir=/opt/homebrew/var/mysql-8.4 --port=3306 --socket=/tmp/mysql84.sock &
mysql -uroot -S /tmp/mysql84.sock -e "CREATE DATABASE jclcompet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE jclcompet_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'jclcompet'@'localhost' IDENTIFIED BY 'mot-de-passe-local';
  GRANT ALL ON jclcompet.* TO 'jclcompet'@'localhost'; GRANT ALL ON jclcompet_test.* TO 'jclcompet'@'localhost';"
composer install && cp .env.example .env   # APP_ENV=local, APP_DEBUG=true, FORCE_SECURE_COOKIES=false
php bin/reset-dev.php --yes                # tables + contenu du programme + démonstration
```

Configuration Apache minimale (port 8095) :

```apache
Listen 127.0.0.1:8095
LoadModule rewrite_module lib/httpd/modules/mod_rewrite.so
LoadModule php_module /opt/homebrew/opt/php/lib/httpd/modules/libphp.so
DocumentRoot "/chemin/vers/jclcompet/public"
<Directory "/chemin/vers/jclcompet/public">
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
DirectoryIndex index.php
```

## 5. Sauvegarde et restauration

**À sauvegarder** : la base MySQL **et** le dossier `storage/documents` (règlements, logos, photos, encarts).

- Automatique (SSH) : `bin/backup.sh` crée `storage/backups/base-AAAAMMJJ-HHMMSS.sql.gz` et `documents-….tar.gz`.
  Copier ces fichiers hors du serveur. Conseillé : avant chaque compétition, le soir de la compétition, avant chaque mise à jour.
- Hostinger : hPanel › Fichiers › Sauvegardes (sauvegardes quotidiennes/hebdomadaires selon l'offre) et phpMyAdmin › Exporter.
- Restauration : `bin/restore.sh storage/backups/base-….sql.gz storage/backups/documents-….tar.gz` (confirmation demandée),
  ou import du fichier `.sql` dans phpMyAdmin puis copie du dossier `documents`.
- Après restauration : Administration › Journal d'activité › « Vérifier l'intégrité du journal ».

## 6. Avant la mise en production — liste de vérification

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_SECURE_COOKIES=true`, HTTPS actif
- [ ] `AUDIT_SECRET` aléatoire défini (et conservé)
- [ ] `.env`, `storage/`, `app/` inaccessibles depuis le navigateur (tester `https://domaine/.env` → refus)
- [ ] Aucun compte de démonstration actif (Administration › Comptes) ; seed `--demo` non exécuté
- [ ] Mots de passe provisoires changés (imposé à la première connexion des comptes créés par l'administrateur)
- [ ] Mentions légales et hébergeur complétés (Administration › Site vitrine › Coordonnées et textes)
- [ ] Sauvegarde testée (création **et** restauration sur une base de test)
