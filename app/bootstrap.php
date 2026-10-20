<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

// Autoload Composer (Dompdf) si présent, sinon chargeur PSR-4 minimal : l'application fonctionne sans vendor/
// (seule la génération PDF native nécessite Dompdf ; une version HTML imprimable est proposée à défaut).
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        foreach (['App\\' => '/app/', 'Database\\Seeds\\' => '/database/seeds/'] as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = BASE_PATH . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
}
require_once BASE_PATH . '/app/helpers.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

if (!Config::get('app.debug')) {
    ini_set('display_errors', '0');
}

// Erreurs fatales (hors exceptions) : journalisées, page générique sans détail technique.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        App\Core\Logger::error(sprintf('%s in %s:%d', $err['message'], $err['file'], $err['line']));
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><p style="font-family:sans-serif;padding:2rem">Une erreur est survenue. L\'incident a été enregistré.</p>';
        }
    }
});
