<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\DomainError;

final class UserController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('users', [
            'title' => 'Comptes',
            'rows' => Database::all('SELECT u.*, r.name AS role_name, r.code AS role_code, c.name AS club_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN club_users cu ON cu.user_id = u.id LEFT JOIN clubs c ON c.id = cu.club_id ORDER BY r.id, u.display_name'),
            'roles' => Database::all('SELECT * FROM roles ORDER BY id'),
            'clubs' => Database::all('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function action(Request $r): Response
    {
        $op = $r->str('op');
        if ($op === 'create') {
            $email = mb_strtolower($r->str('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainError('Adresse e-mail invalide.');
            }
            if (Database::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
                throw new DomainError('Un compte existe déjà avec cette adresse.');
            }
            $role = Database::one('SELECT * FROM roles WHERE id = ?', [$r->int('role_id')]) ?? throw new DomainError('Rôle invalide.');
            $password = (string) $r->input('password', '');
            AuthController::assertStrong($password);
            $uid = Database::insert('users', [
                'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $r->str('display_name') ?: $email,
                'role_id' => (int) $role['id'], 'phone' => $r->str('phone') ?: null, 'must_change_password' => 1,
            ]);
            if ($role['code'] === 'coach') {
                $club = $r->int('club_id') ?? throw new DomainError('Un compte entraîneur doit être rattaché à un club.');
                Database::insert('club_users', ['club_id' => $club, 'user_id' => $uid]);
            }
            AuditLogger::log('user.create', 'user', $uid, null, ['email' => $email, 'role' => $role['code']]);
            return $this->back($r, 'Compte créé. Le mot de passe provisoire devra être changé à la première connexion.');
        }
        $uid = (int) $r->int('user_id');
        $target = Database::one('SELECT * FROM users WHERE id = ?', [$uid]) ?? throw new DomainError('Compte introuvable.');
        if ($uid === Auth::id() && in_array($op, ['toggle', 'role'], true)) {
            throw new DomainError('Vous ne pouvez pas désactiver ou rétrograder votre propre compte.');
        }
        switch ($op) {
            case 'toggle':
                Database::run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$uid]);
                AuditLogger::log('user.toggle', 'user', $uid, ['is_active' => $target['is_active']], ['is_active' => 1 - (int) $target['is_active']]);
                return $this->back($r, 'Compte mis à jour.');
            case 'reset_password':
                $password = (string) $r->input('password', '');
                AuthController::assertStrong($password);
                Database::run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $uid]);
                AuditLogger::log('user.reset_password', 'user', $uid);
                return $this->back($r, 'Mot de passe provisoire défini (changement imposé à la prochaine connexion).');
            case 'delete_demo':
                $n = Database::run('UPDATE users SET is_active = 0 WHERE is_demo = 1 AND id <> ?', [Auth::id()])->rowCount();
                AuditLogger::log('user.disable_demo', 'user', null, null, ['count' => $n]);
                return $this->back($r, "$n compte(s) de démonstration désactivé(s).");
        }
        throw new DomainError('Action inconnue.');
    }
}
