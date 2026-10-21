# Installer la démo sur Hostinger en SSH

Résultat : un site de démonstration complet, par exemple `https://demo.votre-domaine.fr`, avec une centaine de combattants
fictifs dans une compétition en cours (poules, combats en direct, classements, podiums), une compétition à venir dont les
inscriptions sont ouvertes, et tous les comptes de démonstration. Testé de bout en bout sur MySQL 8.4 et MariaDB.

Remplacer partout `u123456789`, `votre-domaine.fr` et `demo` par vos valeurs.

## 1. Préparer l'hébergement (hPanel)

1. **Sous-domaine** : Domaines › Sous-domaines › créer `demo` (dossier proposé : `public_html/demo`).
2. **PHP** : Avancé › Configuration PHP › version **8.2 ou plus** (8.3 conseillé) ; extensions `pdo_mysql`, `mbstring`,
   `fileinfo`, `gd`, `intl` cochées.
3. **Base de données** : Bases de données › Gestion › créer une base et un utilisateur (ex. `u123456789_demo`), noter le mot
   de passe. Hôte : `localhost`.
4. **SSH** : Avancé › Accès SSH › Activer. Noter l'IP, le port (souvent `65002`) et l'utilisateur (`u123456789`).
5. **SSL** : Sécurité › SSL › installer le certificat gratuit sur `demo.votre-domaine.fr`.

## 2. Installer (dans le terminal)

```bash
ssh -p 65002 u123456789@IP_DU_SERVEUR
```

```bash
cd ~/domains/votre-domaine.fr/public_html/demo
rm -f index.html default.php                      # fichiers d'accueil créés par Hostinger, s'il y en a
git clone https://github.com/Duqque/jclcompet.git .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"    # copier la ligne affichée pour AUDIT_SECRET
nano .env
```

Dans `.env`, renseigner (Ctrl+O pour enregistrer, Ctrl+X pour quitter) :

```ini
APP_ENV=demo
APP_DEBUG=false
APP_URL=https://demo.votre-domaine.fr
DB_HOST=localhost
DB_NAME=u123456789_demo
DB_USER=u123456789_demo
DB_PASSWORD=le-mot-de-passe-de-la-base
FORCE_SECURE_COOKIES=true
AUDIT_SECRET=la-ligne-copiee-plus-haut
```

Puis, en une commande (vérifications, tables, contenu du programme 2026, données de démonstration) :

```bash
php bin/install.php --demo
```

Le script affiche les comptes de démonstration, le mot de passe de l'album photos et les liens des écrans TV.
Si une vérification échoue, il dit quoi corriger (version de PHP, extension, `.env`, connexion à la base, droits sur `storage/`).

- `php -v` doit afficher 8.2 ou plus. Sinon, utiliser le binaire de la bonne version, par exemple
  `/opt/alt/php83/usr/bin/php bin/install.php --demo` (et de même pour composer :
  `/opt/alt/php83/usr/bin/php $(which composer) install --no-dev`).
- Le fichier `.htaccess` à la racine envoie toutes les adresses vers `public/` et bloque `app/`, `storage/`, `vendor/`, `.env`…
  Vérifier : `https://demo.votre-domaine.fr/.env` doit répondre « 403 ».

## 3. Comptes de démonstration

| Espace | Identifiant | Mot de passe |
|---|---|---|
| Administration | admin@demo-judo.test | AdminDemo2026! |
| Coach (JC Lensois) | coach@demo-judo.test | CoachDemo2026! |
| Coach (Hénin Judo) | coach2@demo-judo.test | CoachDemo2026! |
| Commissaire tapis 1 | commissaire1@demo-judo.test | CommissaireDemo2026! |
| Commissaire tapis 2 | commissaire2@demo-judo.test | CommissaireDemo2026! |
| Accueil | accueil@demo-judo.test | AccueilDemo2026! |
| Buvette | buvette@demo-judo.test | VendeurDemo2026! |

Ce sont des comptes publics de démonstration : ils ne fonctionnent que si `APP_ENV=demo` (ils sont bloqués en production).

À essayer :
- **Compétition en cours** (« Tournoi de démonstration JCL ») : ~100 combattants sur 6 tapis, poules en cours, combats à saisir,
  inscription sur place à l'accueil, classement en direct `/classement/tournoi-de-demonstration-jcl-donnees-fictives-…`.
- **Compétition à venir** (« Tournoi d'hiver de démonstration ») : inscriptions ouvertes, le coach peut ajouter des combattants
  et valider sa liste.

## 4. Remettre la démo à neuf chaque nuit (conseillé)

Les visiteurs modifient les données ; une tâche planifiée les régénère (dates recalées sur le jour même).
hPanel › Avancé › **Tâches cron** › personnalisée, tous les jours à 4 h :

```bash
cd /home/u123456789/domains/votre-domaine.fr/public_html/demo && php bin/install.php --demo --reset > /dev/null 2>&1
```

La réinitialisation n'est possible qu'avec `APP_ENV=demo` (refusée en production).

## 5. Mettre à jour la démo

```bash
cd ~/domains/votre-domaine.fr/public_html/demo
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/install.php --demo --reset
```

## Site réel (production) au lieu de la démo

Même procédure dans un autre dossier ou sous-domaine, avec `APP_ENV=production` dans `.env`, puis :

```bash
php bin/install.php --comptes        # tables + programme 2026 + comptes de config/comptes.json (mots de passe affichés une fois)
```

Ne jamais lancer `--demo` sur le site réel.
