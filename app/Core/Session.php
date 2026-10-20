<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        $secure = (bool) Config::get('app.force_secure_cookies', false) || $request->isSecure();
        session_name((string) Config::get('app.session_name', 'jclc_session'));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) Config::get('app.session_lifetime', 43200));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => rtrim((string) Config::get('app.base_path', ''), '/') . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expiration après inactivité prolongée.
        $now = time();
        $last = $_SESSION['_last_activity'] ?? $now;
        if ($now - $last > (int) Config::get('app.session_lifetime', 43200)) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf']);
        $_SESSION['_old'] = $input;
    }

    public static function pullOld(): array
    {
        $o = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $o;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function pullErrors(): array
    {
        $e = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $e;
    }
}
