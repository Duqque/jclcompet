<?php
declare(strict_types=1);

namespace App\Controllers\Club;

use App\Controllers\Admin\RegistrationController;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\DocumentService;
use App\Services\DomainError;
use App\Services\NotificationService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\RegistrationService;
use App\Services\SettingsService;

/** Espace privé d'un club : chaque requête est limitée au club de l'utilisateur (isolation des données). */
final class ClubSpaceController extends Controller
{
    private function club(): array
    {
        $id = Auth::clubId();
        if (!$id) {
            throw new HttpException(403, 'Votre compte n\'est rattaché à aucun club.');
        }
        return Database::one('SELECT * FROM clubs WHERE id = ?', [$id]);
    }

    private function comp(Request $r): array
    {
        $c = CompetitionService::find((int) $r->param('id'));
        if ($c['status'] === 'draft') {
            throw new HttpException(404, 'Compétition introuvable.');
        }
        return $c;
    }

    private function render(string $tpl, array $data): Response
    {
        $club = $this->club();
        return $this->view('club/' . $tpl, $data + ['club' => $club, 'notifications' => NotificationService::forUser(Auth::user(), 5)], 'club');
    }

    public function dashboard(Request $r): Response
    {
        $club = $this->club();
        $comps = Database::all(
            "SELECT c.*, (SELECT COUNT(*) FROM registrations r WHERE r.competition_id = c.id AND r.club_id = ? AND r.status IN ('pending','confirmed')) AS my_regs
             FROM competitions c WHERE c.status NOT IN ('draft') ORDER BY c.status = 'archived', c.event_date DESC",
            [$club['id']]
        );
        foreach ($comps as &$c) {
            $c['window_open'] = CompetitionService::registrationWindowOpen($c);
        }
        unset($c);
        return $this->render('dashboard', ['title' => 'Espace club', 'competitions' => $comps]);
    }

    public function profile(Request $r): Response
    {
        return $this->render('profile', ['title' => 'Mon club']);
    }

    /** Champs autorisés pour le club : contact, téléphone, e-mail, liens, logo. Le nom et la ville restent gérés par l'organisation. */
    public function updateProfile(Request $r): Response
    {
        $club = $this->club();
        $d = Validator::validate($r->post, [
            'contact_name' => 'required|maxlen:120', 'email' => 'required|email|maxlen:190', 'phone' => 'nullable|maxlen:40',
            'facebook_url' => 'nullable|maxlen:255', 'website_url' => 'nullable|maxlen:255',
        ], ['contact_name' => 'Nom du contact', 'email' => 'E-mail']);
        foreach (['facebook_url', 'website_url'] as $k) {
            if ($d[$k] && !preg_match('#^https?://#i', $d[$k])) {
                throw new DomainError('Les liens doivent commencer par https://');
            }
        }
        Database::update('clubs', $d, 'id = :id', ['id' => $club['id']]);
        if ($f = $r->file('logo')) {
            $doc = DocumentService::storeUpload($f, 'club_logo', 'Logo ' . $club['name']);
            Database::run('UPDATE clubs SET logo_path = ? WHERE id = ?', ['media/' . $doc, $club['id']]);
        }
        AuditLogger::log('club.self_update', 'club', (int) $club['id'], array_intersect_key($club, $d), $d);
        return $this->redirect('/espace-club/profil', 'Informations du club enregistrées.');
    }

    public function competition(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $cid = (int) $c['id'];
        return $this->render('competition', [
            'title' => $c['name'], 'c' => $c,
            'rows' => RegistrationService::list($cid, ['status' => 'all'], (int) $club['id'], 1000),
            'canEdit' => RegistrationService::clubCanEdit($c) && $c['status'] !== 'archived',
            'athletes' => Database::all('SELECT * FROM athletes WHERE club_id = ? AND id NOT IN (SELECT athlete_id FROM registrations WHERE competition_id = ? AND status IN ("pending","confirmed")) ORDER BY last_name, first_name', [$club['id'], $cid]),
            'scripts' => ['registration-form.js'], 'reg' => null,
        ] + RegistrationController::formData($c));
    }

