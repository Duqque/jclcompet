<?php
declare(strict_types=1);

// Les tests d'intégration utilisent une base dédiée (DB_NAME=jclcompet_test, voir phpunit.xml.dist),
// recréée entièrement au démarrage ; chaque test s'exécute dans une transaction annulée à la fin.
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

if (Config::get('database.name') !== 'jclcompet_test') {
    fwrite(STDERR, "Refus : les tests doivent utiliser la base jclcompet_test.\n");
    exit(1);
}
$_SESSION = [];
$pdo = Database::pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (Database::column('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $t) {
    $pdo->exec("DROP TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
(new App\Services\Migrator())->migrate();
