<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

final class CompetitionService
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'registrations_open' => 'Inscriptions ouvertes',
        'registrations_closed' => 'Inscriptions clôturées',
        'attendance' => 'Contrôle des présences',
        'pool_preparation' => 'Préparation des poules',
        'in_progress' => 'Compétition en cours',
        'finished' => 'Compétition terminée',
        'archived' => 'Archivée',
    ];

    public const EDITABLE_FIELDS = [
        'name', 'tagline', 'description', 'event_date', 'start_time', 'venue', 'address', 'city',
        'registration_opens_at', 'registration_closes_at', 'age_reference_date', 'mat_count', 'max_athletes',
        'expected_participants', 'pool_sizes', 'schedule_text', 'practical_info', 'coach_info', 'is_featured',
    ];

    public static function find(int $id): array
    {
        $c = Database::one('SELECT * FROM competitions WHERE id = ?', [$id]);
        if (!$c) {
            throw new HttpException(404, 'Compétition introuvable.');
        }
        return $c;
    }

    public static function findBySlug(string $slug): array
    {
        $c = Database::one("SELECT * FROM competitions WHERE slug = ? AND status <> 'draft'", [$slug]);
        if (!$c) {
            throw new HttpException(404, 'Compétition introuvable.');
        }
        return $c;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function isArchived(array $competition): bool
    {
        return $competition['status'] === 'archived';
    }

    /** Toute écriture sur une compétition archivée est refusée (lecture seule). */
    public static function assertWritable(array|int $competition): void
    {
        $c = is_array($competition) ? $competition : self::find($competition);
        if (self::isArchived($c)) {
            throw new HttpException(423);
        }
    }

    public static function validatePoolSizes(string|array $sizes): string
    {
        $list = is_array($sizes) ? $sizes : explode(',', $sizes);
        $list = array_values(array_unique(array_filter(array_map('intval', $list), fn ($s) => in_array($s, [3, 4, 5], true))));
        sort($list);
        if (!$list) {
            throw new DomainError('Sélectionnez au moins une taille de poule (3, 4 ou 5 combattants).');
        }
        return implode(',', $list);
    }

    public static function create(array $data, array $ageCodes = ['U11', 'U13', 'U15'], bool $withWeights = true): int
    {
        return Database::transaction(function () use ($data, $ageCodes, $withWeights) {
            $data['pool_sizes'] = self::validatePoolSizes($data['pool_sizes'] ?? '3,4,5');
            self::checkDates($data);
            $row = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
            $row['uuid'] = self::uuid();
            $row['slug'] = self::uniqueSlug(($data['name'] ?? 'competition') . '-' . substr((string) $data['event_date'], 0, 4));
            $row['status'] = 'draft';
            $row['created_by'] = Auth::id();
            $row['is_demo'] = (int) ($data['is_demo'] ?? 0);
            $row['mat_count'] = max(1, min(20, (int) ($row['mat_count'] ?? 1)));
            $id = Database::insert('competitions', $row);
            $competition = self::find($id);
            SettingsService::initialize($id);
            self::syncTatamis($competition);
            CategoryService::createFromTemplates($competition, $ageCodes, $withWeights);
            DurationRuleService::createExamples($id);
            RankingService::createDefaultRules($id);
            AuditLogger::log('competition.create', 'competition', $id, null, ['name' => $row['name']], null, $id);
            return $id;
        });
    }

    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        self::assertWritable($before);
        if (isset($data['pool_sizes'])) {
            $data['pool_sizes'] = self::validatePoolSizes($data['pool_sizes']);
        }
        self::checkDates(array_merge($before, $data));
        $row = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
        if (isset($row['mat_count'])) {
            $row['mat_count'] = max(1, min(20, (int) $row['mat_count']));
        }
        Database::transaction(function () use ($id, $row, $before) {
            Database::update('competitions', $row, 'id = :id', ['id' => $id]);
            self::syncTatamis(self::find($id));
            $changed = array_filter($row, fn ($v, $k) => (string) ($before[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($changed) {
                AuditLogger::log('competition.update', 'competition', $id, array_intersect_key($before, $changed), $changed, null, $id);
            }
        });
    }

    private static function checkDates(array $d): void
    {
        if (!empty($d['registration_opens_at']) && !empty($d['registration_closes_at'])
            && strtotime((string) $d['registration_opens_at']) >= strtotime((string) $d['registration_closes_at'])) {
            throw new DomainError("La clôture des inscriptions doit être postérieure à leur ouverture.");
        }
        if (!empty($d['registration_closes_at']) && !empty($d['event_date'])
            && strtotime((string) $d['registration_closes_at']) > strtotime($d['event_date'] . ' 23:59:59')) {
            throw new DomainError("La clôture des inscriptions ne peut pas être postérieure au jour de la compétition.");
        }
    }

    /** Crée les tapis manquants ; désactive (ou supprime s'ils sont vierges) les tapis au-delà du nombre configuré. */
    public static function syncTatamis(array $competition): void
    {
        $count = (int) $competition['mat_count'];
        $existing = Database::all('SELECT * FROM tatamis WHERE competition_id = ? ORDER BY number', [$competition['id']]);
        $numbers = array_column($existing, 'number');
        for ($n = 1; $n <= $count; $n++) {
            if (!in_array($n, $numbers)) {
                Database::insert('tatamis', ['competition_id' => $competition['id'], 'number' => $n, 'name' => 'Tapis ' . $n]);
            }
        }
        foreach ($existing as $t) {
            $active = (int) $t['number'] <= $count ? 1 : 0;
            if (!$active) {
                $used = Database::value('SELECT 1 FROM pools WHERE tatami_id = ? UNION SELECT 1 FROM bouts WHERE tatami_id = ? LIMIT 1', [$t['id'], $t['id']]);
                if (!$used) {
                    Database::run('DELETE FROM tatamis WHERE id = ?', [$t['id']]);
                    continue;
                }
            }
            if ((int) $t['is_active'] !== $active) {
                Database::run('UPDATE tatamis SET is_active = ? WHERE id = ?', [$active, $t['id']]);
            }
        }
    }

    public static function tatamis(int $competitionId, bool $activeOnly = true): array
    {
        return Database::all(
            'SELECT * FROM tatamis WHERE competition_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY number',
            [$competitionId]
        );
    }

    public static function setStatus(int $id, string $status): array
    {
        if (!isset(self::STATUSES[$status])) {
            throw new DomainError('Statut inconnu.');
        }
        $c = self::find($id);
        if ($status === 'archived') {
            throw new DomainError("Utilisez l'action « Archiver » pour archiver une compétition.");
        }
        self::assertWritable($c);
        $warnings = [];
        if ($status === 'in_progress') {
            $validated = (int) Database::value("SELECT COUNT(*) FROM pool_generations WHERE competition_id = ? AND status = 'validated'", [$id]);
            if (!$validated) {
                throw new DomainError('Validez la composition des poules avant de lancer la compétition.');
            }
            $warnings = DurationRuleService::configurationIssues($id);
        }
        Database::run('UPDATE competitions SET status = ? WHERE id = ?', [$status, $id]);
        AuditLogger::log('competition.status', 'competition', $id, ['status' => $c['status']], ['status' => $status], null, $id);
        return $warnings;
    }

    public static function archive(int $id, string $reason = ''): void
    {
        $c = self::find($id);
        if ($c['status'] === 'archived') {
            return;
        }
        $running = (int) Database::value("SELECT COUNT(*) FROM bouts WHERE competition_id = ? AND status = 'in_progress'", [$id]);
        if ($running) {
            throw new DomainError("Impossible d'archiver : $running combat(s) en cours.");
        }
        Database::transaction(function () use ($id, $c, $reason) {
            RankingService::recomputeCompetition($id);
            Database::run("UPDATE competitions SET status = 'archived', archived_at = NOW(), archived_by = ? WHERE id = ?", [Auth::id(), $id]);
            // Les écrans TV d'une compétition archivée sont désactivés.
            Database::run('UPDATE tv_tokens t JOIN tatamis m ON m.id = t.tatami_id SET t.is_active = 0, t.revoked_at = NOW() WHERE m.competition_id = ? AND t.is_active = 1', [$id]);
            SettingsService::set($id, 'status_before_archive', $c['status']);
            AuditLogger::log('competition.archive', 'competition', $id, ['status' => $c['status']], ['status' => 'archived'], $reason ?: null, $id);
        });
    }

    public static function reopen(int $id, string $reason): void
    {
        $c = self::find($id);
        if ($c['status'] !== 'archived') {
            throw new DomainError("Cette compétition n'est pas archivée.");
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new DomainError('Indiquez le motif de la réouverture (5 caractères minimum).');
        }
        $previous = (string) (SettingsService::get($id, 'status_before_archive') ?: 'finished');
        Database::run('UPDATE competitions SET status = ?, archived_at = NULL, archived_by = NULL WHERE id = ?', [$previous, $id]);
        AuditLogger::log('competition.reopen', 'competition', $id, ['status' => 'archived'], ['status' => $previous], $reason, $id);
    }

    public static function registrationWindowOpen(array $c, ?int $now = null): bool
    {
        $now ??= time();
        if ($c['status'] !== 'registrations_open') {
            return false;
        }
        if ($c['registration_opens_at'] && $now < strtotime($c['registration_opens_at'])) {
            return false;
        }
        if ($c['registration_closes_at'] && $now > strtotime($c['registration_closes_at'])) {
            return false;
        }
        return true;
    }

    public static function stats(int $id): array
    {
        $reg = Database::one(
            "SELECT COUNT(*) AS registered,
                    COUNT(DISTINCT club_id) AS clubs,
                    SUM(attendance_status = 'present') AS present,
                    SUM(attendance_status = 'absent') AS absent,
                    SUM(attendance_status = 'to_check') AS to_check,
                    SUM(attendance_status = 'expected') AS expected,
                    SUM(needs_validation = 1) AS pending_validation
             FROM registrations WHERE competition_id = ? AND status IN ('pending','confirmed')",
            [$id]
        );
        $gen = PoolService::activeGeneration($id);
        $pools = $gen ? (int) Database::value('SELECT COUNT(*) FROM pools WHERE generation_id = ?', [$gen['id']]) : 0;
        $bouts = Database::one(
            "SELECT COUNT(*) AS total, SUM(status = 'validated') AS done, SUM(status = 'in_progress') AS running,
                    SUM(status = 'finished') AS awaiting
             FROM bouts WHERE competition_id = ? AND status <> 'cancelled'",
            [$id]
        );
        return [
            'registered' => (int) $reg['registered'],
            'clubs' => (int) $reg['clubs'],
            'present' => (int) $reg['present'],
            'absent' => (int) $reg['absent'],
            'to_check' => (int) $reg['to_check'],
            'expected' => (int) $reg['expected'],
            'pending_validation' => (int) $reg['pending_validation'],
            'pools' => $pools,
            'generation' => $gen,
            'bouts_total' => (int) $bouts['total'],
            'bouts_done' => (int) $bouts['done'],
            'bouts_running' => (int) $bouts['running'],
            'bouts_awaiting' => (int) $bouts['awaiting'],
            'bouts_remaining' => (int) $bouts['total'] - (int) $bouts['done'],
        ];
    }

    /** Avancement par tapis (combats, temps restant estimé). */
    public static function tatamiProgress(int $competitionId): array
    {
        $gap = (int) SettingsService::get($competitionId, 'bout_gap_seconds');
        return Database::all(
            "SELECT t.id, t.number, t.name, t.is_active, t.current_bout_id,
                    COUNT(DISTINCT p.id) AS pools,
                    COUNT(b.id) AS bouts,
                    COALESCE(SUM(b.status = 'validated'), 0) AS done,
                    COALESCE(SUM(b.status IN ('scheduled','in_progress','finished')), 0) AS remaining,
                    COALESCE(SUM(b.duration_seconds + :gap), 0) AS est_total,
                    COALESCE(SUM(CASE WHEN b.status <> 'validated' THEN b.duration_seconds + :gap2 ELSE 0 END), 0) AS est_remaining
             FROM tatamis t
             LEFT JOIN bouts b ON b.tatami_id = t.id AND b.status <> 'cancelled'
             LEFT JOIN pools p ON p.id = b.pool_id
             WHERE t.competition_id = :cid
             GROUP BY t.id ORDER BY t.number",
            ['gap' => $gap, 'gap2' => $gap, 'cid' => $competitionId]
        );
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $i = 2;
        while (Database::value('SELECT 1 FROM competitions WHERE slug = ?', [$candidate])) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function featured(): ?array
    {
        return Database::one(
            "SELECT * FROM competitions WHERE status NOT IN ('draft','archived','finished')
             ORDER BY is_featured DESC, event_date ASC LIMIT 1"
        ) ?? Database::one("SELECT * FROM competitions WHERE status <> 'draft' ORDER BY event_date DESC LIMIT 1");
    }
}
