<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Vidéo des tapis : état partagé de la vidéo-arbitrage (VAR), directs YouTube et replays par combat, appairage des
 * caméras téléphone / iPad (WebRTC). Le flux vidéo ne passe jamais par ce serveur.
 */
final class VideoService
{
    /** Statuts où la régie vidéo fonctionne (jour J). */
    public const DAY_STATUSES = ['registrations_closed', 'attendance', 'pool_preparation', 'in_progress'];

    /** Caméras : 1 = côté table (dans l'axe), 2 = côté opposé (dans l'axe). */
    public const SLOTS = [1 => 'Caméra 1 · côté table', 2 => 'Caméra 2 · côté opposé'];

    /** @var array<int, list<array>> directs par tapis (cache de la requête) */
    private static array $streams = [];

    /** Secondes montrées avant le hajime dans « Revoir le combat ». */
    private const REPLAY_LEAD_S = 5;

    /** Tapis piloté par la régie : droit « video.operate », tapis en service, compétition du jour J (sauf administrateur). */
    public static function tatami(int $tatamiId): array
    {
        if (!Auth::can('video.operate')) {
            throw new HttpException(403, 'Accès réservé à la vidéo-arbitrage.');
        }
        $t = Database::one(
            'SELECT t.*, c.name AS competition_name, c.status AS competition_status FROM tatamis t JOIN competitions c ON c.id = t.competition_id WHERE t.id = ?',
            [$tatamiId]
        ) ?? throw new HttpException(404, 'Tapis introuvable.');
        if (!Auth::can('results.correct') && (!in_array($t['competition_status'], self::DAY_STATUSES, true) || (int) $t['is_active'] !== 1)) {
            throw new HttpException(403, "Ce tapis n'est pas ouvert à la vidéo.");
        }
        return $t;
    }

    /** Compétitions du jour J avec au moins un tapis en service. */
    public static function competitions(): array
    {
        $in = implode(',', array_fill(0, count(self::DAY_STATUSES), '?'));
        return Database::all(
            "SELECT c.id, c.name, c.status, c.event_date, c.city,
                    (SELECT COUNT(*) FROM tatamis t WHERE t.competition_id = c.id AND t.is_active = 1) AS mats
             FROM competitions c
             WHERE c.status IN ($in) AND EXISTS (SELECT 1 FROM tatamis t WHERE t.competition_id = c.id AND t.is_active = 1)
             ORDER BY c.status = 'in_progress' DESC, c.event_date, c.name",
            self::DAY_STATUSES
        );
    }

    public static function tatamis(int $competitionId): array
    {
        return Database::all(
            "SELECT t.*, (t.video_seen_at IS NOT NULL AND t.video_seen_at > NOW() - INTERVAL 20 SECOND) AS regie_online
             FROM tatamis t WHERE t.competition_id = ? AND t.is_active = 1 ORDER BY t.number",
            [$competitionId]
        );
    }

    /** Résumé public (TV, application, commissaire) : direct YouTube et vidéo-arbitrage en cours. */
    public static function publicState(array $tatami): array
    {
        return [
            'youtube_id' => $tatami['youtube_video_id'] ?? null,
            'var' => (int) ($tatami['var_active'] ?? 0) === 1,
            'var_on_air' => (int) ($tatami['var_active'] ?? 0) === 1 && (int) ($tatami['var_on_air'] ?? 0) === 1,
            'var_since_ms' => isset($tatami['var_started_at_ms']) ? (int) $tatami['var_started_at_ms'] : null,
        ];
    }

    /** État complet pour la régie : affichage du combat (comme l'écran TV) + vidéo + repères de la VAR. */
    public static function regieState(array $tatami): array
    {
        Database::run('UPDATE tatamis SET video_seen_at = NOW() WHERE id = ?', [$tatami['id']]);
        $state = TvTokenService::tvState($tatami);
        $state['video'] = self::publicState($tatami) + [
            'stream' => self::currentStream((int) $tatami['id']),
            'cameras' => self::cameras((int) $tatami['id']),
        ];
        $state['markers'] = self::markers((int) $tatami['id'], BoutService::nowMs() - 15 * 60 * 1000);
        return $state;
    }

