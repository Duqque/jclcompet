<?php
declare(strict_types=1);

// RÉINITIALISATION COMPLÈTE — réservée au développement (APP_ENV=local).
// Supprime toutes les tables, réapplique les migrations, importe le contenu du programme et les données de démonstration.
// Usage : php bin/reset-dev.php [--yes] [--no-demo]
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];

use App\Core\Config;
use App\Core\Database;

if (Config::get('app.env') !== 'local') {
    fwrite(STDERR, "Refusé : la réinitialisation n'est autorisée qu'avec APP_ENV=local.\n");
    exit(1);
}
if (!in_array('--yes', $argv, true)) {
    echo 'Toutes les données de la base « ' . Config::get('database.name') . " » seront effacées. Taper « oui » pour confirmer : ";
    if (trim((string) fgets(STDIN)) !== 'oui') {
        echo "Annulé.\n";
        exit(1);
    }
}
$pdo = Database::pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (Database::column('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $table) {
    $pdo->exec("DROP TABLE `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
foreach (glob(BASE_PATH . '/storage/documents/*') as $f) {
    if (basename($f) !== '.gitkeep') {
        unlink($f);
    }
}
echo "Base vidée.\n";
passthru(PHP_BINARY . ' ' . escapeshellarg(BASE_PATH . '/bin/migrate.php'));
passthru(PHP_BINARY . ' ' . escapeshellarg(BASE_PATH . '/bin/seed.php') . ' --content' . (in_array('--no-demo', $argv, true) ? '' : ' --demo'), $code);
exit($code);
