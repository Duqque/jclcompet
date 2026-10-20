<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public static function notifyAdmins(?int $competitionId, string $type, string $message, ?string $link = null): void
    {
        $admins = Database::column("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.is_active = 1");
        foreach ($admins as $uid) {
            Database::insert('notifications', ['competition_id' => $competitionId, 'user_id' => (int) $uid, 'type' => $type, 'message' => mb_substr($message, 0, 500), 'link' => $link]);
        }
    }

    public static function notifyClub(int $clubId, ?int $competitionId, string $type, string $message, ?string $link = null): void
    {
        Database::insert('notifications', ['competition_id' => $competitionId, 'club_id' => $clubId, 'type' => $type, 'message' => mb_substr($message, 0, 500), 'link' => $link]);
    }

    public static function forUser(array $user, int $limit = 10): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE (user_id = ? OR (club_id IS NOT NULL AND club_id = ?)) ORDER BY id DESC LIMIT ' . (int) $limit,
            [(int) $user['id'], (int) ($user['club_id'] ?? 0)]
        );
    }

    public static function unreadCount(array $user): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND (user_id = ? OR (club_id IS NOT NULL AND club_id = ?))',
            [(int) $user['id'], (int) ($user['club_id'] ?? 0)]
        );
    }

    public static function markAllRead(array $user): void
    {
        Database::run(
            'UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL AND (user_id = ? OR (club_id IS NOT NULL AND club_id = ?))',
            [(int) $user['id'], (int) ($user['club_id'] ?? 0)]
        );
    }
}