    /**
     * Repères de la ligne de temps VAR : hajime / fin de chaque combat et actions du commissaire (avantages, shidos,
     * osaekomi, matte) depuis $sinceMs.
     */
    public static function markers(int $tatamiId, int $sinceMs): array
    {
        $out = [];
        foreach (Database::all(
            'SELECT b.id, b.started_at_ms, b.ended_at_ms, aa.last_name AS a_name, ab.last_name AS b_name
             FROM bouts b JOIN registrations ra ON ra.id = b.a_registration_id JOIN athletes aa ON aa.id = ra.athlete_id
             JOIN registrations rb ON rb.id = b.b_registration_id JOIN athletes ab ON ab.id = rb.athlete_id
             WHERE b.tatami_id = ? AND b.started_at_ms IS NOT NULL AND (b.ended_at_ms IS NULL OR b.ended_at_ms >= ?)',
            [$tatamiId, $sinceMs]
        ) as $b) {
            $out[] = ['at_ms' => (int) $b['started_at_ms'], 'kind' => 'start', 'side' => null, 'label' => 'Hajime · ' . $b['a_name'] . ' / ' . $b['b_name'], 'bout_id' => (int) $b['id']];
            if ($b['ended_at_ms'] !== null) {
                $out[] = ['at_ms' => (int) $b['ended_at_ms'], 'kind' => 'end', 'side' => null, 'label' => 'Fin du combat', 'bout_id' => (int) $b['id']];
            }
        }
        $names = ['score:ippon' => 'Ippon', 'score:wazaari' => 'Waza-ari', 'score:yuko' => 'Yuko', 'score:shido' => 'Shido', 'osaekomi' => 'Osaekomi', 'pause' => 'Matte', 'toketa' => 'Toketa'];
        foreach (Database::all(
            "SELECT e.bout_id, e.action, e.side, e.at_ms, e.payload_json FROM score_events e JOIN bouts b ON b.id = e.bout_id
             WHERE b.tatami_id = ? AND e.at_ms >= ? AND e.action IN ('score:ippon','score:wazaari','score:yuko','score:shido','osaekomi','pause','toketa')
             ORDER BY e.at_ms",
            [$tatamiId, $sinceMs]
        ) as $e) {
            $delta = (int) (json_decode((string) $e['payload_json'], true)['delta'] ?? 1);
            $out[] = ['at_ms' => (int) $e['at_ms'], 'kind' => $e['action'], 'side' => $e['side'],
                'label' => ($delta < 0 ? 'Annulation ' : '') . ($names[$e['action']] ?? $e['action']), 'bout_id' => (int) $e['bout_id']];
        }
        usort($out, fn ($x, $y) => $x['at_ms'] <=> $y['at_ms']);
        return $out;
    }

    /** Début / fin de la vidéo-arbitrage ; « à l'antenne » = le ralenti remplace le direct sur YouTube. */
    public static function setVar(array $tatami, bool $active, bool $onAir): array
    {
        $was = (int) $tatami['var_active'] === 1;
        Database::run(
            'UPDATE tatamis SET var_active = ?, var_on_air = ?, var_started_at_ms = ? WHERE id = ?',
            [$active ? 1 : 0, $active && $onAir ? 1 : 0, $active ? ($was ? $tatami['var_started_at_ms'] : BoutService::nowMs()) : null, $tatami['id']]
        );
        if ($active !== $was) {
            AuditLogger::log($active ? 'video.var_start' : 'video.var_end', 'tatami', (int) $tatami['id'], null,
                ['bout_id' => $tatami['current_bout_id'] ? (int) $tatami['current_bout_id'] : null, 'on_air' => $onAir], null, (int) $tatami['competition_id']);
        }
        return self::publicState(Database::one('SELECT * FROM tatamis WHERE id = ?', [$tatami['id']]));
    }

