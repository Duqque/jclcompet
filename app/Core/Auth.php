<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    private static array $permissions = [];

    public static function attempt(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        if (RateLimiter::tooManyAttempts($email, $request->ip())) {
            return ['ok' => false, 'error' => 'Trop de tentatives. Patientez quelques minutes avant de réessayer.'];
        }
        $user = Database::one(
            'SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            [$email]
        );
        // password_verify est toujours exécuté pour ne pas révéler l'existence d'un compte par le temps de réponse.
        $hash = $user['password_hash'] ?? '$2y$12$0000000000000000000000.000000000000000000000000000000';
        $valid = password_verify($password, $hash);
        $blockedDemo = $user && (int) $user['is_demo'] === 1 && Config::get('app.env') === 'production';
        if (!$user || !$valid || (int) $user['is_active'] !== 1 || $blockedDemo) {
            RateLimiter::hit($email, $request->ip(), false);
            $msg = $blockedDemo ? 'Les comptes de démonstration sont désactivés en production.' : 'Identifiant ou mot de passe incorrect.';
            return ['ok' => false, 'error' => $msg];
        }
        RateLimiter::hit($email, $request->ip(), true);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        self::login((int) $user['id']);
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        \App\Services\AuditLogger::log('auth.login', 'user', (int) $user['id']);
        return ['ok' => true, 'user' => self::user()];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        Session::put('_csrf', bin2hex(random_bytes(32)));
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        if (self::check()) {
            \App\Services\AuditLogger::log('auth.logout', 'user', self::id());
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
        self::$permissions = [];
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id) {
                $user = Database::one(
                    'SELECT u.id, u.email, u.display_name, u.role_id, u.is_active, u.is_demo, u.must_change_password,
                            r.code AS role_code, r.name AS role_name, cu.club_id
                     FROM users u JOIN roles r ON r.id = u.role_id
                     LEFT JOIN club_users cu ON cu.user_id = u.id
                     WHERE u.id = ?',
                    [(int) $id]
                );
                if ($user && (int) $user['is_active'] === 1) {
                    self::$user = $user;
                    self::$permissions = Database::column(
                        'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
                        [(int) $user['role_id']]
                    );
                } else {
                    Session::forget('user_id');
                }
            }
        }
        return self::$user;
    }

    /** Utilisé par les tests et les scripts CLI. */
    public static function actAs(?int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$loaded = false;
        self::$user = null;
        self::$permissions = [];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::$user['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role_code'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function can(string $permission): bool
    {
        self::user();
        return in_array($permission, self::$permissions, true);
    }

    public static function clubId(): ?int
    {
        $c = self::user()['club_id'] ?? null;
        return $c === null ? null : (int) $c;
    }

    public static function authorize(string $permission): void
    {
        if (!self::check()) {
            throw new HttpException(401);
        }
        if (!self::can($permission)) {
            throw new HttpException(403);
        }
    }

    public static function homePath(): string
    {
        return match (self::role()) {
            'admin' => '/admin',
            'coach' => '/espace-club',
            'commissioner' => '/commissaire',
            'reception' => '/accueil',
            'seller' => '/caisse',
            default => '/',
        };
    }
}
