<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitation des tentatives de connexion, persistée en base (fonctionne même avec plusieurs processus PHP).
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $identifier, string $ip): bool
    {
        $window = (int) Config::get('app.login_window_minutes', 15);
        $maxId = (int) Config::get('app.login_max_attempts', 5);
        $maxIp = $maxId * 4;
        $since = date('Y-m-d H:i:s', time() - $window * 60);
        $byId = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND success = 0 AND attempted_at >= ?',
            [mb_strtolower($identifier), $since]
        );
        $byIp = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= ?',
            [$ip, $since]
        );
        return $byId >= $maxId || $byIp >= $maxIp;
    }

    public static function hit(string $identifier, string $ip, bool $success): void
    {
        Database::insert('login_attempts', [
            'identifier' => mb_strtolower($identifier),
            'ip_address' => $ip,
            'success' => $success ? 1 : 0,
        ]);
        if ($success) {
            Database::run('DELETE FROM login_attempts WHERE identifier = ? AND success = 0', [mb_strtolower($identifier)]);
        }
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }
}