    /** Identifiant d'une vidéo YouTube à partir d'un lien (watch, youtu.be, live, embed, studio) ou de l'identifiant seul. */
    public static function youtubeId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $input)) {
            return $input;
        }
        if (preg_match('~(?:youtu\.be/|[?&]v=|/live/|/embed/|/video/|/shorts/)([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Début d'un direct YouTube sur le tapis : repère 0:00 des replays. Un nouveau direct clôt le précédent.
     */
    public static function startStream(array $tatami, string $link): array
    {
        $id = self::youtubeId($link) ?? throw new DomainError('Lien YouTube non reconnu : collez le lien du direct (youtube.com/live/… ou youtu.be/…).');
        $now = BoutService::nowMs();
        Database::transaction(function () use ($tatami, $id, $now) {
            Database::run('UPDATE video_streams SET ended_at_ms = ? WHERE tatami_id = ? AND ended_at_ms IS NULL', [$now, $tatami['id']]);
            Database::insert('video_streams', ['tatami_id' => $tatami['id'], 'youtube_video_id' => $id, 'started_at_ms' => $now, 'created_by' => Auth::id()]);
            Database::run('UPDATE tatamis SET youtube_video_id = ?, stream_started_at_ms = ? WHERE id = ?', [$id, $now, $tatami['id']]);
        });
        AuditLogger::log('video.stream_start', 'tatami', (int) $tatami['id'], null, ['youtube_video_id' => $id], null, (int) $tatami['competition_id']);
        return self::currentStream((int) $tatami['id']);
    }

    public static function stopStream(array $tatami): void
    {
        Database::run('UPDATE video_streams SET ended_at_ms = ? WHERE tatami_id = ? AND ended_at_ms IS NULL', [BoutService::nowMs(), $tatami['id']]);
        Database::run('UPDATE tatamis SET youtube_video_id = NULL, stream_started_at_ms = NULL WHERE id = ?', [$tatami['id']]);
        AuditLogger::log('video.stream_stop', 'tatami', (int) $tatami['id'], null, null, null, (int) $tatami['competition_id']);
    }

    /** Ajustement des replays (le direct YouTube a démarré quelques secondes avant ou après le clic). */
    public static function setOffset(array $tatami, int $seconds): void
    {
        Database::run('UPDATE tatamis SET stream_offset_s = ? WHERE id = ?', [max(-600, min(600, $seconds)), $tatami['id']]);
    }

    public static function currentStream(int $tatamiId): ?array
    {
        $s = Database::one('SELECT * FROM video_streams WHERE tatami_id = ? AND ended_at_ms IS NULL ORDER BY id DESC LIMIT 1', [$tatamiId]);
        return $s ? ['youtube_id' => $s['youtube_video_id'], 'started_at_ms' => (int) $s['started_at_ms'],
            'offset_s' => (int) Database::value('SELECT stream_offset_s FROM tatamis WHERE id = ?', [$tatamiId])] : null;
    }

    /**
     * « Revoir le combat » : vidéo YouTube du direct pendant lequel le combat a commencé et position (secondes) du hajime
     * moins quelques secondes. null si le combat n'a pas été filmé.
     */
    public static function replay(array $bout): ?array
    {
        if (empty($bout['tatami_id']) || empty($bout['started_at_ms'])) {
            return null;
        }
        $start = (int) $bout['started_at_ms'];
        // Directs du tapis chargés une fois par requête (listes de combats)
        $tid = (int) $bout['tatami_id'];
        self::$streams[$tid] ??= Database::all(
            'SELECT s.*, t.stream_offset_s FROM video_streams s JOIN tatamis t ON t.id = s.tatami_id WHERE s.tatami_id = ? ORDER BY s.id DESC',
            [$tid]
        );
        $s = null;
        foreach (self::$streams[$tid] as $row) {
            if ((int) $row['started_at_ms'] <= $start && ($row['ended_at_ms'] === null || (int) $row['ended_at_ms'] >= $start)) {
                $s = $row;
                break;
            }
        }
        if (!$s) {
            return null;
        }
        $at = intdiv($start - (int) $s['started_at_ms'], 1000) + (int) $s['stream_offset_s'] - self::REPLAY_LEAD_S;
        return ['youtube_id' => $s['youtube_video_id'], 'start_s' => max(0, $at), 'live' => $s['ended_at_ms'] === null];
    }

    // ------------------------------------------------------------------ Caméras téléphone / iPad (WebRTC)

    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Nouveau code d'appairage pour l'emplacement de caméra (l'ancien téléphone est déconnecté). */
    public static function createCamera(array $tatami, int $slot): string
    {
        if (!isset(self::SLOTS[$slot])) {
            throw new DomainError('Emplacement de caméra inconnu.');
        }
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
        }
        Database::run(
            'INSERT INTO video_cameras (tatami_id, slot, code_hash, expires_at, created_by) VALUES (?, ?, ?, NOW() + INTERVAL 1 DAY, ?)
             ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at), created_by = VALUES(created_by),
                offer_sdp = NULL, answer_sdp = NULL, session = NULL, device_label = NULL, last_seen_at = NULL',
            [$tatami['id'], $slot, hash('sha256', $code), Auth::id()]
        );
        AuditLogger::log('video.camera_code', 'tatami', (int) $tatami['id'], null, ['slot' => $slot], null, (int) $tatami['competition_id']);
        return $code;
    }

    public static function removeCamera(array $tatami, int $slot): void
    {
        Database::run('DELETE FROM video_cameras WHERE tatami_id = ? AND slot = ?', [$tatami['id'], $slot]);
    }

    /** Caméra appairée par son code (page publique du téléphone). */
    public static function cameraByCode(string $code): array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $cam = strlen($code) === 6 ? Database::one(
            'SELECT v.*, t.number AS tatami_number, c.name AS competition_name FROM video_cameras v JOIN tatamis t ON t.id = v.tatami_id
             JOIN competitions c ON c.id = t.competition_id WHERE v.code_hash = ? AND v.expires_at > NOW()',
            [hash('sha256', $code)]
        ) : null;
        return $cam ?? throw new HttpException(404, 'Code caméra inconnu ou expiré : demandez un nouveau code à la régie vidéo.');
    }

    /** Le téléphone publie son offre WebRTC (nouvelle tentative = nouvelle session). */
    public static function cameraOffer(array $cam, string $session, string $sdp, string $label): void
    {
        if (!preg_match('/^[a-z0-9]{8,16}$/', $session) || !str_starts_with($sdp, 'v=0') || strlen($sdp) > 60000) {
            throw new DomainError('Offre vidéo invalide.');
        }
        Database::run(
            'UPDATE video_cameras SET offer_sdp = ?, answer_sdp = NULL, session = ?, device_label = ?, last_seen_at = NOW() WHERE id = ?',
            [$sdp, $session, mb_substr($label, 0, 120) ?: null, $cam['id']]
        );
    }

    /** Réponse de la régie pour la session en cours, que le téléphone récupère. */
    public static function cameraAnswer(array $tatami, int $slot, string $session, string $sdp): void
    {
        if (!str_starts_with($sdp, 'v=0') || strlen($sdp) > 60000) {
            throw new DomainError('Réponse vidéo invalide.');
        }
        $n = Database::run('UPDATE video_cameras SET answer_sdp = ? WHERE tatami_id = ? AND slot = ? AND session = ?', [$sdp, $tatami['id'], $slot, $session])->rowCount();
        if ($n === 0) {
            throw new DomainError('Cette caméra a changé de session : elle va se reconnecter.', 409);
        }
    }

    public static function cameraPing(array $cam): void
    {
        Database::run('UPDATE video_cameras SET last_seen_at = NOW() WHERE id = ?', [$cam['id']]);
    }

    /** Emplacements de caméra du tapis, avec l'offre en attente de réponse (régie). */
    public static function cameras(int $tatamiId): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM video_cameras WHERE tatami_id = ? AND expires_at > NOW() ORDER BY slot', [$tatamiId]) as $c) {
            $online = $c['last_seen_at'] !== null && strtotime($c['last_seen_at']) > time() - 15;
            $out[] = [
                'slot' => (int) $c['slot'], 'device' => $c['device_label'], 'session' => $c['session'], 'online' => $online,
                'offer' => $c['offer_sdp'] !== null && $c['answer_sdp'] === null ? $c['offer_sdp'] : null,
            ];
        }
        return $out;
    }
}
