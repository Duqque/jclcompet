<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Controllers\Public\SiteController;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BoutService;
use App\Services\CategoryService;
use App\Services\ClubColorService;
use App\Services\CompetitionService;
use App\Services\PoolService;
use App\Services\RankingService;
use App\Services\SettingsService;
use App\Services\TvTokenService;

/**
 * API publique en lecture seule de l'application mobile « JCL Live » (suivi des combats en direct).
 * Aucune donnée privée : ni date de naissance, ni licence, ni coordonnées. Les photos des judokas ne sont transmises
 * que si l'organisation les a autorisées (Paramètres › photos sur l'annonce des podiums).
 * Le direct respecte « public_live » ; poules, combats et judokas respectent « pools_published ».
 * Les chemins renvoyés (logos, photos) sont relatifs : l'application les résout avec l'adresse du serveur.
 */
final class MobileController extends Controller
{
    private const METHOD_SHORT = ['ippon' => 'Ippon', 'wazaari' => 'Waza-ari', 'yuko' => 'Yuko', 'decision' => 'Décision', 'hansoku_make' => 'Hansoku-make',
        'fusen_gachi' => 'Forfait', 'kiken_gachi' => 'Abandon', 'shido' => 'Shido'];

    // ------------------------------------------------------------------ Accueil, compétitions

    public function home(Request $r): Response
    {
        $comps = $this->competitions();
        $live = array_values(array_filter($comps, fn ($c) => $c['live']));
        $out = ['live' => [], 'competitions' => $comps, 'server_now_ms' => BoutService::nowMs()];
        foreach ($live as $c) {
            $full = CompetitionService::findBySlug($c['slug']);
            $out['live'][] = $c + ['mats' => SettingsService::get((int) $full['id'], 'public_live') ? $this->mats($full, 1) : []];
        }
        return $this->out($out, 'no-store');
    }

    public function competitionsList(Request $r): Response
    {
        return $this->out(['competitions' => $this->competitions()], 'max-age=30');
    }

    public function competition(Request $r): Response
    {
        $c = $this->comp($r);
        $cid = (int) $c['id'];
        $bouts = Database::one("SELECT COUNT(*) AS total, SUM(status = 'validated') AS done FROM bouts WHERE competition_id = ? AND status <> 'cancelled'", [$cid]);
        $schedule = array_map(fn ($i) => ['time' => $i['title'], 'categories' => $i['subtitle'], 'details' => $i['body'], 'block' => $i['group_label']],
            Database::all("SELECT title, subtitle, body, group_label FROM content_items WHERE competition_id = ? AND list_key = 'schedule' AND is_published = 1 ORDER BY sort_order, id", [$cid]));
        return $this->out($this->summary($c) + [
            'description' => $c['description'],
            'practical_info' => $c['practical_info'],
            'address' => $c['address'],
            'start_time' => $c['start_time'] ? substr((string) $c['start_time'], 0, 5) : null,
            'bouts_total' => (int) ($bouts['total'] ?? 0),
            'bouts_done' => (int) ($bouts['done'] ?? 0),
            'pools_published' => $this->poolsPublished($c),
            'live_public' => (bool) SettingsService::get($cid, 'public_live'),
            'schedule' => $schedule,
            'links' => [
                'page' => url('/competitions/' . $c['slug']),
                'results' => url('/competitions/' . $c['slug'] . '/resultats'),
                'board' => url('/classement/' . $c['slug']),
                'programme_pdf' => $c['regulation_document_id'] ? url('/media/' . $c['regulation_document_id']) : null,
            ],
        ], 'max-age=30');
    }

    // ------------------------------------------------------------------ Direct

    public function live(Request $r): Response
    {
        $c = $this->comp($r);
        if (!SettingsService::get((int) $c['id'], 'public_live')) {
            throw new HttpException(404, 'Le direct n\'est pas public pour cette compétition.');
        }
        return $this->out(['competition' => $this->summary($c), 'mats' => $this->mats($c, 3), 'server_now_ms' => BoutService::nowMs()], 'no-store');
    }

