<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\DomainError;

final class AuthController extends Controller
{
    public function showLogin(Request $r): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::homePath());
        }
        return $this->view('auth/login', ['title' => 'Connexion'], 'auth');
    }

    public function login(Request $r): Response
    {
        $email = $r->str('email');
        $password = (string) $r->input('password', '');
        if ($email === '' || $password === '') {
            Session::flashInput(['email' => $email]);
            return $this->redirect('/connexion', 'Saisissez votre adresse e-mail et votre mot de passe.', 'error');
        }
        $result = Auth::attempt($email, $password, $r);
        if (!$result['ok']) {
            Session::flashInput(['email' => $email]);
            return $this->redirect('/connexion', $result['error'], 'error');
        }
        $intended = Session::get('_intended');
        Session::forget('_intended');
        $target = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && !str_starts_with($intended, '/api/') ? $intended : Auth::homePath();
        return Response::redirect($target);
    }

    public function logout(Request $r): Response
    {
        Auth::logout();
        Session::start($r);
        return $this->redirect('/connexion', 'Vous êtes déconnecté(e).');
    }

    public function showPassword(Request $r): Response
    {
        return $this->view('auth/password', ['title' => 'Mot de passe'], 'auth');
    }

    public function updatePassword(Request $r): Response
    {
        $user = Database::one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $current = (string) $r->input('current_password', '');
        $new = (string) $r->input('password', '');
        $confirm = (string) $r->input('password_confirmation', '');
        if (!password_verify($current, $user['password_hash'])) {
            throw new DomainError('Mot de passe actuel incorrect.');
        }
        self::assertStrong($new, $confirm);
        if (password_verify($new, $user['password_hash'])) {
            throw new DomainError('Le nouveau mot de passe doit être différent de l\'actuel.');
        }
        Database::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        AuditLogger::log('auth.password_change', 'user', (int) $user['id']);
        Auth::login((int) $user['id']);
        return $this->redirect(Auth::homePath(), 'Mot de passe modifié.');
    }

    public static function assertStrong(string $password, ?string $confirm = null): void
    {
        if (mb_strlen($password) < 12) {
            throw new DomainError('Le mot de passe doit contenir au moins 12 caractères.');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new DomainError('Le mot de passe doit contenir des lettres et des chiffres.');
        }
        if ($confirm !== null && $password !== $confirm) {
            throw new DomainError('Les deux mots de passe ne correspondent pas.');
        }
    }
}
