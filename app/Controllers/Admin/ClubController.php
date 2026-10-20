<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use App\Services\DomainError;

final class ClubController extends AdminController
{
    private const RULES = [
        'name' => 'required|maxlen:150', 'short_name' => 'nullable|maxlen:40', 'city' => 'nullable|maxlen:120', 'department' => 'nullable|maxlen:3',
        'country' => 'required|maxlen:2', 'contact_name' => 'nullable|maxlen:120', 'email' => 'nullable|email|maxlen:190', 'phone' => 'nullable|maxlen:40',
        'facebook_url' => 'nullable|maxlen:255', 'website_url' => 'nullable|maxlen:255', 'is_active' => 'bool',
    ];

    public function index(Request $r): Response
    {
        $q = trim((string) ($r->query['q'] ?? ''));
        $rows = Database::all(
            'SELECT c.*, (SELECT COUNT(*) FROM club_users cu WHERE cu.club_id = c.id) AS accounts, (SELECT COUNT(*) FROM athletes a WHERE a.club_id = c.id) AS athletes
             FROM clubs c WHERE (? = "" OR c.name LIKE ? OR c.city LIKE ?) ORDER BY c.is_active DESC, c.name',
            [$q, "%$q%", "%$q%"]
        );
        return $this->admin('clubs/index', ['title' => 'Clubs', 'rows' => $rows, 'q' => $q]);
    }

    public function create(Request $r): Response
    {
        return $this->admin('clubs/form', ['title' => 'Nouveau club', 'club' => null, 'accounts' => []]);
    }

    private function clean(array $post): array
    {
        $d = Validator::validate($post + ['country' => 'FR'], self::RULES, ['name' => 'Nom', 'email' => 'E-mail', 'country' => 'Pays']);
        foreach (['facebook_url', 'website_url'] as $k) {
            if ($d[$k] && !preg_match('#^https?://#i', $d[$k])) {
                throw new DomainError('Les liens doivent commencer par https://');
            }
        }
        $d['country'] = strtoupper($d['country']);
        foreach (['city', 'contact_name', 'email'] as $k) {
            $d[$k] ??= '';
        }
        return $d;
    }

    public function store(Request $r): Response
    {
        $d = $this->clean($r->post);
        if (Database::value('SELECT 1 FROM clubs WHERE name = ? AND city = ?', [$d['name'], $d['city']])) {
            throw new DomainError('Un club portant ce nom existe déjà dans cette ville.');
        }
        $id = Database::insert('clubs', $d);
        $this->logo($r, $id);
        AuditLogger::log('club.create', 'club', $id, null, ['name' => $d['name']]);
        return $this->redirect('/admin/clubs/' . $id, 'Club créé. Ajoutez maintenant un compte entraîneur.');
    }

    public function edit(Request $r): Response
    {
        $club = Database::one('SELECT * FROM clubs WHERE id = ?', [(int) $r->param('cid')]) ?? throw new DomainError('Club introuvable.', 404);
        $accounts = Database::all('SELECT u.* FROM club_users cu JOIN users u ON u.id = cu.user_id WHERE cu.club_id = ? ORDER BY u.display_name', [$club['id']]);
        return $this->admin('clubs/form', ['title' => $club['name'], 'club' => $club, 'accounts' => $accounts]);
    }

    public function update(Request $r): Response
    {
        $id = (int) $r->param('cid');
        $old = Database::one('SELECT * FROM clubs WHERE id = ?', [$id]) ?? throw new DomainError('Club introuvable.', 404);
        $d = $this->clean($r->post);
        Database::update('clubs', $d, 'id = :id', ['id' => $id]);
        $this->logo($r, $id);
        AuditLogger::log('club.update', 'club', $id, array_intersect_key($old, $d), $d);
        return $this->back($r, 'Club enregistré.');
    }

    private function logo(Request $r, int $clubId): void
    {
        if ($f = $r->file('logo')) {
            $doc = DocumentService::storeUpload($f, 'club_logo', 'Logo club ' . $clubId);
            Database::run('UPDATE clubs SET logo_path = ? WHERE id = ?', ['media/' . $doc, $clubId]);
        }
        if ($r->bool('remove_logo')) {
            Database::run('UPDATE clubs SET logo_path = NULL WHERE id = ?', [$clubId]);
        }
    }

    public function action(Request $r): Response
    {
        $clubId = (int) $r->param('cid');
        switch ($r->str('op')) {
            case 'account_create':
                $email = mb_strtolower($r->str('email'));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new DomainError('Adresse e-mail invalide.');
                }
                if (Database::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
                    throw new DomainError('Un compte existe déjà avec cette adresse.');
                }
                $password = (string) $r->input('password', '');
                AuthController::assertStrong($password);
                $uid = Database::insert('users', [
                    'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $r->str('display_name') ?: $email,
                    'role_id' => (int) Database::value("SELECT id FROM roles WHERE code = 'coach'"), 'must_change_password' => 1,
                ]);
                Database::insert('club_users', ['club_id' => $clubId, 'user_id' => $uid]);
                AuditLogger::log('club.account_create', 'user', $uid, null, ['club_id' => $clubId, 'email' => $email]);
                return $this->back($r, 'Compte entraîneur créé. Transmettez le mot de passe provisoire : il devra être changé à la première connexion.');
            case 'account_toggle':
                $uid = (int) $r->int('user_id');
                Database::run('UPDATE users u JOIN club_users cu ON cu.user_id = u.id SET u.is_active = 1 - u.is_active WHERE u.id = ? AND cu.club_id = ?', [$uid, $clubId]);
                AuditLogger::log('club.account_toggle', 'user', $uid);
                return $this->back($r, 'Compte mis à jour.');
        }
        throw new DomainError('Action inconnue.');
    }
}
