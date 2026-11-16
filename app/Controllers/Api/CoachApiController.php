<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Club\ClubSpaceController;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccountRequestService;
use App\Services\ApiTokenService;
use App\Services\AttendanceService;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\ClubColorService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\RegistrationService;
use App\Services\SettingsService;

/**
 * API privée de l'application JCL Live pour les coachs : compte (connexion par jeton, demande de compte) et
 * gestion des inscriptions du club. Mêmes règles que l'espace club du site (RegistrationService, actor « club ») :
 * un coach ne voit et ne modifie que les judokas de son club. Les écrans du site et de l'application se mettent à
 * jour en direct grâce à l'empreinte /etat (RegistrationService::stamp).
 */
final class CoachApiController extends Controller
{
    // ------------------------------------------------------------------ Compte

    public function login(Request $r): Response
    {
        $email = trim((string) $r->input('email', ''));
        $password = (string) $r->input('password', '');
        if ($email === '' || $password === '') {
            throw new DomainError('Saisissez votre adresse e-mail et votre mot de passe.');
        }
        $res = Auth::verify($email, $password, $r);
        if (!$res['ok']) {
            throw new DomainError($res['error'], !empty($res['pending']) ? 403 : 401, ['pending' => !empty($res['pending'])]);
        }
        $user = $res['user'];
        if (!in_array($user['role_code'], ['coach', 'admin'], true)) {
            throw new DomainError("L'application est réservée aux coachs : ce compte s'utilise sur le site.", 403);
        }
        $token = ApiTokenService::issue((int) $user['id'], (string) $r->input('device', 'iPhone'));
        Auth::useToken((int) $user['id']);
        AuditLogger::log('auth.login_app', 'user', (int) $user['id']);
        return $this->json(['token' => $token, 'account' => $this->account()]);
    }

    public function signup(Request $r): Response
    {
        AccountRequestService::create($r->all(), 'app', $r->ip());
        return $this->json(['ok' => true, 'message' => "Demande envoyée. L'organisation du JC Leforest valide votre compte : vous pourrez ensuite vous connecter avec cette adresse e-mail et ce mot de passe."]);
    }

    public function clubs(Request $r): Response
    {
        return $this->json(['clubs' => array_map(fn ($c) => [
            'id' => (int) $c['id'], 'name' => $c['name'], 'short' => $c['short_name'] ?: $c['name'], 'city' => $c['city'],
            'logo' => $c['logo_path'] ? url('/' . ltrim($c['logo_path'], '/')) : null, 'color' => $c['color'],
        ], AccountRequestService::clubs())]);
    }

    public function me(Request $r): Response
    {
        return $this->json($this->account());
    }

