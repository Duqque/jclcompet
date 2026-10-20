<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DocumentService;
use App\Services\DomainError;
use App\Services\DurationRuleService;
use App\Services\TvTokenService;

final class CompetitionController extends AdminController
{
    private const RULES = [
        'name' => 'required|maxlen:190', 'edition_label' => 'nullable|maxlen:80', 'tagline' => 'nullable|maxlen:255', 'description' => 'nullable',
        'event_date' => 'required|date', 'start_time' => 'required|time', 'venue' => 'nullable|maxlen:190', 'address' => 'required|maxlen:255',
        'city' => 'required|maxlen:120', 'registration_opens_at' => 'nullable|datetime', 'registration_closes_at' => 'nullable|datetime',
        'age_reference_date' => 'nullable|date', 'mat_count' => 'required|int|min:1|max:20', 'max_athletes' => 'nullable|int|min:1',
        'expected_participants' => 'nullable|int|min:0', 'schedule_text' => 'nullable', 'practical_info' => 'nullable', 'coach_info' => 'nullable',
        'is_featured' => 'bool',
    ];
    private const LABELS = [
        'name' => 'Nom', 'event_date' => 'Date', 'start_time' => 'Heure de début', 'address' => 'Adresse', 'city' => 'Ville',
        'mat_count' => 'Nombre de tapis', 'registration_opens_at' => 'Ouverture des inscriptions', 'registration_closes_at' => 'Clôture des inscriptions',
        'max_athletes' => 'Nombre maximal d\'athlètes', 'age_reference_date' => 'Date de référence des âges',
    ];

    public function index(Request $r): Response
    {
        $rows = Database::all(
            "SELECT c.*, (SELECT COUNT(*) FROM registrations r WHERE r.competition_id = c.id AND r.status IN ('pending','confirmed')) AS regs
             FROM competitions c ORDER BY c.status = 'archived', c.event_date DESC"
        );
        return $this->admin('competitions/index', ['title' => 'Compétitions', 'rows' => $rows]);
    }

    public function create(Request $r): Response
    {
        return $this->admin('competitions/form', ['title' => 'Nouvelle compétition', 'c' => null, 'templates' => CategoryService::TEMPLATES]);
    }

    public function store(Request $r): Response
    {
        $data = Validator::validate($r->post, self::RULES, self::LABELS);
        $data['pool_sizes'] = implode(',', (array) ($r->post['pool_sizes'] ?? ['3', '4', '5']));
        $codes = array_values(array_intersect((array) ($r->post['age_templates'] ?? []), array_column(CategoryService::TEMPLATES, 'code')));
        $id = CompetitionService::create($data, $codes, $r->bool('with_weights'));
        $this->handleUploads($r, $id);
        return $this->redirect('/admin/competitions/' . $id, 'Compétition créée. Vérifiez les catégories, les durées de combat et les paramètres sportifs.');
    }

    public function show(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        return $this->admin('competitions/show', [
            'title' => $c['name'], 'competition' => $c, 'c' => $c,
            'stats' => CompetitionService::stats($cid),
            'progress' => CompetitionService::tatamiProgress($cid),
            'tv' => TvTokenService::forCompetition($cid),
            'issues' => DurationRuleService::configurationIssues($cid),
            'recent' => Database::all('SELECT * FROM audit_logs WHERE competition_id = ? ORDER BY id DESC LIMIT 15', [$cid]),
            'byCategory' => Database::all(
                "SELECT ac.name, ac.name_m, ac.name_f, ac.format, a.sex, COUNT(*) AS n, SUM(r.attendance_status = 'present') AS present
                 FROM registrations r JOIN athletes a ON a.id = r.athlete_id JOIN age_categories ac ON ac.id = r.age_category_id
                 WHERE r.competition_id = ? AND r.status IN ('pending','confirmed') GROUP BY ac.id, a.sex ORDER BY ac.sort_order, a.sex",
                [$cid]
            ),
        ]);
    }

    public function edit(Request $r): Response
    {
        $c = $this->competition($r);
        return $this->admin('competitions/form', ['title' => 'Informations', 'competition' => $c, 'c' => $c, 'templates' => []]);
    }

    public function update(Request $r): Response
    {
        $c = $this->competition($r);
        $data = Validator::validate($r->post, self::RULES, self::LABELS);
        if (isset($r->post['pool_sizes'])) {
            $data['pool_sizes'] = implode(',', (array) $r->post['pool_sizes']);
        }
        CompetitionService::update((int) $c['id'], $data);
        if (isset($r->post['edition_label'])) {
            Database::run('UPDATE competitions SET edition_label = ? WHERE id = ?', [$data['edition_label'], $c['id']]);
        }
        $this->handleUploads($r, (int) $c['id']);
        return $this->redirect('/admin/competitions/' . $c['id'] . '/modifier', 'Informations enregistrées.');
    }

