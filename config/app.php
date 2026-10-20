<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'JCL Compét'),
    // local | demo | production. En production, les comptes de démonstration sont refusés à la connexion
    // et les scripts de données fictives / de réinitialisation refusent de s'exécuter.
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8095'), '/'),
    'base_path' => Env::get('APP_BASE_PATH', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Paris'),
    'session_name' => Env::get('SESSION_NAME', 'jclc_session'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 43200),
    'force_secure_cookies' => (bool) Env::get('FORCE_SECURE_COOKIES', false),
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_window_minutes' => (int) Env::get('LOGIN_WINDOW_MINUTES', 15),
    'audit_secret' => (string) Env::get('AUDIT_SECRET', ''),
    'tv_token_days' => (int) Env::get('TV_TOKEN_DAYS', 7),
    'upload_max_bytes' => (int) Env::get('UPLOAD_MAX_BYTES', 8 * 1024 * 1024),
    'organizer' => [
        'name' => Env::get('ORGANIZER_NAME', 'JC Leforest'),
        'logo' => Env::get('ORGANIZER_LOGO', 'img/logo-jcl.png'),
    ],
];
