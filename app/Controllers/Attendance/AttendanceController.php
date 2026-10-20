<?php
declare(strict_types=1);

namespace App\Controllers\Attendance;

use App\Controllers\Admin\RegistrationController;
use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AttendanceService;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\RegistrationService;
use App\Services\SettingsService;

/** Accueil des combattants le jour J (mobile d'abord) : pointage présent / absent / à vérifier, inscription sur place. */
final class AttendanceController extends Controller
{
    public function index(Request $r): Response
    {
        $rows = Database::all("SELECT * FROM competitions WHERE status NOT IN ('draft','archived') ORDER BY ABS(DATEDIFF(event_date, CURDATE())) LIMIT 10");
        if (count($rows) === 1) {
            return Response::redirect('/accueil/' . $rows[0]['id']);
        }
        return $this->view('attendance/index', ['title' => 'Accueil', 'competitions' => $rows], 'attendance');
    }

    public function show(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        $cid = (int) $c['id'];
        return $this->view('attendance/show', [
            'title' => 'Présences', 'c' => $c,
            'clubs' => Database::all(
                "SELECT c.id, c.name, c.short_name, c.logo_path, COUNT(*) AS n FROM registrations r JOIN clubs c ON c.id = r.club_id
                 WHERE r.competition_id = ? AND r.status IN ('pending','confirmed') GROUP BY c.id ORDER BY n DESC, c.name",
                [$cid]
            ),
            'ages' => CategoryService::ageCategories($cid),
            'lateAllowed' => (bool) SettingsService::get($cid, 'late_registration_allowed'),
            'weighIn' => SettingsService::get($cid, 'weight_input') === 'weight',
            'scripts' => ['attendance.js'],
            'config' => ['listUrl' => url('/api/accueil/' . $cid . '/athletes'), 'markUrl' => url('/api/accueil/inscriptions/__ID__/presence'), 'weighIn' => SettingsService::get($cid, 'weight_input') === 'weight'],
        ], 'attendance');
    }

    public function list(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        $f = array_intersect_key($r->query, array_flip(['q', 'club_id', 'age_category_id', 'sex', 'attendance']));
        $rows = RegistrationService::list((int) $c['id'], $f + ['status' => 'active'], null, 2000);
        $counts = Database::one(
            "SELECT SUM(attendance_status = 'present') AS present, SUM(attendance_status = 'absent') AS absent, SUM(attendance_status = 'to_check') AS to_check, SUM(attendance_status = 'expected') AS expected, COUNT(*) AS total
             FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')",
            [$c['id']]
        );
        return $this->json([
            'counts' => array_map('intval', $counts),
            'rows' => array_map(fn ($x) => [
                'id' => (int) $x['id'], 'last_name' => $x['last_name'], 'first_name' => $x['first_name'], 'sex' => $x['sex'],
                'club' => $x['club_name'] ?? $x['club_exception_label'], 'club_short' => $x['club_short'] ?: ($x['club_name'] ?? $x['club_exception_label']),
                'logo' => $x['club_logo'] ? url('/' . ltrim($x['club_logo'], '/')) : null,
                'photo' => $x['photo_document_id'] ? url('/media/' . $x['photo_document_id'], ['mini' => 1]) : null,
                'year' => substr((string) $x['birth_date'], 0, 4), 'grade' => $x['grade'],
                'declared' => $x['declared_weight_kg'] === null ? null : (float) $x['declared_weight_kg'],
                'category' => CategoryService::label(['name' => $x['age_name'], 'name_m' => $x['name_m'], 'name_f' => $x['name_f']], $x['sex']),
                'weight' => $x['weight_label'], 'measured' => $x['measured_weight_kg'] === null ? null : (float) $x['measured_weight_kg'],
                'attendance' => $x['attendance_status'], 'at' => $x['attendance_at'] ? substr($x['attendance_at'], 11, 5) : null, 'by' => $x['attendance_by_name'],
                'late' => (bool) $x['is_late'], 'onsite' => (bool) $x['is_onsite'], 'pending' => (bool) $x['needs_validation'],
            ], $rows),
        ]);
    }

    public function mark(Request $r): Response
    {
        $in = $r->json();
        $weight = isset($in['measured_weight_kg']) && $in['measured_weight_kg'] !== '' && $in['measured_weight_kg'] !== null ? (float) str_replace(',', '.', (string) $in['measured_weight_kg']) : null;
        $reg = AttendanceService::mark((int) $r->param('rid'), (string) ($in['status'] ?? ''), $weight, isset($in['note']) ? (string) $in['note'] : null);
        return $this->json(['ok' => true, 'attendance' => $reg['attendance_status'], 'weight' => $reg['weight_label'], 'at' => substr((string) $reg['attendance_at'], 11, 5)]);
    }

    public function onsiteForm(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        return $this->view('attendance/onsite', [
            'title' => 'Inscription sur place', 'c' => $c, 'reg' => null, 'scripts' => ['registration-form.js'],
            'clubs' => Database::all('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name'),
            'lateAllowed' => (bool) SettingsService::get((int) $c['id'], 'late_registration_allowed'),
            'needsValidation' => (bool) SettingsService::get((int) $c['id'], 'late_registration_requires_validation'),
        ] + RegistrationController::formData($c), 'attendance');
    }

    public function onsiteStore(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        $clubId = $r->int('club_id');
        if (!$clubId && $r->str('club_exception_label') === '') {
            throw new DomainError('Sélectionnez le club ou saisissez une exception autorisée.');
        }
        $measured = $r->str('measured_weight_kg') !== '' ? (float) str_replace(',', '.', $r->str('measured_weight_kg')) : null;
        $id = RegistrationService::register((int) $c['id'], $r->post, [
            'actor' => 'onsite', 'club_id' => $clubId, 'club_exception_label' => $r->str('club_exception_label'),
            'present' => true, 'measured_weight_kg' => $measured,
        ]);
        RegistrationService::applyPhoto($id, $r->file('photo'));
        $reg = RegistrationService::find($id);
        return $this->redirect('/accueil/' . $c['id'], "{$reg['first_name']} {$reg['last_name']} inscrit(e) sur place et pointé(e) présent(e)" . ($reg['needs_validation'] ? ' — en attente de validation par l\'administration.' : '.'));
    }
}