    /** Empreinte des résultats : change dès qu'un combat est validé ou corrigé (rafraîchissement et notifications). */
    public function stamp(Request $r): Response
    {
        $c = $this->comp($r);
        return $this->out(['stamp' => SiteController::rankingStamp((int) $c['id']), 'server_now_ms' => BoutService::nowMs()], 'no-store');
    }

    // ------------------------------------------------------------------ Combats

    public function bouts(Request $r): Response
    {
        $c = $this->requirePools($r);
        $where = ['b.competition_id = ?', "b.status <> 'cancelled'"];
        $p = [(int) $c['id']];
        if (!empty($r->query['tapis'])) {
            $where[] = 't.number = ?';
            $p[] = (int) $r->query['tapis'];
        }
        $statut = (string) ($r->query['statut'] ?? '');
        if ($statut === 'a_venir') {
            $where[] = "b.status IN ('scheduled','in_progress','finished')";
        } elseif ($statut === 'termines') {
            $where[] = "b.status = 'validated'";
        }
        if (!empty($r->query['poule'])) {
            $where[] = 'p.number = ?';
            $p[] = (int) $r->query['poule'];
        }
        $rows = Database::all(
            'SELECT b.id FROM bouts b JOIN pools p ON p.id = b.pool_id LEFT JOIN tatamis t ON t.id = b.tatami_id WHERE ' . implode(' AND ', $where) .
            ($statut === 'termines' ? ' ORDER BY b.validated_at DESC, b.id DESC' : ' ORDER BY t.number, b.order_on_tatami, b.id') . ' LIMIT 600',
            $p
        );
        return $this->out(['bouts' => array_map(fn ($x) => $this->bout(BoutService::state((int) $x['id'], false)), $rows)], 'no-store');
    }

    private function bout(array $s): array
    {
        $m = $s['win_method'] ?? null;
        $fighter = fn (array $f) => [
            'registration_id' => $f['registration_id'],
            'first_name' => $f['first_name'], 'last_name' => mb_strtoupper($f['last_name']),
            'club' => $f['club'], 'club_short' => $f['club_short'] ?: $f['club'], 'club_logo' => $f['club_logo'],
            'ippon' => $f['ippon'], 'wazaari' => $f['wazaari'], 'yuko' => $f['yuko'], 'shido' => $f['shido'], 'hansoku' => $f['hansoku'],
            'score' => $f['ippon'] . $f['wazaari'] . $f['yuko'] . ($f['shido'] > 0 ? '-' . $f['shido'] : ''),
        ];
        return [
            'id' => $s['id'], 'status' => $s['status'], 'status_label' => $s['status_label'], 'phase' => $s['phase'],
            'mat' => $s['tatami_number'], 'order_on_mat' => $s['order_on_tatami'],
            'pool_number' => (int) preg_replace('/^.*Poule /u', '', (string) $s['pool_label']), 'pool_id' => $s['pool_id'],
            'pool_position' => $s['pool_position'], 'pool_bouts' => $s['pool_bouts'],
            'category' => $s['category'], 'weight' => $s['weight_label'],
            'a' => $fighter($s['a']), 'b' => $fighter($s['b']),
            'winner' => $s['winner_side'], 'method' => $m, 'method_label' => $m ? (self::METHOD_SHORT[$m] ?? $s['win_method_label']) : null,
            'clock' => $s['clock'] ?? null, 'osaekomi' => $s['osaekomi'] ?? null,
            'model' => isset($s['model']) ? ['osaekomi' => $s['model']['osaekomi'], 'use_yuko' => $s['model']['use_yuko'], 'shido_max' => $s['model']['shido_for_hansoku']] : null,
        ];
    }

