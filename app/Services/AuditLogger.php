<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

/**
 * Journal d'audit en ajout seul. Chaque ligne porte le hachage HMAC de son contenu et du hachage précédent :
 * une modification ou suppression directe en base casse la chaîne et est signalée par verifyChain().
 */
final class AuditLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        mixed $old = null,
        mixed $new = null,
        ?string $reason = null,
        ?int $competitionId = null,
    ): void {
        Database::transaction(function () use ($action, $entityType, $entityId, $old, $new, $reason, $competitionId) {
            $prev = Database::value('SELECT row_hash FROM audit_logs ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $user = Auth::user();
            $row = [
                'competition_id' => $competitionId,
                'user_id' => $user['id'] ?? null,
                'user_label' => $user ? $user['display_name'] . ' <' . $user['email'] . '>' : (PHP_SAPI === 'cli' ? 'cli' : null),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
                'reason' => $reason === null ? null : mb_substr($reason, 0, 500),
                'ip_address' => PHP_SAPI === 'cli' ? 'cli' : ($_SERVER['REMOTE_ADDR'] ?? null),
                'prev_hash' => $prev ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $row['row_hash'] = self::hash($row);
            Database::insert('audit_logs', $row);
        });
    }

    private static function hash(array $row): string
    {
        $fields = ['competition_id', 'user_id', 'user_label', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'reason', 'ip_address', 'prev_hash', 'created_at'];
        $payload = implode("\x1f", array_map(fn ($f) => (string) ($row[$f] ?? ''), $fields));
        return hash_hmac('sha256', $payload, (string) Config::get('app.audit_secret', 'jclcompet'));
    }

    /** @return array{ok: bool, checked: int, broken_at: ?int} */
    public static function verifyChain(): array
    {
        $prev = null;
        $checked = 0;
        $stmt = Database::run('SELECT * FROM audit_logs ORDER BY id');
        while ($row = $stmt->fetch()) {
            $checked++;
            if (($row['prev_hash'] ?? null) !== $prev || !hash_equals(self::hash($row), (string) $row['row_hash'])) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row['id']];
            }
            $prev = $row['row_hash'];
        }
        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }
}
