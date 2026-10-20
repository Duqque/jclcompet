<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'name' => Env::get('DB_NAME', 'jclcompet'),
    'user' => Env::get('DB_USER', 'jclcompet'),
    'password' => (string) Env::get('DB_PASSWORD', ''),
];
