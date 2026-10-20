<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Jetons d'affichage TV : lecture seule, limités à un tapis, révocables, à durée de validité limitée.
 * Seul le hachage SHA-256 est stocké ; le lien complet n'est affiché qu'à la création.
 */
final class TvTokenService
{
    public static function create(int $tatamiId, ?string $label = null, ?int $days = null): array
    {
        $t = Database::one('SELECT * FROM tatamis WHERE id = ?', [$tatamiId]);
        if (!$t) {
            throw new HttpException(404, 'Tapis introuvable.');
        }
        CompetitionService::assertWritable((int) $t['competition_id']);
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $days = max(1, min(60, $days ?? (int) Config::get('app.tv_token_days', 7)));
        $id = Database::insert('tv_tokens', [
            'tatami_id' => $tatamiId,
            'token_hash' => hash('sha256', $token),
            'token_hint' => substr($token, 0, 6),
            'label' => $label ? mb_substr($label, 0, 80) : 'Écran tapis ' . $t['number'],
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
            'created_by' => Auth::id(),
        ]);
        AuditLogger::log('tv.create', 'tv_token', $id, null, ['tatami' => (int) $t['number'], 'days' => $days], null, (int) $t['competition_id']);
        return ['id' => $id, 'token' => $token, 'url' => absolute_url('/tv/tapis/' . $t['number'], ['jeton' => $token])];
    }

    /** Réinitialiser = révoquer les jetons actifs du tapis et en créer un nouveau. */
    public static function reset(int $tatamiId): array
    {
        foreach (Database::column('SELECT id FROM tv_tokens WHERE tatami_id = ? AND is_active = 1', [$tatamiId]) as $id) {
            self::revoke((int) $id);
        }
        return self::create($tatamiId);
    }