    public function logout(Request $r): Response
    {
        ApiTokenService::revoke((string) $r->header('Authorization'));
        AuditLogger::log('auth.logout_app', 'user', Auth::id());
        return $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------ Espace club

    /** Club et compétitions (inscriptions ouvertes, en cours, passées), avec les effectifs du club. */
    public function home(Request $r): Response
    {
        $club = $this->club();
        $rows = Database::all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM registrations r WHERE r.competition_id = c.id AND r.club_id = :club AND r.status IN ('pending','confirmed')) AS mine,
                    (SELECT COUNT(*) FROM registrations r WHERE r.competition_id = c.id AND r.club_id = :club2 AND r.status IN ('pending','confirmed') AND r.attendance_status = 'present') AS present
             FROM competitions c WHERE c.status NOT IN ('draft') ORDER BY c.status = 'archived', c.status = 'in_progress' DESC, c.event_date DESC",
            ['club' => $club['id'], 'club2' => $club['id']]
        );
        return $this->json(['account' => $this->account(), 'competitions' => array_map(fn ($c) => $this->compSummary($c) + [
            'mine' => (int) $c['mine'], 'present' => (int) $c['present'],
        ], $rows)]);
    }

    /** Fiche compétition du club : mes judokas, judokas du club à réinscrire, règles du formulaire. */
    public function competition(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $cid = (int) $c['id'];
        $rows = RegistrationService::list($cid, ['status' => 'all'], (int) $club['id'], 1000);
        $inPool = array_flip(array_map('intval', Database::column(
            "SELECT pa.registration_id FROM pool_athletes pa JOIN pool_generations g ON g.id = pa.generation_id WHERE g.competition_id = ? AND g.status IN ('draft','validated')",
            [$cid]
        )));
        $validation = RegistrationService::clubListValidation($cid, (int) $club['id']);
        $previous = Database::all(
            "SELECT * FROM athletes WHERE club_id = ? AND id NOT IN (SELECT athlete_id FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')) ORDER BY last_name, first_name",
            [$club['id'], $cid]
        );
        $weights = CategoryService::weightCategories($cid);
        return $this->json($this->compSummary($c) + [
            'stamp' => RegistrationService::stamp($cid, (int) $club['id']),
            'weigh_in' => SettingsService::get($cid, 'weight_input') === 'weight',
            'weight_by_category' => SettingsService::get($cid, 'weight_input') === 'category',
            'require_license' => (bool) SettingsService::get($cid, 'require_license'),
            'list_validation' => $validation ? ['at' => $validation['validated_at'], 'by' => $validation['display_name'], 'count' => (int) $validation['registrations_count'], 'changed_since' => (int) $validation['changed_since']] : null,
            'fighters' => array_map(fn ($x) => $this->fighter($x, isset($inPool[(int) $x['id']])), $rows),
            'club_athletes' => array_map(fn ($a) => [
                'id' => (int) $a['id'], 'first_name' => $a['first_name'], 'last_name' => mb_strtoupper($a['last_name']), 'sex' => $a['sex'],
                'birth_date' => $a['birth_date'], 'grade' => $a['grade'], 'license_number' => $a['license_number'],
            ], $previous),
            'weight_categories' => array_map(fn ($w) => ['id' => (int) $w['id'], 'age_id' => (int) $w['age_category_id'], 'sex' => $w['sex'], 'label' => $w['label']], $weights),
            'grades' => ['Blanche', 'Blanche-jaune', 'Jaune', 'Jaune-orange', 'Orange', 'Orange-verte', 'Verte', 'Bleue', 'Marron', 'Noire'],
        ]);
    }

    /** Empreinte : l'application la consulte toutes les 3 s et recharge la fiche dès qu'elle change. */
    public function stamp(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        return $this->json(['stamp' => RegistrationService::stamp((int) $c['id'], (int) $club['id'])])->header('Cache-Control', 'no-store');
    }

    /** Catégorie calculée avant l'envoi (date de naissance, sexe, poids). */
    public function preview(Request $r): Response
    {
        $c = $this->comp($r);
        $birth = (string) $r->input('birth_date', '');
        $sex = (string) $r->input('sex', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth) || !in_array($sex, ['M', 'F'], true)) {
            return $this->json(['category' => null, 'weight' => null]);
        }
        try {
            [$age] = CategoryService::resolveAgeCategory((int) $c['id'], $birth, $sex);
            $kg = $r->input('declared_weight_kg');
            $kg = $kg === null || $kg === '' ? null : (float) str_replace(',', '.', (string) $kg);
            $wid = (int) $r->input('weight_category_id', 0) ?: null;
            [$w] = RegistrationService::resolveWeight((int) $c['id'], $age, $sex, $kg, $wid);
            return $this->json(['category' => CategoryService::label($age, $sex), 'age_id' => (int) $age['id'], 'weight' => $w['label'] ?? null, 'error' => null]);
        } catch (HttpException $e) {
            return $this->json(['category' => null, 'weight' => null, 'error' => $e->getMessage()]);
        }
    }

    public function register(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $data = $this->fields($r);
        if ($aid = (int) $r->input('athlete_id', 0)) {
            $a = Database::one('SELECT * FROM athletes WHERE id = ? AND club_id = ?', [$aid, $club['id']]) ?? throw new HttpException(403);
            $data = array_merge(['last_name' => $a['last_name'], 'first_name' => $a['first_name'], 'sex' => $a['sex'], 'birth_date' => $a['birth_date'],
                'grade' => $a['grade'], 'license_number' => $a['license_number']], array_filter($data, fn ($v) => $v !== '' && $v !== null));
        }
        $id = RegistrationService::register((int) $c['id'], $data, ['actor' => 'club', 'club_id' => (int) $club['id']]);
        $reg = RegistrationService::find($id);
        return $this->json(['ok' => true, 'message' => "{$reg['first_name']} {$reg['last_name']} est inscrit(e) en " . CategoryService::label(['name' => $reg['age_name'], 'name_m' => $reg['name_m'] ?? null, 'name_f' => $reg['name_f'] ?? null], $reg['sex']) . ($reg['weight_label'] ? " {$reg['weight_label']} kg" : '') . '.', 'id' => $id]);
    }

    public function update(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $reg = $this->ownRegistration($r, $c, $club);
        $data = $this->fields($r);
        if (RegistrationService::inPool((int) $reg['id'])) {
            RegistrationService::updateDetails((int) $reg['id'], $data, ['actor' => 'club', 'club_id' => (int) $club['id']]);
            return $this->json(['ok' => true, 'message' => 'Grade et licence mis à jour (catégorie et poids verrouillés : le judoka est placé en poule).']);
        }
        RegistrationService::update((int) $reg['id'], $data, ['actor' => 'club', 'club_id' => (int) $club['id']]);
        return $this->json(['ok' => true, 'message' => 'Inscription modifiée.']);
    }

    public function photo(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $reg = $this->ownRegistration($r, $c, $club);
        CompetitionService::assertWritable($c);
        RegistrationService::applyPhoto((int) $reg['id'], $r->file('photo'), $r->bool('remove'));
        return $this->json(['ok' => true, 'message' => $r->bool('remove') ? 'Photo retirée.' : 'Photo enregistrée.']);
    }

    public function withdraw(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $reg = $this->ownRegistration($r, $c, $club);
        RegistrationService::withdraw((int) $reg['id'], ['actor' => 'club', 'club_id' => (int) $club['id']]);
        return $this->json(['ok' => true, 'message' => $reg['first_name'] . ' ' . $reg['last_name'] . ' : inscription retirée.']);
    }

    /** Le jour J : présence et poids des judokas du club (mêmes règles que le site). */
    public function mark(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        $reg = $this->ownRegistration($r, $c, $club);
        if (!ClubSpaceController::attendanceOpen($c)) {
            throw new DomainError('Le pointage des présences ouvre le jour de la compétition.');
        }
        $status = (string) $r->input('status', 'present');
        if (!in_array($status, ['present', 'absent', 'expected'], true)) {
            throw new DomainError('Statut de présence inconnu.');
        }
        $raw = trim(str_replace(',', '.', (string) $r->input('measured_weight_kg', '')));
        $weight = $raw !== '' ? (float) $raw : null;
        if ($weight !== null && RegistrationService::inPool((int) $reg['id'])) {
            throw new DomainError('Ce judoka est déjà placé en poule : son poids ne peut plus être modifié (contactez l\'organisation).');
        }
        AttendanceService::mark((int) $reg['id'], $status, $weight, null);
        $label = ['present' => 'présent(e)', 'absent' => 'absent(e)', 'expected' => 'attendu(e)'][$status];
        return $this->json(['ok' => true, 'message' => $reg['first_name'] . ' ' . $reg['last_name'] . ' : ' . $label . ($weight !== null ? ', pesé(e) ' . kg($weight) : '') . '.']);
    }

    public function validateList(Request $r): Response
    {
        $club = $this->club();
        $c = $this->comp($r);
        if (!RegistrationService::clubCanEdit($c)) {
            throw new DomainError('La date limite est dépassée : contactez l\'organisation.');
        }
        $n = RegistrationService::validateClubList((int) $c['id'], (int) $club['id']);
        return $this->json(['ok' => true, 'message' => "Liste validée : $n judoka(s) confirmé(s). L'organisation est prévenue."]);
    }

    /** Photo d'un judoka du club (privée) : servie à l'application avec le jeton, jamais aux autres clubs. */
    public function media(Request $r): Response
    {
        $club = $this->club();
        $d = \App\Services\DocumentService::find((int) $r->param('doc'));
        if ($d['type'] !== 'athlete_photo' || (int) Database::value('SELECT club_id FROM athletes WHERE photo_document_id = ?', [$d['id']]) !== (int) $club['id']) {
            throw new HttpException(404, 'Photo introuvable.');
        }
        $mini = $d['thumb_name'] && !isset($r->query['grand']);
        $path = \App\Services\DocumentService::dir() . '/' . ($mini ? $d['thumb_name'] : $d['stored_name']);
        if (!is_file($path)) {
            throw new HttpException(404, 'Photo introuvable.');
        }
        return Response::file($path, $d['mime_type'], $d['original_name'], true)->header('Cache-Control', 'private, max-age=600');
    }

    // ------------------------------------------------------------------ Outils

    private function account(): array
    {
        $u = Auth::user() ?? throw new HttpException(401);
        $club = Auth::clubId() ? Database::one('SELECT id, name, short_name, logo_path, brand_color, city FROM clubs WHERE id = ?', [Auth::clubId()]) : null;
        return [
            'id' => (int) $u['id'], 'name' => $u['display_name'], 'email' => $u['email'], 'role' => $u['role_code'],
            'club' => $club ? ['id' => (int) $club['id'], 'name' => $club['name'], 'short' => $club['short_name'] ?: $club['name'], 'city' => $club['city'],
                'logo' => $club['logo_path'] ? url('/' . ltrim($club['logo_path'], '/')) : null, 'color' => ClubColorService::forClub($club)] : null,
        ];
    }

    private function club(): array
    {
        $id = Auth::clubId() ?? throw new HttpException(403, 'Votre compte n\'est rattaché à aucun club : contactez l\'organisation.');
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

    private function ownRegistration(Request $r, array $c, array $club): array
    {
        $reg = RegistrationService::find((int) $r->param('rid'));
        if ((int) $reg['competition_id'] !== (int) $c['id'] || (int) $reg['club_id'] !== (int) $club['id']) {
            throw new HttpException(403, 'Ce judoka n\'appartient pas à votre club.');
        }
        return $reg;
    }

    /** Champs modifiables par un club (jamais le commentaire de l'organisation). */
    private function fields(Request $r): array
    {
        $d = array_intersect_key($r->all(), array_flip(['last_name', 'first_name', 'sex', 'birth_date', 'declared_weight_kg', 'grade', 'license_number', 'weight_category_id']));
        if (isset($d['declared_weight_kg'])) {
            $d['declared_weight_kg'] = str_replace(',', '.', (string) $d['declared_weight_kg']);
        }
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $d);
    }

    private function compSummary(array $c): array
    {
        return [
            'id' => (int) $c['id'], 'slug' => $c['slug'], 'name' => $c['name'], 'date' => $c['event_date'], 'city' => $c['city'], 'venue' => $c['venue'],
            'status' => $c['status'], 'status_label' => CompetitionService::statusLabel($c['status']),
            'can_edit' => RegistrationService::clubCanEdit($c) && $c['status'] !== 'archived',
            'closes_at' => $c['registration_closes_at'],
            'attendance_open' => ClubSpaceController::attendanceOpen($c),
            'live' => $c['status'] === 'in_progress',
        ];
    }

    private function fighter(array $x, bool $inPool): array
    {
        return [
            'id' => (int) $x['id'], 'athlete_id' => (int) $x['athlete_id'],
            'first_name' => $x['first_name'], 'last_name' => mb_strtoupper($x['last_name']), 'sex' => $x['sex'], 'birth_date' => $x['birth_date'],
            'grade' => $x['grade'], 'license_number' => $x['license_number'],
            'category' => CategoryService::label(['name' => $x['age_name'], 'name_m' => $x['name_m'], 'name_f' => $x['name_f']], $x['sex']),
            'weight' => $x['weight_label'], 'weight_category_id' => $x['weight_category_id'] ? (int) $x['weight_category_id'] : null,
            'weight_by_category' => (bool) $x['weight_declared_by_category'],
            'declared_weight_kg' => $x['declared_weight_kg'] === null ? null : (float) $x['declared_weight_kg'],
            'measured_weight_kg' => $x['measured_weight_kg'] === null ? null : (float) $x['measured_weight_kg'],
            'status' => $x['status'], 'attendance' => $x['attendance_status'], 'in_pool' => $inPool,
            'photo' => $x['photo_document_id'] ? url('/api/v1/club/photos/' . $x['photo_document_id']) : null,
            'late' => (bool) $x['is_late'], 'onsite' => (bool) $x['is_onsite'],
        ];
    }
}
