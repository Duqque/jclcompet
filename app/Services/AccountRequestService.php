<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\Database;

/**
 * Création de compte coach depuis le site ou l'application. La demande crée un compte inactif rattaché à
 * aucun club : il ne donne accès à rien tant que l'organisation ne l'a pas accepté (compte activé et relié
 * au club choisi, ou à un nouveau club créé à partir des informations saisies).
 */
final class AccountRequestService
{
    private const MAX_PER_IP_PER_HOUR = 5;

    /** @return int identifiant de la demande */
    public static function create(array $d, string $source, string $ip): int
    {
        $first = RegistrationService::normalizeFirstName(trim((string) ($d['first_name'] ?? '')));
        $last = RegistrationService::normalizeLastName(trim((string) ($d['last_name'] ?? '')));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $phone = trim((string) ($d['phone'] ?? ''));
        $password = (string) ($d['password'] ?? '');
        $clubId = (int) ($d['club_id'] ?? 0) ?: null;
        $clubName = trim((string) ($d['club_name'] ?? ''));
        $clubCity = trim((string) ($d['club_city'] ?? ''));
        $message = trim((string) ($d['message'] ?? ''));

        $errors = [];
        if ($first === '' || $last === '') {
            $errors['last_name'] = 'Indiquez votre nom et votre prénom.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if ($phone === '' || !preg_match('/^[0-9 +().-]{8,20}$/', $phone)) {
            $errors['phone'] = 'Indiquez un numéro de téléphone joignable (l\'organisation peut vous appeler pour vérifier).';
        }
        if ($clubId === null && $clubName === '') {
            $errors['club_id'] = 'Choisissez votre club, ou indiquez son nom s\'il n\'est pas dans la liste.';
        }
        if ($clubId !== null && !Database::value('SELECT 1 FROM clubs WHERE id = ? AND is_active = 1', [$clubId])) {
            $errors['club_id'] = 'Club introuvable.';
        }
        if ($errors) {
            throw new \App\Core\ValidationException($errors, reset($errors));
        }
        AuthController::assertStrong($password, array_key_exists('password_confirmation', $d) ? (string) $d['password_confirmation'] : null);
        if ((int) Database::value('SELECT COUNT(*) FROM account_requests WHERE ip_address = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip]) >= self::MAX_PER_IP_PER_HOUR) {
            throw new DomainError('Trop de demandes depuis cette connexion. Réessayez dans une heure.', 429);
        }
        if (Database::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            throw new DomainError('Un compte existe déjà avec cette adresse e-mail : connectez-vous, ou contactez l\'organisation si vous avez oublié votre mot de passe.');
        }

        $roleId = (int) Database::value("SELECT id FROM roles WHERE code = 'coach'");
        $id = Database::transaction(function () use ($first, $last, $email, $phone, $password, $roleId, $clubId, $clubName, $clubCity, $message, $source, $ip) {
            $uid = Database::insert('users', [
                'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $first . ' ' . $last, 'role_id' => $roleId, 'phone' => mb_substr($phone, 0, 40), 'is_active' => 0,
            ]);
            return Database::insert('account_requests', [
                'user_id' => $uid, 'club_id' => $clubId,
                'club_name' => $clubId ? null : mb_substr($clubName, 0, 150), 'club_city' => $clubId ? null : (mb_substr($clubCity, 0, 120) ?: null),
                'message' => $message !== '' ? mb_substr($message, 0, 500) : null, 'source' => $source === 'app' ? 'app' : 'web', 'ip_address' => $ip,
            ]);
        });
        $club = $clubId ? (string) Database::value('SELECT name FROM clubs WHERE id = ?', [$clubId]) : $clubName . ' (nouveau club)';
        NotificationService::notifyAdmins(null, 'account_request', "Demande de compte coach : $first $last — $club" . ($source === 'app' ? ' (application)' : '') . '.', '/admin/utilisateurs#demandes');
        AuditLogger::log('account_request.create', 'account_request', $id, null, ['email' => $email, 'club' => $club, 'source' => $source]);
        return $id;
    }

    public static function pending(): array
    {
        return Database::all(
            "SELECT ar.*, u.email, u.display_name, u.phone, c.name AS club_label, c.short_name AS club_short, c.logo_path AS club_logo, c.city AS club_city_known
             FROM account_requests ar JOIN users u ON u.id = ar.user_id LEFT JOIN clubs c ON c.id = ar.club_id
             WHERE ar.status = 'pending' ORDER BY ar.created_at"
        );
    }

    /** Accepte la demande : compte activé et rattaché au club choisi (ou au nouveau club créé). */
    public static function approve(int $id, ?int $clubId = null): array
    {
        $req = Database::one("SELECT * FROM account_requests WHERE id = ? AND status = 'pending'", [$id]) ?? throw new DomainError('Demande introuvable ou déjà traitée.');
        $clubId ??= $req['club_id'] ? (int) $req['club_id'] : null;
        return Database::transaction(function () use ($req, $clubId) {
            if ($clubId === null) {
                $name = trim((string) $req['club_name']);
                $existing = Database::value('SELECT id FROM clubs WHERE name = ? OR short_name = ?', [$name, $name]);
                $coach = Database::one('SELECT display_name, email, phone FROM users WHERE id = ?', [$req['user_id']]);
                $clubId = $existing ? (int) $existing : Database::insert('clubs', ['name' => $name, 'city' => $req['club_city'], 'country' => 'FR', 'is_active' => 1,
                    'contact_name' => $coach['display_name'], 'email' => $coach['email'], 'phone' => $coach['phone']]);
            } elseif (!Database::value('SELECT 1 FROM clubs WHERE id = ?', [$clubId])) {
                throw new DomainError('Club introuvable.');
            }
            Database::run('UPDATE users SET is_active = 1 WHERE id = ?', [$req['user_id']]);
            Database::run('DELETE FROM club_users WHERE user_id = ?', [$req['user_id']]);
            Database::insert('club_users', ['club_id' => $clubId, 'user_id' => (int) $req['user_id']]);
            Database::run("UPDATE account_requests SET status = 'approved', club_id = ?, decided_by = ?, decided_at = NOW() WHERE id = ?", [$clubId, Auth::id(), $req['id']]);
            AuditLogger::log('account_request.approve', 'account_request', (int) $req['id'], null, ['club_id' => $clubId]);
            return ['user_id' => (int) $req['user_id'], 'club_id' => $clubId];
        });
    }

    public static function reject(int $id, ?string $note = null): void
    {
        $req = Database::one("SELECT * FROM account_requests WHERE id = ? AND status = 'pending'", [$id]) ?? throw new DomainError('Demande introuvable ou déjà traitée.');
        Database::run("UPDATE account_requests SET status = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?", [Auth::id(), $note ? mb_substr($note, 0, 255) : null, $id]);
        ApiTokenService::revokeAll((int) $req['user_id']);
        AuditLogger::log('account_request.reject', 'account_request', $id, null, null, $note);
    }

    /** Clubs proposés au choix (actifs, avec couleur et logo). */
    public static function clubs(): array
    {
        return ClubColorService::decorate(Database::all('SELECT id, name, short_name, logo_path, city, brand_color FROM clubs WHERE is_active = 1 ORDER BY name'));
    }
}
