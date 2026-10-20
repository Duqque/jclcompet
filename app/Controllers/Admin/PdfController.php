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
        'feuilles' => 'Feuilles de résultats',
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
            case 'poules':
            case 'feuilles':
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
}