    public static function setActive(int $tokenId, bool $active): void
    {
        $t = Database::one('SELECT tv.*, m.competition_id FROM tv_tokens tv JOIN tatamis m ON m.id = tv.tatami_id WHERE tv.id = ?', [$tokenId]);
        if (!$t) {
            throw new HttpException(404);
        }
        if ($t['revoked_at'] !== null && $active) {
            throw new DomainError('Un jeton révoqué ne peut pas être réactivé : créez-en un nouveau.');
        }
        Database::run('UPDATE tv_tokens SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $tokenId]);
        AuditLogger::log($active ? 'tv.enable' : 'tv.disable', 'tv_token', $tokenId, null, null, null, (int) $t['competition_id']);
    }

    public static function revoke(int $tokenId): void
    {
        $t = Database::one('SELECT tv.*, m.competition_id FROM tv_tokens tv JOIN tatamis m ON m.id = tv.tatami_id WHERE tv.id = ?', [$tokenId]);
        if (!$t) {
            throw new HttpException(404);
        }
        Database::run('UPDATE tv_tokens SET is_active = 0, revoked_at = COALESCE(revoked_at, NOW()) WHERE id = ?', [$tokenId]);
        AuditLogger::log('tv.revoke', 'tv_token', $tokenId, null, null, null, (int) $t['competition_id']);
    }

    /** Vérifie le jeton pour le numéro de tapis demandé ; retourne le tapis et sa compétition. */
    public static function verify(string $token, int $tatamiNumber): array
    {
        if ($token === '' || strlen($token) > 64) {
            throw new HttpException(403, 'Lien d\'affichage invalide.');
        }
        $row = Database::one(
            'SELECT m.*, tv.id AS token_id, tv.is_active AS token_active, tv.expires_at, tv.last_seen_at, c.name AS competition_name, c.status AS competition_status
             FROM tv_tokens tv JOIN tatamis m ON m.id = tv.tatami_id JOIN competitions c ON c.id = m.competition_id
             WHERE tv.token_hash = ?',
            [hash('sha256', $token)]
        );
        if (!$row || (int) $row['number'] !== $tatamiNumber) {
            throw new HttpException(403, 'Lien d\'affichage invalide.');
        }
        if ((int) $row['token_active'] !== 1) {
            throw new HttpException(403, 'Cet affichage a été désactivé par l\'organisation.');
        }
        if (strtotime($row['expires_at']) < time()) {
            throw new HttpException(403, 'Ce lien d\'affichage a expiré : demandez un nouveau lien à l\'organisation.');
        }
        if ($row['last_seen_at'] === null || strtotime($row['last_seen_at']) < time() - 30) {
            Database::run('UPDATE tv_tokens SET last_seen_at = NOW(), last_seen_ip = ? WHERE id = ?', [$_SERVER['REMOTE_ADDR'] ?? null, $row['token_id']]);
        }
        return $row;
    }

    public static function forCompetition(int $competitionId): array
    {
        return Database::all(
            'SELECT tv.*, m.number AS tatami_number FROM tv_tokens tv JOIN tatamis m ON m.id = tv.tatami_id
             WHERE m.competition_id = ? ORDER BY m.number, tv.id DESC',
            [$competitionId]
        );
    }

    public static function status(array $token): string
    {
        if ($token['revoked_at'] !== null) {
            return 'revoked';
        }
        if ((int) $token['is_active'] !== 1) {
            return 'disabled';
        }
        if (strtotime($token['expires_at']) < time()) {
            return 'expired';
        }
        if ($token['last_seen_at'] !== null && strtotime($token['last_seen_at']) > time() - 90) {
            return 'online';
        }
        return 'idle';
    }

    /** État public d'un tapis pour l'écran TV (lecture seule). */
    public static function tvState(array $tatami): array
    {
        $cid = (int) $tatami['competition_id'];
        $current = $tatami['current_bout_id'] ? BoutService::state((int) $tatami['current_bout_id']) : null;
        // Après validation, « VICTOIRE » reste affiché quelques secondes puis l'écran présente le combat suivant,
        // indépendamment de ce que fait le commissaire sur son poste (paramètres, historique…).
        $resultSeconds = (int) SettingsService::get($cid, 'tv_result_seconds');
        if ($current && $current['status'] === 'validated' && $current['validated_at'] && strtotime($current['validated_at']) < time() - $resultSeconds) {
            $upcoming = BoutService::upcoming((int) $tatami['id'], 1, $current['id']);
            $current = $upcoming ? BoutService::state($upcoming[0]['id']) : null;
        }
        $next = BoutService::upcoming((int) $tatami['id'], 3, $current['id'] ?? null);
        $mode = 'waiting';
        if ($current) {
            $mode = match ($current['status']) {
                'scheduled' => 'next',
                'in_progress' => $current['clock']['running'] ? 'running' : 'paused',
                'finished', 'validated' => 'result',
                default => 'waiting',
            };
        }
        return [
            'mode' => $mode,
            'tatami' => ['id' => (int) $tatami['id'], 'number' => (int) $tatami['number'], 'name' => $tatami['name']],
            'competition' => ['id' => $cid, 'name' => $tatami['competition_name'] ?? Database::value('SELECT name FROM competitions WHERE id = ?', [$cid])],
            'display' => [
                'fighter_color' => SettingsService::get($cid, 'fighter_color'),
                'refresh_ms' => max(500, (int) SettingsService::get($cid, 'tv_refresh_ms')),
                'use_yuko' => (bool) ($current['model']['use_yuko'] ?? SettingsService::get($cid, 'score_model')['use_yuko'] ?? true),
                'shido_max' => (int) ($current['model']['shido_for_hansoku'] ?? SettingsService::get($cid, 'score_model')['shido_for_hansoku'] ?? 3),
                'osaekomi' => $current['model']['osaekomi'] ?? SettingsService::get($cid, 'score_model')['osaekomi'] ?? null,
            ],
            'current' => $current,
            'next' => $next,
            'server_now_ms' => BoutService::nowMs(),
        ];
    }
}