    public function boutDetail(Request $r): Response
    {
        $id = (int) $r->param('id');
        $comp = Database::one('SELECT c.* FROM bouts b JOIN competitions c ON c.id = b.competition_id WHERE b.id = ?', [$id]) ?? throw new HttpException(404, 'Combat introuvable.');
        if (!$this->poolsPublished($comp)) {
            throw new HttpException(404, 'Combat introuvable.');
        }
        $s = BoutService::state($id);
        $events = Database::all("SELECT action, side, clock_ms, created_at FROM score_events WHERE bout_id = ? ORDER BY id", [$id]);
        $timeline = [];
        foreach ($events as $e) {
            [$verb, $kind] = array_pad(explode(':', $e['action'], 2), 2, null);
            $label = match (true) {
                $verb === 'start' => 'Hajime',
                $verb === 'pause' => 'Matte',
                $verb === 'osaekomi' => 'Osaekomi',
                $verb === 'toketa' => 'Toketa',
                $verb === 'golden_score' => 'Golden score',
                $verb === 'finish' => 'Fin du combat',
                $verb === 'validate' => 'Résultat validé',
                $verb === 'forfeit' => 'Forfait',
                $verb === 'score' && $kind !== null => self::METHOD_SHORT[$kind] ?? ucfirst($kind),
                default => null,
            };
            if ($label === null || $verb === 'reset') {
                continue;
            }
            $timeline[] = ['type' => $verb === 'score' ? $kind : $verb, 'label' => $label, 'side' => $e['side'], 'clock_ms' => $e['clock_ms'] === null ? null : (int) $e['clock_ms']];
        }
        $other = function (int $rid) use ($id) {
            $rows = Database::all("SELECT id FROM bouts WHERE (a_registration_id = ? OR b_registration_id = ?) AND id <> ? AND status <> 'cancelled' ORDER BY order_on_tatami", [$rid, $rid, $id]);
            return array_map(fn ($x) => $this->bout(BoutService::state((int) $x['id'], false)), $rows);
        };
        return $this->out([
            'bout' => $this->bout($s), 'timeline' => $timeline, 'competition' => $this->summary($comp),
            'other_a' => $other($s['a']['registration_id']), 'other_b' => $other($s['b']['registration_id']),
            'server_now_ms' => BoutService::nowMs(),
        ], 'no-store');
    }

    // ------------------------------------------------------------------ Poules

    public function pools(Request $r): Response
    {
        $c = $this->requirePools($r);
        $g = PoolService::activeGeneration((int) $c['id']);
        $sheets = $g ? PoolService::sheets(PoolService::pools((int) $g['id'])) : [];
        usort($sheets, fn ($x, $y) => [(int) $x['age_sort'], $x['sex'], (int) ($x['weight_sort'] ?? 0), (int) $x['number']] <=> [(int) $y['age_sort'], $y['sex'], (int) ($y['weight_sort'] ?? 0), (int) $y['number']]);
        $colors = $this->clubColors();
        $out = [];
        foreach ($sheets as $p) {
            $athletes = [];
            foreach ($p['athletes'] as $i => $a) {
                $st = $p['standing'][(int) $a['registration_id']] ?? null;
                $athletes[] = [
                    'seed' => $i + 1, 'registration_id' => (int) $a['registration_id'],
                    'first_name' => $a['first_name'], 'last_name' => mb_strtoupper($a['last_name']),
                    'club' => $a['club_name'] ?? $a['club_exception_label'], 'club_short' => $a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label']),
                    'club_logo' => $a['club_logo'] ? url('/' . ltrim($a['club_logo'], '/')) : null, 'club_color' => $colors[(int) $a['club_id']] ?? ClubColorService::FALLBACK,
                    'weight' => $a['reference_weight_kg'] !== null ? (float) $a['reference_weight_kg'] : null,
                    'wins' => $st ? (int) $st['wins'] : 0, 'points' => $st ? (int) $st['value_points'] : 0,
                    'rank' => $p['complete'] && $st && !$st['excluded'] ? (int) $st['rank'] : null,
                ];
            }
            $cells = [];
            foreach ($p['cells'] as $row => $cols) {
                foreach ($cols as $col => $b) {
                    $side = $b['side'];
                    $cells[] = ['row' => $row, 'col' => $col, 'bout_id' => (int) $b['id'], 'status' => $b['status'],
                        'won' => $b['status'] === 'validated' ? $b['winner_side'] === $side : null,
                        'score' => $b['status'] === 'validated' ? (int) $b[$side . '_ippon'] . (int) $b[$side . '_wazaari'] . (int) $b[$side . '_yuko'] . ((int) $b[$side . '_shido'] > 0 ? '-' . (int) $b[$side . '_shido'] : '') : null,
                        'method' => $b['status'] === 'validated' && $b['winner_side'] === $side ? (self::METHOD_SHORT[$b['win_method']] ?? null) : null,
                        'order' => (int) $b['order_in_pool']];
                }
            }
            $out[] = [
                'id' => (int) $p['id'], 'number' => (int) $p['number'], 'mat' => $p['tatami_number'] === null ? null : (int) $p['tatami_number'],
                'category' => CategoryService::label(['name' => $p['age_name'], 'name_m' => $p['name_m'], 'name_f' => $p['name_f']], $p['sex']),
                'weight' => $p['weight_label'], 'bouts_done' => (int) $p['bouts_done'], 'bouts_total' => (int) $p['bouts_total'],
                'complete' => (bool) $p['complete'], 'athletes' => $athletes, 'cells' => $cells,
                'order' => array_map(fn ($b) => ['bout_id' => (int) $b['id'], 'order' => (int) $b['order_in_pool'], 'a' => $b['seed_a'], 'b' => $b['seed_b'], 'status' => $b['status'], 'winner' => $b['winner_side']], $p['bouts']),
            ];
        }
        return $this->out(['pools' => $out], 'no-store');
    }

