<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\PdfService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\RegistrationService;

final class PdfController extends AdminController
{
    public const TYPES = [
        'participants' => 'Liste des participants',
        'categories' => 'Listes par catégorie',
        'poules' => 'Poules',
        'planning' => 'Planning des tapis',
        'feuilles' => 'Feuilles de poule',
        'resultats-poules' => 'Résultats par poule',
        'resultats-clubs' => 'Résultats par club',
        'classement' => 'Classement final',
        'clubs' => 'Classement des clubs',
    ];

    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $g = PoolService::activeGeneration((int) $c['id']);
        return $this->admin('pdf', ['title' => 'Documents PDF', 'competition' => $c, 'c' => $c, 'g' => $g, 'dompdf' => PdfService::available(),
            'tatamis' => CompetitionService::tatamis((int) $c['id']), 'ages' => CategoryService::ageCategories((int) $c['id'])]);
    }

    public function render(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $type = (string) $r->param('type');
        if (!isset(self::TYPES[$type])) {
            throw new DomainError('Document inconnu.', 404);
        }
        $q = $r->query;
        $opt = [
            'orientation' => ($q['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait',
            'per_page' => in_array((int) ($q['per_page'] ?? 2), [1, 2, 4, 6], true) ? (int) ($q['per_page'] ?? 2) : 2,
            'show_competition' => !isset($q['opts']) || !empty($q['show_competition']),
            'show_tatami' => !isset($q['opts']) || !empty($q['show_tatami']),
            'show_category' => !isset($q['opts']) || !empty($q['show_category']),
            'show_weight' => !isset($q['opts']) || !empty($q['show_weight']),
            'show_names' => !isset($q['opts']) || !empty($q['show_names']),
            'show_blank' => !isset($q['opts']) || !empty($q['show_blank']),
            'tatami_id' => isset($q['tatami_id']) && $q['tatami_id'] !== '' ? (int) $q['tatami_id'] : null,
            'age_category_id' => isset($q['age_category_id']) && $q['age_category_id'] !== '' ? (int) $q['age_category_id'] : null,
            'pool_id' => isset($q['pool_id']) && $q['pool_id'] !== '' ? (int) $q['pool_id'] : null,
            'club_id' => isset($q['club_id']) && $q['club_id'] !== '' ? (int) $q['club_id'] : null,
        ];
        $data = ['c' => $c, 'opt' => $opt, 'docTitle' => self::TYPES[$type], 'generatedAt' => date('d/m/Y H:i')];
        $g = PoolService::activeGeneration($cid);
        $pools = function () use ($g, $opt) {
            if (!$g) {
                throw new DomainError('Aucune poule générée.');
            }
            $list = PoolService::pools((int) $g['id'], $opt['tatami_id']);
            if ($opt['age_category_id']) {
                $list = array_values(array_filter($list, fn ($p) => (int) $p['age_category_id'] === $opt['age_category_id']));
            }
            if ($opt['pool_id']) {
                $list = array_values(array_filter($list, fn ($p) => (int) $p['id'] === $opt['pool_id']));
            }
            return $list;
        };
        switch ($type) {
            case 'participants':
            case 'categories':
                $f = ['status' => 'active'];
                if ($opt['age_category_id']) {
                    $f['age_category_id'] = $opt['age_category_id'];
                }
                if (!empty($q['present'])) {
                    $f['attendance'] = 'present';
                }
                $data['rows'] = RegistrationService::list($cid, $f, null, 100000);
                break;
            case 'feuilles':
                $data['pools'] = PoolService::sheets($pools());
                if (!empty($q['vierge'])) {
                    $data['docTitle'] = 'Feuilles de poule vierges';
                }
                if ($opt['pool_id'] && $data['pools']) {
                    $data['docSubtitle'] = 'Poule ' . $data['pools'][0]['number'];
                } elseif ($opt['tatami_id'] && $data['pools']) {
                    $data['docSubtitle'] = 'Tapis ' . $data['pools'][0]['tatami_number'];
                }
                break;
            case 'resultats-poules':
                $list = PoolService::sheets($pools());
                usort($list, fn ($x, $y) => [(int) $x['age_sort'], $x['sex'], (int) ($x['weight_sort'] ?? 0), (int) $x['number']] <=> [(int) $y['age_sort'], $y['sex'], (int) ($y['weight_sort'] ?? 0), (int) $y['number']]);
                $data['pools'] = $list;
                $data['medals'] = array_column(Database::all('SELECT registration_id, medal, place FROM athlete_rankings WHERE competition_id = ?', [$cid]), null, 'registration_id');
                break;
            case 'resultats-clubs':
                $data['clubs'] = self::clubResults($pools(), $cid, $opt['club_id']);
                if ($opt['club_id'] && $data['clubs']) {
                    $data['docSubtitle'] = reset($data['clubs'])['club']['name'];
                }
                break;
            case 'poules':
                $data['pools'] = $pools();
                $data['bouts'] = [];
                foreach (Database::all(
                    "SELECT b.*, CONCAT(aa.last_name, ' ', aa.first_name) AS a_name, CONCAT(ab.last_name, ' ', ab.first_name) AS b_name
                     FROM bouts b JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id
                     JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id
                     WHERE b.competition_id = ? AND b.status <> 'cancelled' ORDER BY b.pool_id, b.order_in_pool",
                    [$cid]
                ) as $b) {
                    $data['bouts'][$b['pool_id']][] = $b;
                }
                break;
            case 'planning':
                if (!$g) {
                    throw new DomainError('Aucune poule générée.');
                }
                $data['tatamis'] = [];
                foreach (CompetitionService::tatamis($cid) as $t) {
                    if ($opt['tatami_id'] && (int) $t['id'] !== $opt['tatami_id']) {
                        continue;
                    }
                    $t['bouts'] = Database::all(
                        "SELECT b.*, p.label AS pool_label, p.number AS pool_number, CONCAT(aa.last_name, ' ', aa.first_name) AS a_name, CONCAT(ab.last_name, ' ', ab.first_name) AS b_name,
                                ca.name AS a_club, cb.name AS b_club
                         FROM bouts b JOIN pools p ON p.id = b.pool_id
                         JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id LEFT JOIN clubs ca ON ca.id = ra.club_id
                         JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id LEFT JOIN clubs cb ON cb.id = rb.club_id
                         WHERE b.tatami_id = ? AND b.status <> 'cancelled' ORDER BY b.order_on_tatami",
                        [$t['id']]
                    );
                    $data['tatamis'][] = $t;
                }
                $data['gap'] = (int) \App\Services\SettingsService::get($cid, 'bout_gap_seconds');
                break;
            case 'classement':
                $data['rows'] = RankingService::individual($cid);
                break;
            case 'clubs':
                $data['byPoints'] = RankingService::clubTable($cid, 'points');
                $data['byMedals'] = RankingService::clubTable($cid, 'medals');
                break;
        }
        AuditLogger::log('pdf.export', 'competition', $cid, null, ['type' => $type], null, $cid);
        $orientation = in_array($type, ['planning', 'clubs'], true) && !isset($q['orientation']) ? 'landscape' : $opt['orientation'];
        return PdfService::render($type, $data, $type . '-' . $c['slug'] . '.pdf', $orientation, isset($q['html']));
    }

    /** Résultats par club : catégorie par catégorie (ordre des âges, puis des poids), puis ordre de classement. */
    public static function clubResults(array $poolList, int $cid, ?int $clubId = null): array
    {
        $sheets = PoolService::sheets($poolList);
        $medals = array_column(Database::all('SELECT registration_id, medal, place FROM athlete_rankings WHERE competition_id = ?', [$cid]), null, 'registration_id');
        $clubs = [];
        foreach ($sheets as $p) {
            foreach ($p['athletes'] as $a) {
                $clubKey = $a['club_id'] ? (int) $a['club_id'] : 0;
                if ($clubId && $clubKey !== $clubId) {
                    continue;
                }
                $st = $p['standing'][(int) $a['registration_id']] ?? null;
                $clubs[$clubKey]['club'] ??= ['name' => $a['club_name'] ?? ($a['club_exception_label'] ?: 'Sans club'), 'logo' => $a['club_logo']];
                $clubs[$clubKey]['rows'][] = [
                    'athlete' => $a, 'pool' => $p, 'wins' => $st['wins'] ?? 0, 'points' => $st['value_points'] ?? 0,
                    'rank' => $p['complete'] && $st && !$st['excluded'] ? (int) $st['rank'] : null, 'complete' => $p['complete'],
                    'medal' => $medals[(int) $a['registration_id']]['medal'] ?? null, 'fought' => $p['bouts_done'],
                ];
            }
        }
        $key = fn ($x) => [(int) $x['pool']['age_sort'], $x['pool']['sex'], (int) ($x['pool']['weight_sort'] ?? 0), (int) $x['pool']['number'], $x['rank'] ?? 99, $x['athlete']['last_name']];
        foreach ($clubs as &$cl) {
            usort($cl['rows'], fn ($x, $y) => $key($x) <=> $key($y));
        }
        unset($cl);
        uasort($clubs, fn ($x, $y) => strcmp($x['club']['name'], $y['club']['name']));
        return $clubs;
    }
}
