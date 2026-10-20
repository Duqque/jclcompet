<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Moteur de combat. L'état de référence (chronomètre, scores, pénalités) est en base :
 *  - chronomètre : clock_elapsed_ms accumulé + (maintenant − clock_started_at_ms) quand il tourne ;
 *  - chaque action porte la version connue du client (verrou optimiste) et un identifiant d'événement
 *    (idempotence : un renvoi après coupure réseau n'est jamais appliqué deux fois) ;
 *  - chaque action est exécutée dans une transaction avec verrou de ligne (SELECT … FOR UPDATE).
 */
final class BoutService
{
    public const METHODS = [
        'ippon' => 'Ippon', 'wazaari' => 'Waza-ari', 'yuko' => 'Yuko', 'decision' => 'Décision (hantei)',
        'hansoku_make' => 'Hansoku-make', 'fusen_gachi' => 'Fusen-gachi (forfait)', 'kiken_gachi' => 'Kiken-gachi (abandon)',
        'shido' => 'Pénalités (shido)',
    ];
    public const STATUS = [
        'scheduled' => 'À venir', 'in_progress' => 'En cours', 'finished' => 'Terminé (à valider)',
        'validated' => 'Validé', 'cancelled' => 'Annulé',
    ];

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public static function find(int $id, bool $lock = false): array
    {
        $b = Database::one('SELECT * FROM bouts WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$b) {
            throw new HttpException(404, 'Combat introuvable.');
        }
        return $b;
    }

    /** Temps écoulé (ms) dans la phase en cours (temps réglementaire ou golden score). */
    public static function elapsedMs(array $b, ?int $now = null): int
    {
        $now ??= self::nowMs();
        $e = (int) $b['clock_elapsed_ms'];
        if ((int) $b['clock_running'] === 1 && $b['clock_started_at_ms'] !== null) {
            $e += max(0, $now - (int) $b['clock_started_at_ms']);
        }
        return $e;
    }

    // ------------------------------------------------------------------
    // Autorisations
    // ------------------------------------------------------------------

    /** Administrateur (toute saisie) ou commissaire affecté au tapis, sur son tapis actif. */
    public static function assertCanScore(array $bout): void
    {
        if (!Auth::check()) {
            throw new HttpException(401);
        }
        CompetitionService::assertWritable((int) $bout['competition_id']);
        if (Auth::can('results.correct')) {
            return;
        }
        if (!Auth::can('bouts.score')) {
            throw new HttpException(403);
        }
        $assigned = Database::one('SELECT is_active FROM commissioner_tatamis WHERE user_id = ? AND tatami_id = ?', [Auth::id(), (int) $bout['tatami_id']]);
        if (!$assigned) {
            throw new HttpException(403, "Ce combat n'est pas sur un tapis qui vous est affecté.");
        }
        if ((int) $assigned['is_active'] !== 1) {
            throw new HttpException(403, "Ce tapis n'est pas votre tapis actif : sélectionnez-le d'abord.");
        }
    }

    public static function assertCanUseTatami(int $tatamiId): array
    {
        $t = Database::one('SELECT * FROM tatamis WHERE id = ?', [$tatamiId]);
        if (!$t) {
            throw new HttpException(404, 'Tapis introuvable.');
        }
        CompetitionService::assertWritable((int) $t['competition_id']);
        if (Auth::can('results.correct')) {
            return $t;
        }
        $assigned = Database::one('SELECT is_active FROM commissioner_tatamis WHERE user_id = ? AND tatami_id = ?', [Auth::id(), $tatamiId]);
        if (!Auth::can('bouts.score') || !$assigned) {
            throw new HttpException(403, "Ce tapis ne vous est pas affecté.");
        }
        if ((int) $assigned['is_active'] !== 1) {
            throw new HttpException(403, "Ce tapis n'est pas votre tapis actif.");
        }
        return $t;
    }

    // ------------------------------------------------------------------
    // Actions de saisie
    // ------------------------------------------------------------------

    /**
     * Applique une action de saisie. $input : action, version, client_event_id, side, kind, delta, winner_side, method, held_ms.
     * @return array état du combat après l'action
     */
    public static function act(int $boutId, array $input): array
    {
        $action = (string) ($input['action'] ?? '');
        $allowed = ['start', 'pause', 'reset', 'score', 'osaekomi', 'toketa', 'golden_score', 'finish', 'forfeit', 'validate', 'reopen'];
        if (!in_array($action, $allowed, true)) {
            throw new DomainError('Action inconnue.', 400);
        }
        $clientEventId = isset($input['client_event_id']) ? substr((string) $input['client_event_id'], 0, 64) : null;

        return Database::transaction(function () use ($boutId, $action, $input, $clientEventId) {
            $b = self::find($boutId, true);
            self::assertCanScore($b);
            // Idempotence : événement déjà appliqué (renvoi après perte de connexion)
            if ($clientEventId !== null && Database::value('SELECT 1 FROM score_events WHERE bout_id = ? AND client_event_id = ?', [$boutId, $clientEventId])) {
                return self::state($boutId) + ['duplicate' => true];
            }
            if ($action === 'validate' && $b['status'] === 'validated') {
                // Double validation : sans effet, résultat déjà enregistré.
                return self::state($boutId) + ['duplicate' => true];
            }
            if (!isset($input['version']) || (int) $input['version'] !== (int) $b['version']) {
                throw new HttpException(409, 'Le combat a été modifié depuis un autre poste : affichage actualisé, vérifiez avant de recommencer.', ['state' => self::state($boutId)]);
            }
            // Horodatage : celui du poste du commissaire s'il est cohérent (action différée par une coupure réseau),
            // jamais dans le futur ni antérieur au dernier démarrage du chronomètre.
            $serverNow = self::nowMs();
            $now = $serverNow;
            if (isset($input['at_ms']) && in_array($action, ['start', 'pause', 'finish', 'osaekomi', 'toketa'], true)) {
                $at = (int) $input['at_ms'];
                if (abs($at - $serverNow) <= 5000) {
                    $now = min($at, $serverNow);
                }
                if ($b['clock_started_at_ms'] !== null) {
                    $now = max($now, (int) $b['clock_started_at_ms']);
                }
            }
            $model = (array) SettingsService::get((int) $b['competition_id'], 'score_model');
            $changes = match ($action) {
                'start' => self::doStart($b, $now),
                'pause' => self::doPause($b, $now, $model),
                'reset' => self::doReset($b),
                'score' => self::doScore($b, $now, $model, (string) ($input['side'] ?? ''), (string) ($input['kind'] ?? ''), (int) ($input['delta'] ?? 1)),
                'osaekomi' => self::doOsaekomi($b, $now, (string) ($input['side'] ?? '')),
                'toketa' => self::doToketa($b, $now, $model, isset($input['held_ms']) ? (int) $input['held_ms'] : null),
                'golden_score' => self::doGoldenScore($b, $now),
                'finish' => self::doFinish($b, $now, $model, $input['winner_side'] ?? null, $input['method'] ?? null),
                'forfeit' => self::doForfeit($b, $now, (string) ($input['winner_side'] ?? ''), (string) ($input['method'] ?? 'fusen_gachi')),
                'validate' => self::doValidate($b),
                'reopen' => self::doReopen($b),
            };
            $changes['version'] = (int) $b['version'] + 1;
            Database::update('bouts', $changes, 'id = :id', ['id' => $boutId]);
            Database::insert('score_events', [
                'bout_id' => $boutId,
                'client_event_id' => $clientEventId,
                'action' => $action . (isset($input['kind']) ? ':' . $input['kind'] : ''),
                'side' => in_array($input['side'] ?? null, ['a', 'b'], true) ? $input['side'] : (in_array($input['winner_side'] ?? null, ['a', 'b'], true) ? $input['winner_side'] : null),
                'payload_json' => json_encode(array_intersect_key($input, array_flip(['delta', 'method', 'held_ms'])), JSON_UNESCAPED_UNICODE),
                'clock_ms' => self::elapsedMs(array_merge($b, $changes), $now),
                'user_id' => Auth::id(),
            ]);
            $after = self::find($boutId);
            self::afterChange($b, $after, $action);
            return self::state($boutId);
        });
    }

    private static function assertInProgress(array $b): void
    {
        if ($b['status'] !== 'in_progress') {
            throw new DomainError($b['status'] === 'scheduled' ? 'Démarrez le combat (Hajime) avant de saisir.' : 'Ce combat est terminé.');
        }
    }

    private static function doStart(array $b, int $now): array
    {
        if (!in_array($b['status'], ['scheduled', 'in_progress'], true)) {
            throw new DomainError('Ce combat est terminé.');
        }
        if ((int) $b['clock_running'] === 1) {
            return [];
        }
        $other = Database::value(
            "SELECT id FROM bouts WHERE tatami_id = ? AND id <> ? AND status IN ('in_progress','finished') LIMIT 1",
            [$b['tatami_id'], $b['id']]
        );
        if ($other) {
            throw new DomainError("Un autre combat de ce tapis est en cours ou attend sa validation (combat n° $other).");
        }
        $changes = ['clock_running' => 1, 'clock_started_at_ms' => $now, 'status' => 'in_progress'];
        if ($b['started_at'] === null) {
            $changes['started_at'] = date('Y-m-d H:i:s');
            $changes['commissioner_id'] = Auth::id();
        }
        Database::run('UPDATE tatamis SET current_bout_id = ? WHERE id = ?', [$b['id'], $b['tatami_id']]);
        return $changes;
    }

    private static function stopClock(array $b, int $now): array
    {
        if ((int) $b['clock_running'] !== 1) {
            return [];
        }
        return ['clock_running' => 0, 'clock_elapsed_ms' => self::elapsedMs($b, $now), 'clock_started_at_ms' => null];
    }

    private static function doPause(array $b, int $now, array $model): array
    {
        self::assertInProgress($b);
        $changes = self::stopClock($b, $now);
        if ($b['osaekomi_side'] !== null) {
            // Matte met fin à l'immobilisation : le temps tenu est converti en avantage.
            $changes = array_merge($changes, self::doToketa(array_merge($b, $changes), $now, $model, null));
        }
        return $changes;
    }

    private static function doReset(array $b): array
    {
        if (!in_array($b['status'], ['scheduled', 'in_progress'], true)) {
            throw new DomainError('Un combat terminé ne peut pas être réinitialisé : utilisez la correction.');
        }
        if ((int) $b['clock_running'] === 1) {
            throw new DomainError('Arrêtez le chronomètre avant de réinitialiser.');
        }
        return [
            'status' => 'scheduled', 'clock_running' => 0, 'clock_started_at_ms' => null, 'clock_elapsed_ms' => 0,
            'is_golden_score' => 0, 'osaekomi_side' => null, 'osaekomi_started_at_ms' => null, 'started_at' => null,
            'a_ippon' => 0, 'a_wazaari' => 0, 'a_yuko' => 0, 'a_shido' => 0, 'a_hansoku' => 0,
            'b_ippon' => 0, 'b_wazaari' => 0, 'b_yuko' => 0, 'b_shido' => 0, 'b_hansoku' => 0,
            'winner_side' => null, 'win_method' => null, 'final_elapsed_ms' => null, 'ended_at' => null,
        ];
    }

    private static function other(string $side): string
    {
        return $side === 'a' ? 'b' : 'a';
    }

    private static function doScore(array $b, int $now, array $model, string $side, string $kind, int $delta): array
    {
        if (!in_array($side, ['a', 'b'], true) || !in_array($kind, ['ippon', 'wazaari', 'yuko', 'shido', 'hansoku'], true) || !in_array($delta, [1, -1], true)) {
            throw new DomainError('Saisie invalide.', 400);
        }
        if ($kind === 'yuko' && empty($model['use_yuko'])) {
            throw new DomainError("Le yuko n'est pas utilisé dans le modèle de score de cette compétition.");
        }
        $correctingFinished = $b['status'] === 'finished' && $delta === -1;
        if (!$correctingFinished) {
            self::assertInProgress($b);
        }
        $col = $side . '_' . $kind;
        $value = (int) $b[$col] + $delta;
        if ($value < 0) {
            return [];
        }
        $max = match ($kind) {
            'ippon', 'hansoku' => 1,
            'shido' => (int) ($model['shido_for_hansoku'] ?? 3),
            'wazaari' => (int) ($model['wazaari_for_ippon'] ?? 2),
            default => 99,
        };
        if ($value > $max) {
            return [];
        }
        $changes = [$col => $value];
        $s = array_merge($b, $changes);
        // Pénalités : le n-ième shido entraîne hansoku-make
        if ($kind === 'shido') {
            $s[$side . '_hansoku'] = $changes[$side . '_hansoku'] = $value >= (int) ($model['shido_for_hansoku'] ?? 3) ? 1 : ((int) $b[$side . '_shido'] >= (int) ($model['shido_for_hansoku'] ?? 3) && $delta < 0 ? 0 : (int) $b[$side . '_hansoku']);
        }
        // Waza-ari awasete ippon : le chiffre waza-ari reste affiché (ex. « 020 ») et le combat est gagné.
        $decisive = self::decisiveResult($s, $model, $delta > 0 ? ['side' => $side, 'kind' => $kind] : null);
        if ($decisive) {
            $changes = array_merge($changes, self::stopClock($b, $now), [
                'status' => 'finished', 'winner_side' => $decisive['winner'], 'win_method' => $decisive['method'],
                'osaekomi_side' => null, 'osaekomi_started_at_ms' => null,
                'final_elapsed_ms' => self::totalElapsed(array_merge($b, self::stopClock($b, $now))), 'ended_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif ($b['status'] === 'finished') {
            // L'annulation d'un avantage supprime la fin automatique du combat : retour en cours (chronomètre arrêté).
            $changes = array_merge($changes, ['status' => 'in_progress', 'winner_side' => null, 'win_method' => null, 'final_elapsed_ms' => null, 'ended_at' => null]);
        }
        return $changes;
    }

    /** Fin immédiate : ippon, hansoku-make, ou premier avantage en golden score. */
    private static function decisiveResult(array $s, array $model, ?array $lastScore): ?array
    {
        foreach (['a', 'b'] as $side) {
            if ((int) $s[$side . '_hansoku'] === 1) {
                return ['winner' => self::other($side), 'method' => 'hansoku_make'];
            }
        }
        foreach (['a', 'b'] as $side) {
            if ((int) $s[$side . '_ippon'] === 1 || (int) $s[$side . '_wazaari'] >= (int) ($model['wazaari_for_ippon'] ?? 2)) {
                return ['winner' => $side, 'method' => 'ippon'];
            }
        }
        if ((int) $s['is_golden_score'] === 1 && !empty($model['golden_score_first_score_wins']) && $lastScore && in_array($lastScore['kind'], ['wazaari', 'yuko'], true)) {
            return ['winner' => $lastScore['side'], 'method' => $lastScore['kind']];
        }
        return null;
    }

    private static function totalElapsed(array $b): int
    {
        $e = (int) $b['clock_elapsed_ms'];
        return (int) $b['is_golden_score'] === 1 ? (int) $b['duration_seconds'] * 1000 + $e : $e;
    }

    private static function doOsaekomi(array $b, int $now, string $side): array
    {
        self::assertInProgress($b);
        if (!in_array($side, ['a', 'b'], true)) {
            throw new DomainError('Côté invalide.', 400);
        }
        if ((int) $b['clock_running'] !== 1) {
            throw new DomainError("L'osaekomi se déclare chronomètre en marche.");
        }
        return ['osaekomi_side' => $side, 'osaekomi_started_at_ms' => $now];
    }

    /** Fin d'immobilisation : avantage attribué selon les seuils du modèle de score (ex. 5 s yuko, 10 s waza-ari, 20 s ippon). */
    private static function doToketa(array $b, int $now, array $model, ?int $clientHeldMs): array
    {
        if ($b['osaekomi_side'] === null) {
            return [];
        }
        $serverHeld = max(0, $now - (int) $b['osaekomi_started_at_ms']);
        // Le temps mesuré par le poste du commissaire est retenu s'il est cohérent (latence réseau).
        $held = ($clientHeldMs !== null && abs($clientHeldMs - $serverHeld) <= 1500) ? $clientHeldMs : $serverHeld;
        $side = $b['osaekomi_side'];
        $t = (array) ($model['osaekomi'] ?? []);
        $kind = null;
        if ($held >= (int) ($t['ippon'] ?? 20) * 1000) {
            $kind = 'ippon';
        } elseif ($held >= (int) ($t['wazaari'] ?? 10) * 1000) {
            $kind = 'wazaari';
        } elseif (!empty($model['use_yuko']) && $held >= (int) ($t['yuko'] ?? 5) * 1000) {
            $kind = 'yuko';
        }
        $base = ['osaekomi_side' => null, 'osaekomi_started_at_ms' => null];
        if ($kind === null) {
            return $base;
        }
        $b = array_merge($b, $base);
        return array_merge($base, self::doScore($b, $now, $model, $side, $kind, 1));
    }

    private static function doGoldenScore(array $b, int $now): array
    {
        self::assertInProgress($b);
        if ((int) $b['clock_running'] === 1) {
            throw new DomainError('Arrêtez le chronomètre (Matte) avant de passer en golden score.');
        }
        if ((int) $b['is_golden_score'] === 1) {
            return [];
        }
        if ($b['golden_score_mode'] === 'none') {
            throw new DomainError("Pas de golden score pour cette catégorie : terminez le combat par décision.");
        }
        if (self::elapsedMs($b, $now) < (int) $b['duration_seconds'] * 1000) {
            throw new DomainError("Le temps réglementaire n'est pas écoulé.");
        }
        $scoreA = [(int) $b['a_ippon'], (int) $b['a_wazaari'], (int) $b['a_yuko']];
        $scoreB = [(int) $b['b_ippon'], (int) $b['b_wazaari'], (int) $b['b_yuko']];
        if ($scoreA !== $scoreB) {
            throw new DomainError('Les scores ne sont pas à égalité : terminez le combat.');
        }
        return ['is_golden_score' => 1, 'clock_elapsed_ms' => 0, 'clock_started_at_ms' => null, 'clock_running' => 0];
    }

    /** Vainqueur aux avantages (ippon > waza-ari > yuko), sinon pénalités si le modèle le prévoit, sinon null (égalité). */
    public static function leader(array $b, array $model): ?array
    {
        foreach (['ippon', 'wazaari', 'yuko'] as $k) {
            if ((int) $b['a_' . $k] !== (int) $b['b_' . $k]) {
                return ['winner' => (int) $b['a_' . $k] > (int) $b['b_' . $k] ? 'a' : 'b', 'method' => $k];
            }
        }
        if (!empty($model['shido_decides']) && (int) $b['a_shido'] !== (int) $b['b_shido']) {
            return ['winner' => (int) $b['a_shido'] < (int) $b['b_shido'] ? 'a' : 'b', 'method' => 'shido'];
        }
        return null;
    }

    private static function doFinish(array $b, int $now, array $model, mixed $winnerSide, mixed $method): array
    {
        if ($b['status'] === 'finished') {
            return [];
        }
        self::assertInProgress($b);
        $stop = self::stopClock($b, $now);
        $s = array_merge($b, $stop);
        $lead = self::leader($s, $model);
        if ($lead === null) {
            if (!in_array($winnerSide, ['a', 'b'], true)) {
                throw new DomainError('Égalité : désignez le vainqueur (décision) ou passez en golden score.', 422, ['needs_decision' => true]);
            }
            $lead = ['winner' => $winnerSide, 'method' => 'decision'];
        } elseif (in_array($winnerSide, ['a', 'b'], true) && $winnerSide !== $lead['winner']) {
            throw new DomainError('Le vainqueur désigné ne correspond pas au score. Corrigez le score si nécessaire.');
        }
        return array_merge($stop, [
            'status' => 'finished', 'winner_side' => $lead['winner'], 'win_method' => $lead['method'],
            'osaekomi_side' => null, 'osaekomi_started_at_ms' => null,
            'final_elapsed_ms' => self::totalElapsed($s), 'ended_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function doForfeit(array $b, int $now, string $winnerSide, string $method): array
    {
        if (!in_array($b['status'], ['scheduled', 'in_progress'], true)) {
            throw new DomainError('Ce combat est déjà terminé.');
        }
        if (!in_array($winnerSide, ['a', 'b'], true) || !in_array($method, ['fusen_gachi', 'kiken_gachi'], true)) {
            throw new DomainError('Précisez le vainqueur et le motif (forfait ou abandon).');
        }
        $stop = self::stopClock($b, $now);
        return array_merge($stop, [
            'status' => 'finished', 'winner_side' => $winnerSide, 'win_method' => $method,
            'osaekomi_side' => null, 'osaekomi_started_at_ms' => null,
            'final_elapsed_ms' => self::totalElapsed(array_merge($b, $stop)), 'ended_at' => date('Y-m-d H:i:s'),
            'started_at' => $b['started_at'] ?? date('Y-m-d H:i:s'), 'commissioner_id' => $b['commissioner_id'] ?? Auth::id(),
        ]);
    }

    private static function doReopen(array $b): array
    {
        if ($b['status'] !== 'finished') {
            throw new DomainError('Seul un combat terminé et non validé peut être repris.');
        }
        return ['status' => 'in_progress', 'winner_side' => null, 'win_method' => null, 'final_elapsed_ms' => null, 'ended_at' => null];
    }

    private static function doValidate(array $b): array
    {
        if ($b['status'] !== 'finished' || !in_array($b['winner_side'], ['a', 'b'], true)) {
            throw new DomainError('Terminez le combat avant de valider le résultat.');
        }
        $revision = (int) Database::value('SELECT COALESCE(MAX(revision), 0) + 1 FROM bout_results WHERE bout_id = ?', [$b['id']]);
        Database::run('UPDATE bout_results SET is_current = 0 WHERE bout_id = ?', [$b['id']]);
        Database::insert('bout_results', [
            'bout_id' => $b['id'], 'revision' => $revision, 'is_current' => 1,
            'winner_registration_id' => $b[$b['winner_side'] . '_registration_id'],
            'loser_registration_id' => $b[self::other($b['winner_side']) . '_registration_id'],
            'winner_side' => $b['winner_side'], 'win_method' => $b['win_method'],
            'score_json' => json_encode(self::scoreSnapshot($b)), 'kind' => 'validation', 'recorded_by' => Auth::id(),
        ]);
        AuditLogger::log('bout.validate', 'bout', (int) $b['id'], null, self::scoreSnapshot($b) + ['winner_side' => $b['winner_side'], 'method' => $b['win_method']], null, (int) $b['competition_id']);
        return ['status' => 'validated', 'validated_by' => Auth::id(), 'validated_at' => date('Y-m-d H:i:s')];
    }

    public static function scoreSnapshot(array $b): array
    {
        $out = [];
        foreach (['a', 'b'] as $s) {
            foreach (['ippon', 'wazaari', 'yuko', 'shido', 'hansoku'] as $k) {
                $out[$s][$k] = (int) $b[$s . '_' . $k];
            }
        }
        return $out;
    }

    /** Effets de bord après une action : statut de la poule, classements. */
    private static function afterChange(array $before, array $after, string $action): void
    {
        if ($before['status'] === 'scheduled' && $after['status'] !== 'scheduled') {
            Database::run("UPDATE pools SET status = 'in_progress' WHERE id = ? AND status = 'validated'", [$after['pool_id']]);
            Database::run("UPDATE competitions SET status = 'in_progress' WHERE id = ? AND status IN ('pool_preparation','attendance','registrations_closed')", [$after['competition_id']]);
        }
        if ($action === 'validate') {
            RankingService::recomputePool((int) $after['pool_id']);
        }
    }

    // ------------------------------------------------------------------
    // Correction d'un résultat validé
    // ------------------------------------------------------------------

    /**
     * Correction d'un résultat validé : confirmation, motif, ancienne et nouvelle valeurs, auteur, horodatage,
     * recalcul des classements. Administrateur : tout combat. Commissaire : combats de ses tapis, compétition non terminée.
     */
    public static function correct(int $boutId, array $input): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            throw new DomainError('Indiquez le motif de la correction (5 caractères minimum).');
        }
        if (empty($input['confirm'])) {
            throw new DomainError('Confirmez explicitement la correction.');
        }
        return Database::transaction(function () use ($boutId, $input, $reason) {
            $b = self::find($boutId, true);
            if (!Auth::can('results.correct')) {
                self::assertCanScore($b);
                $status = (string) Database::value('SELECT status FROM competitions WHERE id = ?', [$b['competition_id']]);
                if (in_array($status, ['finished', 'archived'], true)) {
                    throw new HttpException(403, 'Compétition terminée : seule l\'administration peut corriger ce résultat.');
                }
            } else {
                CompetitionService::assertWritable((int) $b['competition_id']);
            }
            if ($b['status'] !== 'validated') {
                throw new DomainError('Seul un résultat validé se corrige ici ; un combat en cours se modifie depuis la saisie.');
            }
            if (!isset($input['version']) || (int) $input['version'] !== (int) $b['version']) {
                throw new HttpException(409, 'Le combat a été modifié entre-temps : rechargez avant de corriger.', ['state' => self::state($boutId)]);
            }
            $winner = $input['winner_side'] ?? null;
            $method = (string) ($input['method'] ?? '');
            if (!in_array($winner, ['a', 'b'], true) || !isset(self::METHODS[$method])) {
                throw new DomainError('Vainqueur et type de victoire obligatoires.');
            }
            $changes = [];
            foreach (['a', 'b'] as $s) {
                foreach (['ippon' => 1, 'wazaari' => 9, 'yuko' => 99, 'shido' => 9, 'hansoku' => 1] as $k => $max) {
                    $v = (int) ($input['scores'][$s][$k] ?? $b[$s . '_' . $k]);
                    $changes[$s . '_' . $k] = max(0, min($max, $v));
                }
            }
            $old = self::scoreSnapshot($b) + ['winner_side' => $b['winner_side'], 'method' => $b['win_method']];
            $changes['winner_side'] = $winner;
            $changes['win_method'] = $method;
            $changes['version'] = (int) $b['version'] + 1;
            $changes['validated_by'] = Auth::id();
            $changes['validated_at'] = date('Y-m-d H:i:s');
            Database::update('bouts', $changes, 'id = :id', ['id' => $boutId]);
            $after = self::find($boutId);
            $new = self::scoreSnapshot($after) + ['winner_side' => $winner, 'method' => $method];
            $revision = (int) Database::value('SELECT COALESCE(MAX(revision), 0) + 1 FROM bout_results WHERE bout_id = ?', [$boutId]);
            Database::run('UPDATE bout_results SET is_current = 0 WHERE bout_id = ?', [$boutId]);
            Database::insert('bout_results', [
                'bout_id' => $boutId, 'revision' => $revision, 'is_current' => 1,
                'winner_registration_id' => $after[$winner . '_registration_id'],
                'loser_registration_id' => $after[self::other($winner) . '_registration_id'],
                'winner_side' => $winner, 'win_method' => $method, 'score_json' => json_encode(self::scoreSnapshot($after)),
                'kind' => 'correction', 'reason' => mb_substr($reason, 0, 500), 'recorded_by' => Auth::id(),
            ]);
            Database::insert('score_events', [
                'bout_id' => $boutId, 'action' => 'correct', 'side' => $winner,
                'payload_json' => json_encode(['reason' => mb_substr($reason, 0, 200)], JSON_UNESCAPED_UNICODE), 'user_id' => Auth::id(),
            ]);
            AuditLogger::log('bout.correct', 'bout', $boutId, $old, $new, $reason, (int) $b['competition_id']);
            RankingService::recomputePool((int) $b['pool_id']);
            return self::state($boutId);
        });
    }

    /** Annule un combat (ex. athlète retiré avant le combat) — administrateur uniquement. */
    public static function cancel(int $boutId, string $reason): void
    {
        $b = self::find($boutId);
        CompetitionService::assertWritable((int) $b['competition_id']);
        if (!in_array($b['status'], ['scheduled'], true)) {
            throw new DomainError('Seul un combat à venir peut être annulé.');
        }
        Database::run("UPDATE bouts SET status = 'cancelled', version = version + 1 WHERE id = ?", [$boutId]);
        AuditLogger::log('bout.cancel', 'bout', $boutId, ['status' => 'scheduled'], ['status' => 'cancelled'], $reason, (int) $b['competition_id']);
        RankingService::recomputePool((int) $b['pool_id']);
    }

    // ------------------------------------------------------------------
    // Tapis : combat en cours, suivants
    // ------------------------------------------------------------------

    /** Choisit le combat affiché / saisi sur le tapis (le suivant par défaut). */
    public static function selectCurrent(int $tatamiId, ?int $boutId = null): ?array
    {
        return Database::transaction(function () use ($tatamiId, $boutId) {
            $t = Database::one('SELECT * FROM tatamis WHERE id = ? FOR UPDATE', [$tatamiId]);
            if ($t['current_bout_id']) {
                $cur = Database::one('SELECT status FROM bouts WHERE id = ?', [$t['current_bout_id']]);
                if ($cur && in_array($cur['status'], ['in_progress', 'finished'], true) && (int) $t['current_bout_id'] !== $boutId) {
                    throw new DomainError('Le combat en cours doit être terminé et validé avant de passer au suivant.');
                }
            }
            if ($boutId === null) {
                $boutId = Database::value("SELECT id FROM bouts WHERE tatami_id = ? AND status = 'scheduled' ORDER BY order_on_tatami, id LIMIT 1", [$tatamiId]);
                $boutId = $boutId === null ? null : (int) $boutId;
            } elseif (!Database::value("SELECT 1 FROM bouts WHERE id = ? AND tatami_id = ? AND status <> 'cancelled'", [$boutId, $tatamiId])) {
                throw new DomainError("Ce combat n'est pas programmé sur ce tapis.");
            }
            Database::run('UPDATE tatamis SET current_bout_id = ? WHERE id = ?', [$boutId, $tatamiId]);
            AuditLogger::log('tatami.select_bout', 'tatami', $tatamiId, ['bout_id' => $t['current_bout_id']], ['bout_id' => $boutId], null, (int) $t['competition_id']);
            return $boutId ? self::state($boutId) : null;
        });
    }

    public static function upcoming(int $tatamiId, int $limit = 5, ?int $excludeId = null): array
    {
        $rows = Database::all(
            "SELECT id FROM bouts WHERE tatami_id = ? AND status = 'scheduled' AND id <> ? ORDER BY order_on_tatami, id LIMIT " . (int) $limit,
            [$tatamiId, $excludeId ?? 0]
        );
        return array_map(fn ($r) => self::state((int) $r['id'], false), $rows);
    }

    public static function recent(int $tatamiId, int $limit = 20): array
    {
        $rows = Database::all(
            "SELECT id FROM bouts WHERE tatami_id = ? AND status IN ('validated','finished') ORDER BY COALESCE(validated_at, ended_at) DESC, id DESC LIMIT " . (int) $limit,
            [$tatamiId]
        );
        return array_map(fn ($r) => self::state((int) $r['id'], false), $rows);
    }

    // ------------------------------------------------------------------
    // Représentation partagée (commissaire, TV, administration)
    // ------------------------------------------------------------------

    public static function state(int $boutId, bool $full = true): array
    {
        $b = Database::one(
            "SELECT b.*, p.label AS pool_label, p.number AS pool_number, p.sex, ac.name AS age_name, ac.name_m, ac.name_f, wc.label AS weight_label,
                    t.number AS tatami_number,
                    (SELECT COUNT(*) FROM bouts x WHERE x.pool_id = b.pool_id AND x.status <> 'cancelled') AS pool_bouts,
                    (SELECT COUNT(*) FROM bouts x WHERE x.pool_id = b.pool_id AND x.status <> 'cancelled' AND x.order_in_pool <= b.order_in_pool) AS pool_position,
                    u.display_name AS validated_by_name
             FROM bouts b JOIN pools p ON p.id = b.pool_id JOIN age_categories ac ON ac.id = p.age_category_id
             LEFT JOIN weight_categories wc ON wc.id = p.weight_category_id
             LEFT JOIN tatamis t ON t.id = b.tatami_id
             LEFT JOIN users u ON u.id = b.validated_by
             WHERE b.id = ?",
            [$boutId]
        );
        if (!$b) {
            throw new HttpException(404, 'Combat introuvable.');
        }
        $fighters = Database::all(
            'SELECT r.id, a.first_name, a.last_name, c.name AS club_name, c.short_name AS club_short, c.logo_path AS club_logo, r.club_exception_label
             FROM registrations r JOIN athletes a ON a.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id WHERE r.id IN (?, ?)',
            [$b['a_registration_id'], $b['b_registration_id']]
        );
        $byId = array_column($fighters, null, 'id');
        $side = function (string $s) use ($b, $byId) {
            $f = $byId[$b[$s . '_registration_id']] ?? [];
            return [
                'registration_id' => (int) $b[$s . '_registration_id'],
                'first_name' => $f['first_name'] ?? '',
                'last_name' => $f['last_name'] ?? '',
                'club' => $f['club_name'] ?? ($f['club_exception_label'] ?? ''),
                'club_short' => $f['club_short'] ?? null,
                'club_logo' => !empty($f['club_logo']) ? url('/' . ltrim($f['club_logo'], '/')) : null,
                'ippon' => (int) $b[$s . '_ippon'], 'wazaari' => (int) $b[$s . '_wazaari'], 'yuko' => (int) $b[$s . '_yuko'],
                'shido' => (int) $b[$s . '_shido'], 'hansoku' => (int) $b[$s . '_hansoku'],
            ];
        };
        $category = CategoryService::label(['name' => $b['age_name'], 'name_m' => $b['name_m'], 'name_f' => $b['name_f']], $b['sex']);
        $state = [
            'id' => (int) $b['id'],
            'version' => (int) $b['version'],
            'status' => $b['status'],
            'status_label' => self::STATUS[$b['status']] ?? $b['status'],
            'phase' => $b['phase'],
            'tatami_id' => $b['tatami_id'] === null ? null : (int) $b['tatami_id'],
            'tatami_number' => $b['tatami_number'] === null ? null : (int) $b['tatami_number'],
            'pool_id' => (int) $b['pool_id'],
            'pool_label' => $b['pool_label'],
            'category' => $category,
            'weight_label' => $b['weight_label'],
            'order_on_tatami' => (int) $b['order_on_tatami'],
            'pool_position' => (int) $b['pool_position'],
            'pool_bouts' => (int) $b['pool_bouts'],
            'a' => $side('a'),
            'b' => $side('b'),
            'winner_side' => $b['winner_side'],
            'win_method' => $b['win_method'],
            'win_method_label' => $b['win_method'] ? (self::METHODS[$b['win_method']] ?? $b['win_method']) : null,
        ];
        if ($full) {
            $state['clock'] = [
                'duration_ms' => (int) $b['duration_seconds'] * 1000,
                'running' => (int) $b['clock_running'] === 1,
                'started_at_ms' => $b['clock_started_at_ms'] === null ? null : (int) $b['clock_started_at_ms'],
                'elapsed_ms' => (int) $b['clock_elapsed_ms'],
                'golden_score' => (int) $b['is_golden_score'] === 1,
                'golden_score_mode' => $b['golden_score_mode'],
                'golden_score_ms' => $b['golden_score_seconds'] === null ? null : (int) $b['golden_score_seconds'] * 1000,
                'final_elapsed_ms' => $b['final_elapsed_ms'] === null ? null : (int) $b['final_elapsed_ms'],
            ];
            $state['osaekomi'] = $b['osaekomi_side'] === null ? null : ['side' => $b['osaekomi_side'], 'started_at_ms' => (int) $b['osaekomi_started_at_ms']];
            $state['validated_at'] = $b['validated_at'];
            $state['validated_by'] = $b['validated_by_name'];
            $state['server_now_ms'] = self::nowMs();
        }
        return $state;
    }
}
