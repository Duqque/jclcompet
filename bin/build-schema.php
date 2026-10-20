<?php
declare(strict_types=1);

// Génère database/schema.sql (installation complète sur une base vide) à partir des migrations versionnées.
// Usage : php bin/build-schema.php
define('BASE_PATH', dirname(__DIR__));
$files = glob(BASE_PATH . '/database/migrations/*.sql');
sort($files, SORT_STRING);
$out = "-- JCL Compét — schéma complet (généré par bin/build-schema.php à partir des migrations, ne pas modifier à la main)\n"
     . "-- Installation : mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/schema.sql\n"
     . "-- Compatible MySQL 8.0+ (et MariaDB 10.6+).\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n"
     . "CREATE TABLE IF NOT EXISTS schema_migrations (\n    version VARCHAR(190) NOT NULL PRIMARY KEY,\n    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
foreach ($files as $f) {
    $out .= "-- ======================================================================\n-- " . basename($f) . "\n-- ======================================================================\n";
    $out .= trim((string) file_get_contents($f)) . "\n\nINSERT INTO schema_migrations (version) VALUES ('" . basename($f) . "');\n\n";
}
$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
file_put_contents(BASE_PATH . '/database/schema.sql', $out);
echo 'database/schema.sql généré (' . count($files) . " migrations).\n";