    public function register(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $data = $r->post;
        if ($aid = $r->int('athlete_id')) {
            $a = Database::one('SELECT * FROM athletes WHERE id = ? AND club_id = ?', [$aid, $club['id']]) ?? throw new HttpException(403);
            $data = array_merge(['last_name' => $a['last_name'], 'first_name' => $a['first_name'], 'sex' => $a['sex'], 'birth_date' => $a['birth_date'], 'grade' => $a['grade'], 'license_number' => $a['license_number']], array_filter($data, fn ($v) => $v !== ''));
        }
        unset($data['admin_comment']);
        $id = RegistrationService::register((int) $c['id'], $data, ['actor' => 'club', 'club_id' => (int) $club['id']]);
        RegistrationService::applyPhoto($id, $r->file('photo'));
        $reg = RegistrationService::find($id);
        return $this->redirect('/espace-club/competitions/' . $c['id'], "{$reg['first_name']} {$reg['last_name']} est inscrit(e) en {$reg['age_name']}" . ($reg['weight_label'] ? " {$reg['weight_label']} kg" : '') . '.');
    }

    public function editRegistration(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $reg = RegistrationService::find((int) $r->param('rid'));
        RegistrationService::assertClubOwns($reg, (int) $club['id']);
        return $this->render('registration', ['title' => 'Modifier une inscription', 'c' => $c, 'reg' => $reg, 'canEdit' => RegistrationService::clubCanEdit($c), 'scripts' => ['registration-form.js']] + RegistrationController::formData($c));
    }

    public function updateRegistration(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $data = $r->post;
        unset($data['admin_comment']);
        RegistrationService::update((int) $r->param('rid'), $data, ['actor' => 'club', 'club_id' => (int) $club['id']]);
        RegistrationService::applyPhoto((int) $r->param('rid'), $r->file('photo'), $r->bool('remove_photo'));
        return $this->redirect('/espace-club/competitions/' . $c['id'], 'Inscription modifiée.');
    }

    public function withdraw(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        RegistrationService::withdraw((int) $r->param('rid'), ['actor' => 'club', 'club_id' => (int) $club['id']]);
        return $this->redirect('/espace-club/competitions/' . $c['id'], 'Inscription retirée.');
    }

    public function export(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        return Response::download(RegistrationService::exportCsv((int) $c['id'], (int) $club['id']), 'inscrits-' . slugify($club['name']) . '-' . $c['slug'] . '.csv', 'text/csv; charset=utf-8');
    }

    public function import(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $file = $r->file('csv');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1_000_000) {
            throw new DomainError('Choisissez un fichier CSV (1 Mo maximum).');
        }
        $res = RegistrationService::importCsv((int) $c['id'], (string) file_get_contents($file['tmp_name']), ['actor' => 'club', 'club_id' => (int) $club['id']]);
        foreach (array_slice($res['errors'], 0, 30, true) as $line => $err) {
            \App\Core\Session::flash('error', "Ligne $line : $err");
        }
        return $this->redirect('/espace-club/competitions/' . $c['id'], $res['created'] . ' inscription(s) importée(s).', $res['created'] ? 'success' : 'warning');
    }

    public function pools(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $g = PoolService::activeGeneration((int) $c['id']);
        $published = $g && $g['status'] === 'validated' && SettingsService::get((int) $c['id'], 'pools_published');
        $pools = $published ? PoolService::pools((int) $g['id']) : [];
        // Les poules publiées sont visibles ; les données privées des autres clubs (date de naissance, licence) ne sont pas affichées.
        $mine = array_values(array_filter($pools, fn ($p) => in_array((int) $club['id'], array_map('intval', array_column($p['athletes'], 'club_id')), true)));
        return $this->render('pools', ['title' => 'Poules et horaires', 'c' => $c, 'published' => $published, 'pools' => $pools, 'mine' => $mine]);
    }

    public function results(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $cid = (int) $c['id'];
        $myRank = Database::one('SELECT * FROM club_rankings WHERE competition_id = ? AND club_id = ?', [$cid, $club['id']]);
        $bouts = Database::all(
            "SELECT b.*, p.label AS pool_label, t.number AS tatami_number, CONCAT(aa.first_name, ' ', aa.last_name) AS a_name, CONCAT(ab.first_name, ' ', ab.last_name) AS b_name, ra.club_id AS a_club, rb.club_id AS b_club
             FROM bouts b JOIN pools p ON p.id = b.pool_id LEFT JOIN tatamis t ON t.id = b.tatami_id
             JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id
             JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id
             WHERE b.competition_id = ? AND (ra.club_id = ? OR rb.club_id = ?) AND b.status <> 'cancelled' ORDER BY t.number, b.order_on_tatami",
            [$cid, $club['id'], $club['id']]
        );
        return $this->render('results', [
            'title' => 'Résultats du club', 'c' => $c, 'rank' => $myRank, 'individual' => RankingService::individual($cid, (int) $club['id']),
            'bouts' => $bouts, 'clubCount' => (int) Database::value('SELECT COUNT(*) FROM club_rankings WHERE competition_id = ?', [$cid]),
        ]);
    }
}
