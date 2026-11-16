<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Jetons de connexion de l'application JCL Live. Le jeton brut n'est remis qu'une fois au téléphone
 * (qui le garde dans le trousseau iOS) ; seule son empreinte SHA-256 est enregistrée.
 */
final class ApiTokenService
{
    public const DAYS = 90;

    public static function issue(int $userId, ?string $device = null): string
    {
        $raw = bin2hex(random_bytes(32));
        Database::insert('api_tokens', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $raw),
            'device_name' => $device !== null ? mb_substr(trim($device), 0, 120) : null,
            'expires_at' => date('Y-m-d H:i:s', time() + self::DAYS * 86400),
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
        return $raw;
    }

    /** Identifiant de l'utilisateur d'un en-tête « Bearer … » valide, sinon null. Le jeton est prolongé à l'usage. */
    public static function resolve(string $header): ?int
    {
        $raw = self::extract($header);
        if ($raw === null) {
            return null;
        }
        $row = Database::one('SELECT id, user_id, last_used_at FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()', [hash('sha256', $raw)]);
        if (!$row) {
            return null;
        }
        if (!$row['last_used_at'] || strtotime($row['last_used_at']) < time() - 600) {
            Database::run('UPDATE api_tokens SET last_used_at = NOW(), expires_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() + self::DAYS * 86400), $row['id']]);
        }
        return (int) $row['user_id'];
    }

    public static function revoke(string $header): void
    {
        if ($raw = self::extract($header)) {
            Database::run('UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = ?', [hash('sha256', $raw)]);
        }
    }

    public static function revokeAll(int $userId): void
    {
        Database::run('UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
    }

    private static function extract(string $header): ?string
    {
        return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $m) ? strtolower($m[1]) : null;
    }
}
