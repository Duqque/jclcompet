<?php
declare(strict_types=1);

// Installation en une commande (SSH, ex. Hostinger) : vérifications, tables, contenu du programme, puis
//   --demo     données de démonstration fictives (exige APP_ENV=demo dans .env ; refusé en production)
//   --reset    avec --demo : efface tout et régénère la démonstration (site de démo uniquement)
//   --comptes  crée les comptes listés dans config/comptes.json (mots de passe affichés une seule fois)
// Usage : php bin/install.php [--demo [--reset]] [--comptes]
$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$ok = fn (string $m) => print("  \u{2713} $m\n");
$fail = function (string $m) {
    fwrite(STDERR, "  \u{2717} $m\n");
    exit(1);
};

echo "JCL Compét — installation\n\n1. Vérifications\n";
version_compare(PHP_VERSION, '8.2.0', '>=') ? $ok('PHP ' . PHP_VERSION) : $fail('PHP 8.2 ou plus requis (actuel : ' . PHP_VERSION . '). Sur Hostinger : hPanel › Avancé › Configuration PHP, puis rouvrir la session SSH.');
foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'gd'] as $ext) {
    extension_loaded($ext) ? $ok("extension $ext") : $fail("extension PHP « $ext » manquante (hPanel › Configuration PHP › Extensions).");
}
is_file("$root/vendor/autoload.php") ? $ok('bibliothèques (vendor/)') : $fail('vendor/ absent : lancer « composer install --no-dev --optimize-autoloader ».');
is_file("$root/.env") ? $ok('fichier .env') : $fail('.env absent : « cp .env.example .env » puis le compléter (base, APP_URL, APP_ENV, AUDIT_SECRET).');

require $root . '/app/bootstrap.php';
$_SESSION = [];

use App\Core\Config;
use App\Core\Database;

$env = (string) Config::get('app.env');
$ok("APP_ENV=$env");
if (in_array('--demo', $args, true) && $env !== 'demo' && $env !== 'local') {
    $fail('--demo exige APP_ENV=demo dans .env (les comptes de démonstration sont bloqués en production).');
}
if (strlen((string) Config::get('app.audit_secret')) < 32) {
    $fail('AUDIT_SECRET vide ou trop court dans .env : php -r "echo bin2hex(random_bytes(32));" puis coller le résultat.');
}
$ok('AUDIT_SECRET défini');
try {
    Database::value('SELECT 1');
    $ok('connexion à la base « ' . Config::get('database.name') . ' » (' . Database::value('SELECT VERSION()') . ')');
} catch (Throwable $e) {
    $fail('connexion à la base impossible : ' . $e->getMessage() . ' — vérifier DB_HOST (localhost), DB_NAME, DB_USER, DB_PASSWORD.');
}
foreach (['storage/logs', 'storage/documents', 'storage/cache', 'storage/exports', 'storage/uploads', 'storage/backups'] as $dir) {
    @mkdir("$root/$dir", 0775, true);
    is_writable("$root/$dir") ? null : $fail("$dir n'est pas accessible en écriture : chmod -R 775 storage");
}
$ok('storage/ accessible en écriture');

$php = escapeshellarg(PHP_BINARY);
$run = function (string $cmd) use ($fail) {
    passthru($cmd, $code);
    if ($code !== 0) {
        $fail('étape interrompue (code ' . $code . ').');
    }
};

if (in_array('--demo', $args, true) && in_array('--reset', $args, true)) {
    echo "\n2. Réinitialisation complète de la démonstration\n";
    $run("$php " . escapeshellarg("$root/bin/reset-dev.php") . ' --yes');
} else {
    echo "\n2. Tables\n";
    $run("$php " . escapeshellarg("$root/bin/migrate.php"));
    echo "\n3. Contenu du programme (club, Taïso, édition 2026, clubs, partenaires)\n";
    $run("$php " . escapeshellarg("$root/bin/seed.php") . ' --content');
    if (in_array('--demo', $args, true)) {
        echo "\n4. Données de démonstration\n";
        if (Database::value("SELECT 1 FROM users WHERE email = 'admin@demo-judo.test'")) {
            echo "  Déjà présentes. Pour tout régénérer : php bin/install.php --demo --reset\n";
        } else {
            $run("$php " . escapeshellarg("$root/bin/seed.php") . ' --demo');
        }
    }
}
if (in_array('--comptes', $args, true)) {
    echo "\nComptes de config/comptes.json\n";
    $run("$php " . escapeshellarg("$root/bin/create-user.php") . ' --fichier=' . escapeshellarg("$root/config/comptes.json"));
}

$url = rtrim((string) Config::get('app.url'), '/');
echo "\nInstallation terminée.\n  Site : $url/\n  Connexion : $url/connexion\n";
if ($env === 'demo') {
    echo "  Démonstration : remise à neuf chaque nuit possible avec une tâche cron « php bin/install.php --demo --reset ».\n";
}
