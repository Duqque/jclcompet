<?php
declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\ContentService;
use App\Services\DocumentService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\SalesService;
use App\Services\SettingsService;

final class SiteController extends Controller
{
    public function home(Request $r): Response
    {
        $featured = CompetitionService::featured();
        $data = ['title' => '', 'featured' => $featured];
        if ($featured) {
            $data += $this->competitionFigures($featured);
        }
        $data['upcoming'] = Database::all("SELECT * FROM competitions WHERE status NOT IN ('draft','archived') AND event_date >= CURDATE() ORDER BY event_date LIMIT 4");
        $data['partners'] = ContentService::partners(null, null);
        $data['history'] = ContentService::history();
        return $this->view('public/home', $data);
    }

    private function competitionFigures(array $c): array
    {
        $cid = (int) $c['id'];
        return [
            'mats' => count(CompetitionService::tatamis($cid)),
            'clubsCount' => (int) Database::value('SELECT COUNT(*) FROM competition_clubs WHERE competition_id = ?', [$cid])
                ?: (int) Database::value("SELECT COUNT(DISTINCT club_id) FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')", [$cid]),
            'athletesCount' => (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')", [$cid]),
            'ages' => CategoryService::ageCategories($cid),
            'regOpen' => CompetitionService::registrationWindowOpen($c),
            'live' => $c['status'] === 'in_progress',
        ];
    }

    public function competitions(Request $r): Response
    {
        $all = Database::all("SELECT * FROM competitions WHERE status <> 'draft' ORDER BY event_date DESC");
        $groups = ['open' => [], 'upcoming' => [], 'live' => [], 'done' => []];
        foreach ($all as $c) {
            $key = match (true) {
                $c['status'] === 'in_progress' => 'live',
                in_array($c['status'], ['finished', 'archived'], true) => 'done',
                CompetitionService::registrationWindowOpen($c) => 'open',
                default => 'upcoming',
            };
            $groups[$key][] = $c;
        }
        return $this->view('public/competitions', ['title' => 'Compétitions', 'groups' => $groups]);
    }

    public function competition(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        $cid = (int) $c['id'];
        $ages = CategoryService::ageCategories($cid);
        $weights = [];
        foreach (CategoryService::weightCategories($cid) as $w) {
            $weights[$w['sex']][$w['age_category_id']][] = $w;
        }
        $rules = \App\Services\DurationRuleService::rules($cid);
        $products = SalesService::products($cid);
        $menu = [];
        foreach ($products as $p) {
            $menu[$p['category']][] = $p;
        }
        $sections = ContentService::sectionMap($cid, 'competition');
        $schedule = [];
        foreach (ContentService::items($cid, 'schedule') as $i) {
            $schedule[$i['group_label'] ?? ''][] = $i;
        }
        $clubs = Database::all(
            'SELECT c.*, cc.is_host FROM competition_clubs cc JOIN clubs c ON c.id = cc.club_id WHERE cc.competition_id = ? ORDER BY cc.is_host, cc.sort_order, c.name',
            [$cid]
        );
        return $this->view('public/competition', [
            'title' => $c['name'] . ($c['edition_label'] ? ' — ' . $c['edition_label'] : ''),
            'description' => \App\Services\Markdown::plain($c['description'], 160),
            'c' => $c, 'ages' => $ages, 'weights' => $weights, 'rules' => $rules, 'menu' => $menu,
            's' => $sections, 'schedule' => $schedule, 'clubs' => $clubs,
            'steps' => ContentService::items($cid, 'day_steps'),
            'access' => ContentService::items($cid, 'access_rules'),
            'prizes' => ContentService::items($cid, 'lottery_prizes'),
            'partners' => ContentService::partnersByTier($cid),
            'history' => ContentService::history(),
            'cardOk' => (bool) SettingsService::get($cid, 'card_payment_accepted'),
            'regulation' => $c['regulation_document_id'] ? DocumentService::find((int) $c['regulation_document_id']) : null,
            'cover' => $c['cover_document_id'] ? DocumentService::url((int) $c['cover_document_id']) : null,
            'hasResults' => (bool) Database::value('SELECT 1 FROM bouts WHERE competition_id = ? AND status = "validated" LIMIT 1', [$cid]),
            'regOpen' => CompetitionService::registrationWindowOpen($c),
        ] + $this->competitionFigures($c));
    }

    public function results(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        $cid = (int) $c['id'];
        $g = PoolService::activeGeneration($cid);
        $published = $g && $g['status'] === 'validated';
        $pools = $published ? PoolService::pools((int) $g['id']) : [];
        $boutsByPool = [];
        if ($pools) {
            foreach (Database::all(
                "SELECT b.*, CONCAT(aa.first_name, ' ', aa.last_name) AS a_name, CONCAT(ab.first_name, ' ', ab.last_name) AS b_name
                 FROM bouts b JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id
                 JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id
                 WHERE b.competition_id = ? AND b.status <> 'cancelled' ORDER BY b.pool_id, b.order_in_pool",
                [$cid]
            ) as $b) {
                $boutsByPool[$b['pool_id']][] = $b;
            }
        }
        $view = in_array($r->query['vue'] ?? '', ['categories', 'poules', 'individuel', 'clubs', 'medailles', 'tapis'], true) ? $r->query['vue'] : 'categories';
        return $this->view('public/results', [
            'title' => 'Résultats — ' . $c['name'], 'c' => $c, 'view' => $view, 'pools' => $pools, 'boutsByPool' => $boutsByPool,
            'individual' => RankingService::individual($cid), 'clubsPoints' => RankingService::clubTable($cid, 'points'),
            'clubsMedals' => RankingService::clubTable($cid, 'medals'), 'totals' => RankingService::medalTotals($cid),
            'showMats' => (bool) SettingsService::get($cid, 'public_mat_results'), 'tatamis' => CompetitionService::tatamis($cid),
            'lastUpdate' => RankingService::lastUpdate($cid),
        ]);
    }

    /** Classement public des clubs, actualisé automatiquement (page dédiée, affichable sur un écran de salle). */
    public function rankingBoard(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        return Response::html(\App\Core\View::render('rankings/board', [
            'c' => $c,
            'refresh' => (int) SettingsService::get((int) $c['id'], 'public_refresh_seconds'),
            'data' => self::rankingData($c),
            'clubName' => ContentService::site('club_name', 'JC Leforest'),
        ]));
    }

    public static function rankingData(array $c): array
    {
        $cid = (int) $c['id'];
        $map = fn ($row) => [
            'club' => $row['name'], 'city' => $row['city'], 'department' => $row['department'],
            'logo' => $row['logo_path'] ? url('/' . $row['logo_path']) : null,
            'points' => (int) $row['points'], 'gold' => (int) $row['gold'], 'silver' => (int) $row['silver'], 'bronze' => (int) $row['bronze'],
            'fourth' => (int) $row['fourth'], 'fifth' => (int) $row['fifth'], 'participants' => (int) $row['participants'],
            'rank_points' => (int) $row['rank_points'], 'rank_medals' => (int) $row['rank_medals'],
        ];
        $bouts = Database::one("SELECT COUNT(*) AS total, SUM(status = 'validated') AS done FROM bouts WHERE competition_id = ? AND status <> 'cancelled'", [$cid]);
        return [
            'competition' => ['name' => $c['name'], 'edition' => $c['edition_label'], 'status' => $c['status']],
            'points' => array_map($map, RankingService::clubTable($cid, 'points')),
            'medals' => array_map($map, RankingService::clubTable($cid, 'medals')),
            'totals' => RankingService::medalTotals($cid) + [
                'participants' => (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND status = 'confirmed' AND attendance_status = 'present'", [$cid]),
                'bouts_done' => (int) $bouts['done'], 'bouts_total' => (int) $bouts['total'],
            ],
            'updated_at' => RankingService::lastUpdate($cid),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function rankingApi(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        return $this->json(self::rankingData($c));
    }

    /** Direct public : combat en cours de chaque tapis (si activé). */
    public function live(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        if (!SettingsService::get((int) $c['id'], 'public_live')) {
            throw new HttpException(404, 'Le direct n\'est pas public pour cette compétition.');
        }
        return $this->view('public/live', ['title' => 'En direct — ' . $c['name'], 'c' => $c, 'scripts' => ['live.js']]);
    }

    public function liveApi(Request $r): Response
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        if (!SettingsService::get((int) $c['id'], 'public_live')) {
            throw new HttpException(404);
        }
        $out = [];
        foreach (CompetitionService::tatamis((int) $c['id']) as $t) {
            $state = \App\Services\TvTokenService::tvState($t + ['competition_name' => $c['name']]);
            $out[] = ['number' => (int) $t['number'], 'mode' => $state['mode'], 'current' => $state['current'], 'next' => array_slice($state['next'], 0, 2)];
        }
        return $this->json(['tatamis' => $out, 'server_now_ms' => \App\Services\BoutService::nowMs()]);
    }

    public function club(Request $r): Response
    {
        $staff = [];
        foreach (ContentService::items(null, 'club_staff') as $i) {
            $staff[$i['group_label'] ?? ''][] = $i;
        }
        return $this->view('public/club', [
            'title' => 'Notre club', 's' => ContentService::sectionMap(null, 'club'), 'staff' => $staff,
            'results' => ContentService::items(null, 'club_results'), 'alumni' => ContentService::items(null, 'club_alumni'),
            'courses' => ContentService::items(null, 'course_schedule'), 'photos' => ContentService::items(null, 'dojo_photos'),
            'history' => ContentService::history(),
        ]);
    }

    public function taiso(Request $r): Response
    {
        return $this->view('public/taiso', [
            'title' => 'Taïso', 's' => ContentService::sectionMap(null, 'taiso'),
            'infos' => ContentService::items(null, 'taiso_infos'), 'benefits' => ContentService::items(null, 'taiso_benefits'),
        ]);
    }

    public function partners(Request $r): Response
    {
        return $this->view('public/partners', ['title' => 'Partenaires et mécènes', 'partners' => ContentService::partnersByTier(null)]);
    }

    public function legal(Request $r): Response
    {
        return $this->view('public/text', ['title' => 'Mentions légales', 'body' => ContentService::site('legal_notice')]);
    }

    public function privacy(Request $r): Response
    {
        return $this->view('public/text', ['title' => 'Confidentialité', 'body' => ContentService::site('privacy_notice')]);
    }

    /** Documents et visuels (stockés hors racine publique). Les documents non publics exigent une connexion. */
    public function media(Request $r): Response
    {
        $d = DocumentService::find((int) $r->param('id'));
        if (!(int) $d['is_public'] && !Auth::check()) {
            throw new HttpException(404, 'Document introuvable.');
        }
        $path = DocumentService::dir() . '/' . $d['stored_name'];
        if (!is_file($path)) {
            throw new HttpException(404, 'Document introuvable.');
        }
        $download = isset($r->query['telecharger']);
        $resp = Response::file($path, $d['mime_type'], $d['original_name'], !$download);
        return $resp->header('Cache-Control', (int) $d['is_public'] ? 'public, max-age=86400' : 'private, no-store');
    }
}