    private function handleUploads(Request $r, int $id): void
    {
        if ($f = $r->file('regulation')) {
            $doc = DocumentService::storeUpload($f, 'regulation', 'Règlement', $id);
            Database::run('UPDATE competitions SET regulation_document_id = ? WHERE id = ?', [$doc, $id]);
        }
        if ($f = $r->file('cover')) {
            $doc = DocumentService::storeUpload($f, 'visual', 'Visuel', $id);
            Database::run('UPDATE competitions SET cover_document_id = ? WHERE id = ?', [$doc, $id]);
        }
        if ($r->bool('remove_cover')) {
            Database::run('UPDATE competitions SET cover_document_id = NULL WHERE id = ?', [$id]);
        }
    }

    public function status(Request $r): Response
    {
        $c = $this->competition($r);
        $warnings = CompetitionService::setStatus((int) $c['id'], $r->str('status'));
        foreach ($warnings as $w) {
            \App\Core\Session::flash($w['level'] === 'error' ? 'error' : 'warning', $w['message']);
        }
        return $this->back($r, 'Statut mis à jour : ' . CompetitionService::statusLabel($r->str('status')) . '.');
    }

    public function archive(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::archive((int) $c['id'], $r->str('reason'));
        return $this->back($r, 'Compétition archivée : elle est désormais en lecture seule.');
    }

    public function reopen(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::reopen((int) $c['id'], $r->str('reason'));
        return $this->back($r, 'Compétition rouverte.');
    }

    /** Prépare l'édition suivante : copie des paramètres, catégories (années +1), durées, barème, buvette, contenus, clubs. */
    public function duplicate(Request $r): Response
    {
        $c = $this->competition($r);
        $newId = Database::transaction(function () use ($c) {
            $shift = 364; // même jour de la semaine, un an plus tard
            $date = date('Y-m-d', strtotime($c['event_date'] . " +$shift days"));
            $years = (int) substr($date, 0, 4) - (int) substr($c['event_date'], 0, 4);
            $edition = $c['edition_label'] && preg_match('/(\d{4})/', $c['edition_label'], $m)
                ? str_replace($m[1], (string) ((int) $m[1] + $years), $c['edition_label']) : $c['edition_label'];
            $row = $c;
            foreach (['id', 'created_at', 'updated_at', 'archived_at', 'archived_by'] as $k) {
                unset($row[$k]);
            }
            $row['uuid'] = CompetitionService::uuid();
            $row['slug'] = CompetitionService::uniqueSlug($c['name'] . '-' . substr($date, 0, 4));
            $row['event_date'] = $date;
            $row['edition_label'] = $edition;
            $row['status'] = 'draft';
            $row['is_demo'] = 0;
            $row['age_reference_date'] = $c['age_reference_date'] ? date('Y-m-d', strtotime($c['age_reference_date'] . " +$years years")) : null;
            $row['registration_opens_at'] = null;
            $row['registration_closes_at'] = null;
            $row['created_by'] = \App\Core\Auth::id();
            $newId = Database::insert('competitions', $row);
            $old = (int) $c['id'];
            Database::run('INSERT INTO competition_settings (competition_id, setting_key, setting_value) SELECT ?, setting_key, setting_value FROM competition_settings WHERE competition_id = ?', [$newId, $old]);
            Database::run("UPDATE competition_settings SET setting_value = '{}' WHERE competition_id = ? AND setting_key = 'pool_group_overrides'", [$newId]);
            Database::run("UPDATE competition_settings SET setting_value = 'false' WHERE competition_id = ? AND setting_key = 'pools_published'", [$newId]);
            $ageMap = [];
            foreach (Database::all('SELECT * FROM age_categories WHERE competition_id = ?', [$old]) as $a) {
                $oldAge = (int) $a['id'];
                unset($a['id'], $a['created_at'], $a['updated_at'], $a['upgrade_to_id']);
                $a['competition_id'] = $newId;
                $a['birth_year_min'] += $years;
                $a['birth_year_max'] += $years;
                $ageMap[$oldAge] = Database::insert('age_categories', $a);
            }
            foreach (Database::all('SELECT id, upgrade_to_id FROM age_categories WHERE competition_id = ? AND upgrade_to_id IS NOT NULL', [$old]) as $a) {
                Database::run('UPDATE age_categories SET upgrade_to_id = ? WHERE id = ?', [$ageMap[$a['upgrade_to_id']] ?? null, $ageMap[$a['id']]]);
            }
            foreach (Database::all('SELECT * FROM competition_categories WHERE competition_id = ?', [$old]) as $cc) {
                Database::insert('competition_categories', ['competition_id' => $newId, 'age_category_id' => $ageMap[$cc['age_category_id']], 'sex' => $cc['sex'], 'is_open' => $cc['is_open'], 'merge_group' => $cc['merge_group'], 'max_participants' => $cc['max_participants']]);
            }
            foreach (Database::all('SELECT * FROM weight_categories WHERE competition_id = ?', [$old]) as $w) {
                unset($w['id'], $w['created_at']);
                $w['competition_id'] = $newId;
                $w['age_category_id'] = $ageMap[$w['age_category_id']];
                Database::insert('weight_categories', $w);
            }
            foreach (Database::all('SELECT * FROM fight_duration_rules WHERE competition_id = ?', [$old]) as $d) {
                unset($d['id'], $d['created_at'], $d['updated_at']);
                $d['competition_id'] = $newId;
                $d['age_category_id'] = $d['age_category_id'] ? $ageMap[$d['age_category_id']] : null;
                $d['valid_from'] = $d['valid_until'] = null;
                Database::insert('fight_duration_rules', $d);
            }
            Database::run('INSERT INTO ranking_rules (competition_id, place, points, medal, counts_in_table) SELECT ?, place, points, medal, counts_in_table FROM ranking_rules WHERE competition_id = ?', [$newId, $old]);
            Database::run('INSERT INTO products (competition_id, category, name, description, note, price_cents, is_active, sort_order) SELECT ?, category, name, description, note, price_cents, is_active, sort_order FROM products WHERE competition_id = ?', [$newId, $old]);
            Database::run('INSERT INTO content_sections (competition_id, page_key, section_key, title, body, image_document_id, sort_order, is_published) SELECT ?, page_key, section_key, title, body, image_document_id, sort_order, is_published FROM content_sections WHERE competition_id = ?', [$newId, $old]);
            Database::run('INSERT INTO content_items (competition_id, list_key, group_label, title, subtitle, body, link_url, image_document_id, is_highlight, sort_order, is_published) SELECT ?, list_key, group_label, title, subtitle, body, link_url, image_document_id, is_highlight, sort_order, is_published FROM content_items WHERE competition_id = ?', [$newId, $old]);
            Database::run('INSERT INTO competition_clubs (competition_id, club_id, is_host, sort_order) SELECT ?, club_id, is_host, sort_order FROM competition_clubs WHERE competition_id = ? AND is_host = 1', [$newId, $old]);
            CompetitionService::syncTatamis(CompetitionService::find($newId));
            AuditLogger::log('competition.duplicate', 'competition', $newId, ['from' => $old], ['event_date' => $date], null, $newId);
            return $newId;
        });
        return $this->redirect('/admin/competitions/' . $newId . '/modifier', 'Édition suivante préparée (brouillon). Vérifiez la date, les années de naissance, les horaires et les dates d\'inscription.');
    }