    // ------------------------------------------------------------------ Judokas

    public function athletes(Request $r): Response
    {
        $c = $this->requirePools($r);
        $q = mb_strtolower(trim((string) ($r->query['q'] ?? '')));
        return $this->out(['athletes' => array_values(array_filter($this->athleteRows($c), fn ($a) => $q === '' ||
            str_contains(mb_strtolower($a['first_name'] . ' ' . $a['last_name'] . ' ' . $a['last_name'] . ' ' . $a['first_name'] . ' ' . $a['club']), $q)))], 'max-age=20');
    }

    public function athlete(Request $r): Response
    {
        $rid = (int) $r->param('id');
        $comp = Database::one('SELECT c.* FROM registrations r JOIN competitions c ON c.id = r.competition_id WHERE r.id = ?', [$rid]) ?? throw new HttpException(404, 'Judoka introuvable.');
        if (!$this->poolsPublished($comp)) {
            throw new HttpException(404, 'Judoka introuvable.');
        }
        $row = array_values(array_filter($this->athleteRows($comp), fn ($a) => $a['registration_id'] === $rid))[0] ?? throw new HttpException(404, 'Judoka introuvable.');
        $bouts = Database::all("SELECT id FROM bouts WHERE (a_registration_id = ? OR b_registration_id = ?) AND status <> 'cancelled' ORDER BY order_on_tatami", [$rid, $rid]);
        return $this->out(['athlete' => $row, 'competition' => $this->summary($comp), 'bouts' => array_map(fn ($x) => $this->bout(BoutService::state((int) $x['id'], false)), $bouts)], 'no-store');
    }

