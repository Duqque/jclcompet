<?php
declare(strict_types=1);

// Applique les migrations SQL versionnées (database/migrations/NNN_nom.sql) non encore appliquées.
// Usage : php bin/migrate.php [--status]
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Migrator;

$migrator = new Migrator();
if (in_array('--status', $argv, true)) {
    foreach ($migrator->status() as $file => $applied) {
        printf("%s %s\n", $applied ? '[x]' : '[ ]', $file);
    }
    exit(0);
}
$applied = $migrator->migrate();
echo $applied ? 'Migrations appliquées : ' . implode(', ', $applied) . PHP_EOL : 'Base à jour, aucune migration à appliquer.' . PHP_EOL;