    public function clubs(Request $r): Response
    {
        $c = $this->competition($r);
        $present = Database::all('SELECT cc.*, c.name, c.city, c.department, c.logo_path FROM competition_clubs cc JOIN clubs c ON c.id = cc.club_id WHERE cc.competition_id = ? ORDER BY cc.is_host, cc.sort_order, c.name', [$c['id']]);
        $registered = Database::all(
            "SELECT DISTINCT c.id, c.name FROM registrations r JOIN clubs c ON c.id = r.club_id WHERE r.competition_id = ? AND r.status IN ('pending','confirmed')
             AND c.id NOT IN (SELECT club_id FROM competition_clubs WHERE competition_id = ?) ORDER BY c.name",
            [$c['id'], $c['id']]
        );
        $all = Database::all('SELECT id, name, city FROM clubs WHERE is_active = 1 AND id NOT IN (SELECT club_id FROM competition_clubs WHERE competition_id = ?) ORDER BY name', [$c['id']]);
        return $this->admin('competitions/clubs', ['title' => 'Clubs présents', 'competition' => $c, 'c' => $c, 'present' => $present, 'registered' => $registered, 'all' => $all]);
    }

    public function saveClubs(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        switch ($r->str('op')) {
            case 'add':
                $order = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM competition_clubs WHERE competition_id = ?', [$cid]);
                foreach ((array) ($r->post['club_ids'] ?? []) as $clubId) {
                    $order += 10;
                    Database::run('INSERT IGNORE INTO competition_clubs (competition_id, club_id, sort_order) VALUES (?, ?, ?)', [$cid, (int) $clubId, $order]);
                }
                break;
            case 'add_registered':
                Database::run("INSERT IGNORE INTO competition_clubs (competition_id, club_id, sort_order) SELECT DISTINCT ?, club_id, 900 FROM registrations WHERE competition_id = ? AND club_id IS NOT NULL AND status IN ('pending','confirmed')", [$cid, $cid]);
                break;
            case 'remove':
                Database::run('DELETE FROM competition_clubs WHERE competition_id = ? AND club_id = ?', [$cid, $r->int('club_id')]);
                break;
            case 'host':
                Database::run('UPDATE competition_clubs SET is_host = (club_id = ?) WHERE competition_id = ?', [$r->int('club_id'), $cid]);
                break;
            default:
                throw new DomainError('Action inconnue.');
        }
        AuditLogger::log('competition.clubs', 'competition', $cid, null, ['op' => $r->str('op')], null, $cid);
        return $this->back($r, 'Liste des clubs présents mise à jour.');
    }
}