    /** Suivi des judokas favoris : prochain combat et dernier résultat de chacun (notifications de l'application). */
    public function follow(Request $r): Response
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) ($r->query['ids'] ?? ''))))), 0, 50);
        $out = [];
        foreach ($ids as $rid) {
            $comp = Database::one('SELECT c.* FROM registrations r JOIN competitions c ON c.id = r.competition_id WHERE r.id = ?', [$rid]);
            if (!$comp || !$this->poolsPublished($comp)) {
                continue;
            }
            $next = Database::value("SELECT id FROM bouts WHERE (a_registration_id = ? OR b_registration_id = ?) AND status IN ('scheduled','in_progress','finished') ORDER BY order_on_tatami LIMIT 1", [$rid, $rid]);
            $last = Database::value("SELECT id FROM bouts WHERE (a_registration_id = ? OR b_registration_id = ?) AND status = 'validated' ORDER BY validated_at DESC, id DESC LIMIT 1", [$rid, $rid]);
            $before = 0;
            if ($next) {
                $n = Database::one('SELECT tatami_id, order_on_tatami FROM bouts WHERE id = ?', [$next]);
                $before = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE tatami_id = ? AND status = 'scheduled' AND order_on_tatami < ?", [$n['tatami_id'], $n['order_on_tatami']]);
            }
            $out[] = ['registration_id' => $rid, 'competition' => $comp['slug'],
                'next' => $next ? $this->bout(BoutService::state((int) $next, false)) + ['bouts_before' => $before] : null,
                'last' => $last ? $this->bout(BoutService::state((int) $last, false)) : null];
        }
        return $this->out(['follow' => $out, 'server_now_ms' => BoutService::nowMs()], 'no-store');
    }

    // ------------------------------------------------------------------ Résultats

    public function results(Request $r): Response
    {
        $c = $this->comp($r);
        $cid = (int) $c['id'];
        $podiums = SiteController::podiums($c);
        $map = fn ($row) => ['club' => $row['name'], 'club_short' => $row['short_name'] ?: $row['name'], 'club_logo' => $row['logo_path'] ? url('/' . ltrim($row['logo_path'], '/')) : null,
            'points' => (int) $row['points'], 'gold' => (int) $row['gold'], 'silver' => (int) $row['silver'], 'bronze' => (int) $row['bronze'],
            'participants' => (int) $row['participants'], 'rank_points' => (int) $row['rank_points'], 'rank_medals' => (int) $row['rank_medals']];
        return $this->out([
            'podiums' => $podiums,
            'clubs_points' => array_map($map, RankingService::clubTable($cid, 'points', true)),
            'clubs_medals' => array_map($map, RankingService::clubTable($cid, 'medals', true)),
            'totals' => array_map('intval', RankingService::medalTotals($cid)),
            'stamp' => SiteController::rankingStamp($cid),
        ], 'no-store');
    }

    // ------------------------------------------------------------------ Outils

    private function out(array $data, string $cache): Response
    {
        return $this->json($data)->header('Cache-Control', $cache === 'no-store' ? 'no-store' : 'public, ' . $cache);
    }

    private function comp(Request $r): array
    {
        $c = CompetitionService::findBySlug((string) $r->param('slug'));
        if (in_array($c['status'], ['draft'], true)) {
            throw new HttpException(404, 'Compétition introuvable.');
        }
        return $c;
    }

    private function poolsPublished(array $c): bool
    {
        if (in_array($c['status'], ['in_progress', 'finished', 'archived'], true)) {
            return true;
        }
        return (bool) SettingsService::get((int) $c['id'], 'pools_published');
    }

    private function requirePools(Request $r): array
    {
        $c = $this->comp($r);
        if (!$this->poolsPublished($c)) {
            throw new HttpException(404, 'Les poules ne sont pas encore publiées.');
        }
        return $c;
    }

    private function competitions(): array
    {
        $rows = Database::all("SELECT * FROM competitions WHERE status NOT IN ('draft') ORDER BY status = 'in_progress' DESC, event_date DESC");
        return array_map(fn ($c) => $this->summary($c), $rows);
    }

    private function summary(array $c): array
    {
        $cid = (int) $c['id'];
        return [
            'id' => $cid, 'slug' => $c['slug'], 'name' => $c['name'], 'edition' => $c['edition_label'],
            'date' => $c['event_date'], 'venue' => $c['venue'], 'city' => $c['city'],
            'status' => $c['status'], 'status_label' => CompetitionService::statusLabel($c['status']),
            'live' => $c['status'] === 'in_progress',
            'registrations_open' => CompetitionService::registrationWindowOpen($c),
            'mats_count' => (int) $c['mat_count'],
            'clubs_count' => (int) Database::value("SELECT COUNT(DISTINCT club_id) FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed') AND club_id IS NOT NULL", [$cid]),
            'athletes_count' => (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')", [$cid]),
            'poster' => !empty($c['cover_document_id']) ? url('/media/' . $c['cover_document_id']) : null,
        ];
    }

    private function mats(array $c, int $next): array
    {
        $out = [];
        foreach (CompetitionService::tatamis((int) $c['id']) as $t) {
            $state = TvTokenService::tvState($t + ['competition_name' => $c['name']]);
            $out[] = [
                'number' => (int) $t['number'], 'mode' => $state['mode'],
                'current' => $state['current'] ? $this->bout($state['current']) : null,
                'next' => array_map(fn ($b) => $this->bout($b), array_slice($state['next'], 0, $next)),
            ];
        }
        return $out;
    }

    private function clubColors(): array
    {
        $out = [];
        foreach (Database::all('SELECT id, logo_path, brand_color FROM clubs') as $cl) {
            $out[(int) $cl['id']] = ClubColorService::forClub($cl);
        }
        return $out;
    }

    private function athleteRows(array $c): array
    {
        $cid = (int) $c['id'];
        $photos = (bool) SettingsService::get($cid, 'public_podium_photos');
        $colors = $this->clubColors();
        $rows = Database::all(
            "SELECT r.id, a.first_name, a.last_name, a.sex, a.grade, a.photo_document_id, r.club_id, r.club_exception_label, r.attendance_status,
                    c.name AS club_name, c.short_name AS club_short, c.logo_path AS club_logo,
                    ac.name AS age_name, ac.name_m, ac.name_f, ac.sort_order AS age_sort, wc.label AS weight_label,
                    p.number AS pool_number, t.number AS mat, ar.place, ar.medal
             FROM registrations r JOIN athletes a ON a.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id
             JOIN age_categories ac ON ac.id = r.age_category_id LEFT JOIN weight_categories wc ON wc.id = r.weight_category_id
             LEFT JOIN pool_athletes pa ON pa.registration_id = r.id
             LEFT JOIN pools p ON p.id = pa.pool_id AND p.generation_id = pa.generation_id
             LEFT JOIN pool_generations g ON g.id = pa.generation_id
             LEFT JOIN tatamis t ON t.id = p.tatami_id
             LEFT JOIN athlete_rankings ar ON ar.registration_id = r.id
             WHERE r.competition_id = ? AND r.status = 'confirmed' AND (g.id IS NULL OR g.status IN ('draft','validated'))
             ORDER BY a.last_name, a.first_name",
            [$cid]
        );
        $seen = [];
        $out = [];
        foreach ($rows as $a) {
            if (isset($seen[$a['id']])) {
                continue;
            }
            $seen[$a['id']] = true;
            $out[] = [
                'registration_id' => (int) $a['id'], 'competition' => $c['slug'],
                'first_name' => $a['first_name'], 'last_name' => mb_strtoupper($a['last_name']), 'sex' => $a['sex'], 'grade' => $a['grade'],
                'category' => CategoryService::label(['name' => $a['age_name'], 'name_m' => $a['name_m'], 'name_f' => $a['name_f']], $a['sex']),
                'weight' => $a['weight_label'],
                'club' => $a['club_name'] ?? $a['club_exception_label'], 'club_short' => $a['club_short'] ?: ($a['club_name'] ?? $a['club_exception_label']),
                'club_logo' => $a['club_logo'] ? url('/' . ltrim($a['club_logo'], '/')) : null, 'club_color' => $colors[(int) $a['club_id']] ?? ClubColorService::FALLBACK,
                'photo' => $photos && $a['photo_document_id'] && $a['place'] !== null ? url('/classement/' . $c['slug'] . '/photo/' . $a['id']) : null,
                'pool_number' => $a['pool_number'] === null ? null : (int) $a['pool_number'], 'mat' => $a['mat'] === null ? null : (int) $a['mat'],
                'present' => $a['attendance_status'] === 'present',
                'place' => $a['place'] === null ? null : (int) $a['place'], 'medal' => $a['medal'],
            ];
        }
        return $out;
    }
}
