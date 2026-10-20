<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;
use App\Services\DomainError;
use App\Services\RegistrationService;
use App\Services\SettingsService;

final class RegistrationController extends AdminController
{
    public static function formData(array $c): array
    {
        $cid = (int) $c['id'];
        $ages = CategoryService::ageCategories($cid);
        $weights = CategoryService::weightCategories($cid);
        $open = Database::all('SELECT age_category_id, sex FROM competition_categories WHERE competition_id = ? AND is_open = 1', [$cid]);
        return [
            'ages' => $ages,
            'weightInput' => SettingsService::get($cid, 'weight_input'),
            'formConfig' => [
                'ages' => array_map(fn ($a) => ['id' => (int) $a['id'], 'name' => $a['name'], 'name_m' => $a['name_m'], 'name_f' => $a['name_f'], 'min' => (int) $a['birth_year_min'], 'max' => (int) $a['birth_year_max'], 'upgrade' => $a['upgrade_to_id'] ? (int) $a['upgrade_to_id'] : null, 'format' => $a['format']], $ages),
                'weights' => array_map(fn ($w) => ['id' => (int) $w['id'], 'age' => (int) $w['age_category_id'], 'sex' => $w['sex'], 'label' => $w['label'], 'max' => $w['max_weight_kg'] === null ? null : (float) $w['max_weight_kg']], $weights),
                'open' => array_map(fn ($o) => $o['age_category_id'] . $o['sex'], $open),
                'weightInput' => SettingsService::get($cid, 'weight_input'),
            ],
        ];
    }

    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $f = array_intersect_key($r->query, array_flip(['q', 'club_id', 'age_category_id', 'sex', 'attendance', 'status']));
        if (($r->query['filter'] ?? '') === 'pending') {
            $f['status'] = 'pending';
        }
        [$page, $per, $offset] = $this->page($r, 50);
        $total = RegistrationService::count($cid, $f);
        return $this->admin('registrations/index', [
            'title' => 'Inscriptions', 'competition' => $c, 'c' => $c, 'f' => $f,
            'rows' => RegistrationService::list($cid, $f, null, $per, $offset), 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $per),
            'clubs' => Database::all('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name'), 'ages' => CategoryService::ageCategories($cid),
        ]);
    }

    public function create(Request $r): Response
    {
        $c = $this->competition($r);
        return $this->admin('registrations/form', ['title' => 'Nouvelle inscription', 'competition' => $c, 'c' => $c, 'reg' => null,
            'clubs' => Database::all('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name'), 'scripts' => ['registration-form.js']] + self::formData($c));
    }

    public function store(Request $r): Response
    {
        $c = $this->competition($r);
        $clubId = $r->int('club_id');
        $id = RegistrationService::register((int) $c['id'], $r->post, ['actor' => 'admin', 'club_id' => $clubId, 'club_exception_label' => $r->str('club_exception_label')]);
        $reg = RegistrationService::find($id);
        return $this->redirect('/admin/competitions/' . $c['id'] . '/inscriptions' . ($r->bool('again') ? '/nouvelle' : ''), "Inscription enregistrée : {$reg['first_name']} {$reg['last_name']} — {$reg['age_name']}" . ($reg['weight_label'] ? " {$reg['weight_label']} kg" : '') . ($reg['is_late'] ? ' (tardive)' : '') . '.');
    }

    public function edit(Request $r): Response
    {
        $c = $this->competition($r);
        $reg = RegistrationService::find((int) $r->param('rid'));
        if ((int) $reg['competition_id'] !== (int) $c['id']) {
            throw new DomainError('Inscription introuvable.', 404);
        }
        return $this->admin('registrations/form', ['title' => 'Inscription', 'competition' => $c, 'c' => $c, 'reg' => $reg,
            'history' => \App\Services\AttendanceService::history((int) $reg['id']), 'inPool' => RegistrationService::inPool((int) $reg['id']),
            'clubs' => Database::all('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name'), 'scripts' => ['registration-form.js']] + self::formData($c));
    }

    public function update(Request $r): Response
    {
        $c = $this->competition($r);
        $rid = (int) $r->param('rid');
        RegistrationService::update($rid, $r->post, ['actor' => 'admin', 'club_id' => $r->int('club_id')]);
        return $this->redirect('/admin/competitions/' . $c['id'] . '/inscriptions/' . $rid, 'Inscription mise à jour.');
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        $rid = (int) $r->param('rid');
        $reg = RegistrationService::find($rid);
        if ((int) $reg['competition_id'] !== (int) $c['id']) {
            throw new DomainError('Inscription introuvable.', 404);
        }
        match ($r->str('op')) {
            'withdraw' => RegistrationService::withdraw($rid, ['actor' => 'admin'], $r->str('reason') ?: null),
            'restore' => RegistrationService::restore($rid),
            'validate' => RegistrationService::validate($rid, true),
            'reject' => RegistrationService::validate($rid, false, $r->str('reason') ?: null),
            default => throw new DomainError('Action inconnue.'),
        };
        return $this->back($r, 'Inscription mise à jour.');
    }

    public function export(Request $r): Response
    {
        $c = $this->competition($r);
        return Response::download(RegistrationService::exportCsv((int) $c['id']), 'inscriptions-' . $c['slug'] . '.csv', 'text/csv; charset=utf-8');
    }

    public function import(Request $r): Response
    {
        $c = $this->competition($r);
        $file = $r->file('csv');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2_000_000) {
            throw new DomainError('Choisissez un fichier CSV (2 Mo maximum).');
        }
        $res = RegistrationService::importCsv((int) $c['id'], (string) file_get_contents($file['tmp_name']), ['actor' => 'admin']);
        foreach (array_slice($res['errors'], 0, 30, true) as $line => $err) {
            \App\Core\Session::flash('error', "Ligne $line : $err");
        }
        return $this->back($r, $res['created'] . ' inscription(s) importée(s)' . ($res['errors'] ? ', ' . count($res['errors']) . ' ligne(s) en erreur (non importées).' : '.'), $res['created'] ? 'success' : 'warning');
    }
}
