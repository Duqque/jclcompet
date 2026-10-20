<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Validator;

/**
 * Inscriptions : contrôle d'âge (année de naissance), de catégorie de poids (poids déclaré ou mesuré),
 * de doublons, de quotas et des dates limites. Contextes : club (espace club), admin, onsite (accueil le jour J).
 */
final class RegistrationService
{
    public const STATUS = ['pending' => 'En attente de validation', 'confirmed' => 'Confirmée', 'withdrawn' => 'Retirée', 'rejected' => 'Refusée'];
    public const ATTENDANCE = ['expected' => 'Attendu', 'present' => 'Présent', 'absent' => 'Absent / forfait', 'to_check' => 'À vérifier'];

    public static function normalizeLastName(string $s): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    public static function normalizeFirstName(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        return preg_replace_callback('/(^|[\s\-\'])(\p{L})/u', fn ($m) => $m[1] . mb_strtoupper($m[2]), $s);
    }

    private static function validateAthlete(array $data): array
    {
        $clean = Validator::validate($data, [
            'last_name' => 'required|maxlen:80',
            'first_name' => 'required|maxlen:80',
            'sex' => 'required|in:M,F',
            'birth_date' => 'required|date',
            'declared_weight_kg' => 'nullable|numeric|min:10|max:250',
            'grade' => 'nullable|maxlen:40',
            'license_number' => 'nullable|maxlen:40',
            'age_category_id' => 'nullable|int',
            'weight_category_id' => 'nullable|int',
            'admin_comment' => 'nullable|maxlen:500',
        ], [
            'last_name' => 'Nom', 'first_name' => 'Prénom', 'sex' => 'Sexe', 'birth_date' => 'Date de naissance',
            'declared_weight_kg' => 'Poids déclaré', 'grade' => 'Grade', 'license_number' => 'Licence',
            'age_category_id' => "Catégorie d'âge", 'weight_category_id' => 'Catégorie de poids', 'admin_comment' => 'Commentaire',
        ]);
        $clean['last_name'] = self::normalizeLastName($clean['last_name']);
        $clean['first_name'] = self::normalizeFirstName($clean['first_name']);
        if (strtotime($clean['birth_date']) > time()) {
            throw new DomainError('La date de naissance ne peut pas être dans le futur.');
        }
        return $clean;
    }

    /**
     * @param array{actor: string, club_id?: ?int, club_exception_label?: ?string} $ctx
     */
    public static function register(int $competitionId, array $data, array $ctx): int
    {
        $competition = CompetitionService::find($competitionId);
        CompetitionService::assertWritable($competition);
        $a = self::validateAthlete($data);
        $actor = $ctx['actor'];
        $clubId = $ctx['club_id'] ?? null;
        $exception = trim((string) ($ctx['club_exception_label'] ?? ''));

        if ($clubId === null && $exception === '') {
            throw new DomainError('Sélectionnez le club de l\'athlète (ou saisissez une exception autorisée).');
        }
        if ($clubId !== null && !Database::value('SELECT 1 FROM clubs WHERE id = ? AND is_active = 1', [$clubId])) {
            throw new DomainError('Club introuvable ou inactif.');
        }
        if (SettingsService::get($competitionId, 'require_license') && empty($a['license_number'])) {
            throw new DomainError('Le numéro de licence est obligatoire pour cette compétition.');
        }

        // Dates limites
        $windowOpen = CompetitionService::registrationWindowOpen($competition);
        $late = false;
        if (!$windowOpen) {
            if ($actor === 'club') {
                throw new DomainError('Les inscriptions ne sont pas ouvertes pour cette compétition (ou la date limite est dépassée).');
            }
            if ($actor === 'onsite' && !SettingsService::get($competitionId, 'late_registration_allowed') && !Auth::can('registrations.manage')) {
                throw new DomainError('Les inscriptions tardives ne sont pas autorisées pour cette compétition.');
            }
            $late = true;
        }

        [$age, $natural] = CategoryService::resolveAgeCategory($competitionId, $a['birth_date'], $a['sex'], $a['age_category_id']);
        if (!$a['weight_category_id'] && !empty($data['weight_category_label'])) {
            $a['weight_category_id'] = self::weightIdFromLabel((int) $age['id'], $a['sex'], (string) $data['weight_category_label']);
        }
        [$weightCat, $byCategory] = self::resolveWeight($competitionId, $age, $a['sex'], $a['declared_weight_kg'], $a['weight_category_id']);

        return Database::transaction(function () use ($competitionId, $a, $clubId, $exception, $age, $natural, $weightCat, $byCategory, $late, $actor, $ctx) {
            self::assertNoDuplicate($competitionId, $a);
            self::assertQuotas($competitionId, (int) $age['id'], $a['sex'], $weightCat ? (int) $weightCat['id'] : null);

            $athleteId = self::findOrCreateAthlete($clubId, $a);
            $needsValidation = $late && SettingsService::get($competitionId, 'late_registration_requires_validation') && $actor !== 'admin';
            $row = [
                'competition_id' => $competitionId,
                'athlete_id' => $athleteId,
                'club_id' => $clubId,
                'club_exception_label' => $clubId === null ? mb_substr($exception, 0, 150) : null,
                'age_category_id' => (int) $age['id'],
                'natural_age_category_id' => (int) $natural['id'],
                'weight_category_id' => $weightCat['id'] ?? null,
                'weight_declared_by_category' => $byCategory ? 1 : 0,
                'declared_weight_kg' => $a['declared_weight_kg'],
                'status' => $needsValidation ? 'pending' : 'confirmed',
                'is_late' => $late ? 1 : 0,
                'is_onsite' => $actor === 'onsite' ? 1 : 0,
                'needs_validation' => $needsValidation ? 1 : 0,
                'admin_comment' => $actor === 'club' ? null : ($a['admin_comment'] ?? null),
                'created_by' => Auth::id(),
            ];
            if (!empty($ctx['present'])) {
                $row['attendance_status'] = 'present';
                $row['attendance_at'] = date('Y-m-d H:i:s');
                $row['attendance_by'] = Auth::id();
                $row['measured_weight_kg'] = $ctx['measured_weight_kg'] ?? null;
            }
            $id = Database::insert('registrations', $row);
            if (!empty($ctx['present'])) {
                Database::insert('attendance_records', ['registration_id' => $id, 'status' => 'present', 'measured_weight_kg' => $row['measured_weight_kg'], 'note' => 'Inscription sur place', 'recorded_by' => Auth::id()]);
            }
            AuditLogger::log('registration.create', 'registration', $id, null, [
                'athlete' => $a['last_name'] . ' ' . $a['first_name'], 'club_id' => $clubId, 'age' => $age['code'],
                'weight' => $weightCat['label'] ?? null, 'late' => $late, 'onsite' => $actor === 'onsite',
            ], null, $competitionId);
            if ($needsValidation) {
                NotificationService::notifyAdmins($competitionId, 'registration.pending', "Inscription tardive à valider : {$a['last_name']} {$a['first_name']}", '/admin/competitions/' . $competitionId . '/inscriptions?filter=pending');
            }
            return $id;
        });
    }

    private static function findOrCreateAthlete(?int $clubId, array $a): int
    {
        $existing = Database::one(
            'SELECT id FROM athletes WHERE club_id <=> ? AND last_name = ? AND first_name = ? AND birth_date = ? FOR UPDATE',
            [$clubId, $a['last_name'], $a['first_name'], $a['birth_date']]
        );
        $fields = ['sex' => $a['sex'], 'grade' => $a['grade'], 'license_number' => $a['license_number']];
        if ($existing) {
            Database::update('athletes', $fields, 'id = :id', ['id' => $existing['id']]);
            return (int) $existing['id'];
        }
        return Database::insert('athletes', $fields + [
            'club_id' => $clubId,
            'last_name' => $a['last_name'],
            'first_name' => $a['first_name'],
            'birth_date' => $a['birth_date'],
        ]);
    }

    private static function assertNoDuplicate(int $competitionId, array $a, ?int $ignoreRegistrationId = null): void
    {
        $dup = Database::one(
            "SELECT r.id, c.name AS club FROM registrations r JOIN athletes at ON at.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id
             WHERE r.competition_id = ? AND at.last_name = ? AND at.first_name = ? AND at.birth_date = ?
               AND r.status IN ('pending','confirmed') AND r.id <> ? LIMIT 1",
            [$competitionId, $a['last_name'], $a['first_name'], $a['birth_date'], $ignoreRegistrationId ?? 0]
        );
        if ($dup) {
            throw new DomainError("Doublon : {$a['first_name']} {$a['last_name']} (né(e) le " . date('d/m/Y', strtotime($a['birth_date'])) . ') est déjà inscrit(e)' . ($dup['club'] ? " pour {$dup['club']}" : '') . '.');
        }
    }

    private static function assertQuotas(int $competitionId, int $ageId, string $sex, ?int $weightId, ?int $ignoreId = null): void
    {
        $active = "status IN ('pending','confirmed') AND id <> " . (int) $ignoreId;
        $c = Database::one('SELECT max_athletes FROM competitions WHERE id = ?', [$competitionId]);
        if ($c['max_athletes'] && (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND $active", [$competitionId]) >= (int) $c['max_athletes']) {
            throw new DomainError('Nombre maximal d\'athlètes atteint pour cette compétition.');
        }
        $ageMax = Database::value('SELECT max_participants FROM age_categories WHERE id = ?', [$ageId]);
        if ($ageMax && (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND age_category_id = ? AND $active", [$competitionId, $ageId]) >= (int) $ageMax) {
            throw new DomainError('Quota atteint pour cette catégorie d\'âge.');
        }
        $ccMax = Database::value('SELECT max_participants FROM competition_categories WHERE competition_id = ? AND age_category_id = ? AND sex = ?', [$competitionId, $ageId, $sex]);
        if ($ccMax && (int) Database::value(
            "SELECT COUNT(*) FROM registrations r JOIN athletes a ON a.id = r.athlete_id WHERE r.competition_id = ? AND r.age_category_id = ? AND a.sex = ? AND r." . str_replace(['status', 'id <>'], ['status', 'r.id <>'], $active),
            [$competitionId, $ageId, $sex]
        ) >= (int) $ccMax) {
            throw new DomainError('Quota atteint pour ce tableau.');
        }
        if ($weightId) {
            $wMax = Database::value('SELECT max_participants FROM weight_categories WHERE id = ?', [$weightId]);
            if ($wMax && (int) Database::value("SELECT COUNT(*) FROM registrations WHERE competition_id = ? AND weight_category_id = ? AND $active", [$competitionId, $weightId]) >= (int) $wMax) {
                throw new DomainError('Quota atteint pour cette catégorie de poids.');
            }
        }
    }

    /**
     * Catégorie de poids : choisie directement par le club (compétition sans pesée, réglage weight_input = category)
     * ou déduite du poids déclaré / mesuré. Jamais déduite de l'âge seul. Aucune pour les catégories « animation ».
     *
     * @return array{0: ?array, 1: bool} [catégorie, choisie directement]
     */
    public static function resolveWeight(int $competitionId, array $age, string $sex, ?float $weight, ?int $weightCategoryId): array
    {
        if (($age['format'] ?? 'pools') === 'animation') {
            return [null, false];
        }
        $hasCats = CategoryService::hasWeightCategories((int) $age['id'], $sex);
        if ($weightCategoryId) {
            $cat = Database::one('SELECT * FROM weight_categories WHERE id = ? AND age_category_id = ? AND sex = ?', [$weightCategoryId, $age['id'], $sex]);
            if (!$cat) {
                throw new DomainError('La catégorie de poids choisie ne correspond pas à la catégorie d\'âge et au sexe de l\'athlète.');
            }
            if ($weight !== null && SettingsService::get($competitionId, 'weight_input') === 'weight') {
                $computed = CategoryService::weightCategoryFor((int) $age['id'], $sex, $weight);
                if ($computed && (int) $computed['id'] !== (int) $cat['id']) {
                    throw new DomainError('Le poids indiqué (' . kg($weight) . ') ne correspond pas à la catégorie ' . $cat['label'] . ' kg.');
                }
            }
            return [$cat, true];
        }
        if ($weight !== null) {
            return [CategoryService::weightCategoryFor((int) $age['id'], $sex, $weight), false];
        }
        if ($hasCats) {
            throw new DomainError(SettingsService::get($competitionId, 'weight_input') === 'category'
                ? 'Choisissez la catégorie de poids de l\'athlète.'
                : 'Le poids est obligatoire pour déterminer la catégorie de poids.');
        }
        if (SettingsService::get($competitionId, 'pool_grouping') === 'morphological') {
            throw new DomainError('Le poids déclaré est obligatoire : il sert à composer les poules morphologiques.');
        }
        return [null, false];
    }

    /** Photo de profil facultative (document privé, visible uniquement des utilisateurs connectés). */
    public static function applyPhoto(int $registrationId, ?array $file, bool $remove = false): void
    {
        $reg = self::find($registrationId);
        if ($remove && $reg['photo_document_id']) {
            Database::run('UPDATE athletes SET photo_document_id = NULL WHERE id = ?', [$reg['athlete_id']]);
            DocumentService::delete((int) $reg['photo_document_id']);
        }
        if ($file) {
            $doc = DocumentService::storeUpload($file, 'athlete_photo', 'Photo ' . $reg['first_name'] . ' ' . $reg['last_name'], null, false);
            $old = Database::value('SELECT photo_document_id FROM athletes WHERE id = ?', [$reg['athlete_id']]);
            Database::run('UPDATE athletes SET photo_document_id = ? WHERE id = ?', [$doc, $reg['athlete_id']]);
            if ($old) {
                DocumentService::delete((int) $old);
            }
            AuditLogger::log('registration.photo', 'registration', $registrationId, null, null, null, (int) $reg['competition_id']);
        }
    }

    /** « -28 », « -28 kg », « +44 » → identifiant de la catégorie de poids (import CSV). */
    public static function weightIdFromLabel(int $ageId, string $sex, string $label): int
    {
        $norm = preg_replace('/\s|kg/i', '', $label);
        if ($norm !== '' && ctype_digit($norm[0])) {
            $norm = '-' . $norm;
        }
        $id = Database::value('SELECT id FROM weight_categories WHERE age_category_id = ? AND sex = ? AND label = ?', [$ageId, $sex, $norm]);
        if (!$id) {
            throw new DomainError("catégorie de poids « $label » inconnue pour cette catégorie d'âge");
        }
        return (int) $id;
    }

    public static function find(int $id): array
    {
        $r = Database::one(
            'SELECT r.*, a.last_name, a.first_name, a.sex, a.birth_date, a.grade, a.license_number, a.photo_document_id, a.club_id AS athlete_club_id,
                    c.name AS club_name, ac.name AS age_name, ac.name_m, ac.name_f, ac.code AS age_code, wc.label AS weight_label
             FROM registrations r JOIN athletes a ON a.id = r.athlete_id
             LEFT JOIN clubs c ON c.id = r.club_id
             JOIN age_categories ac ON ac.id = r.age_category_id
             LEFT JOIN weight_categories wc ON wc.id = r.weight_category_id
             WHERE r.id = ?',
            [$id]
        );
        if (!$r) {
            throw new HttpException(404, 'Inscription introuvable.');
        }
        return $r;
    }

    /** Un club ne voit et ne modifie que ses propres inscriptions. */
    public static function assertClubOwns(array $registration, ?int $clubId): void
    {
        if ($clubId === null || (int) $registration['club_id'] !== $clubId) {
            throw new HttpException(403);
        }
    }

    public static function clubCanEdit(array $competition): bool
    {
        return CompetitionService::registrationWindowOpen($competition);
    }

    public static function inPool(int $registrationId): bool
    {
        return (bool) Database::value(
            "SELECT 1 FROM pool_athletes pa JOIN pool_generations g ON g.id = pa.generation_id WHERE pa.registration_id = ? AND g.status IN ('draft','validated') LIMIT 1",
            [$registrationId]
        );
    }

    public static function update(int $id, array $data, array $ctx): void
    {
        $reg = self::find($id);
        $competition = CompetitionService::find((int) $reg['competition_id']);
        CompetitionService::assertWritable($competition);
        if ($ctx['actor'] === 'club') {
            self::assertClubOwns($reg, $ctx['club_id'] ?? null);
            if (!self::clubCanEdit($competition)) {
                throw new DomainError('La date limite est dépassée : contactez l\'organisateur pour toute modification.');
            }
        }
        if (self::inPool($id)) {
            throw new DomainError('Cet athlète est déjà placé dans une poule : retirez-le de la poule avant de modifier son inscription.');
        }
        $a = self::validateAthlete(array_merge([
            'last_name' => $reg['last_name'], 'first_name' => $reg['first_name'], 'sex' => $reg['sex'], 'birth_date' => $reg['birth_date'],
            'declared_weight_kg' => $reg['declared_weight_kg'], 'grade' => $reg['grade'], 'license_number' => $reg['license_number'],
            'age_category_id' => $reg['age_category_id'], 'admin_comment' => $reg['admin_comment'],
            'weight_category_id' => $reg['weight_declared_by_category'] ? $reg['weight_category_id'] : null,
        ], $data));
        $clubId = $ctx['actor'] === 'admin' && array_key_exists('club_id', $ctx) ? $ctx['club_id'] : $reg['club_id'];
        [$age, $natural] = CategoryService::resolveAgeCategory((int) $reg['competition_id'], $a['birth_date'], $a['sex'], $a['age_category_id']);
        $useMeasured = SettingsService::get((int) $reg['competition_id'], 'use_measured_weight') && $reg['measured_weight_kg'] !== null;
        $weight = $useMeasured ? (float) $reg['measured_weight_kg'] : $a['declared_weight_kg'];
        [$weightCat, $byCategory] = self::resolveWeight((int) $reg['competition_id'], $age, $a['sex'], $weight, $a['weight_category_id']);

        Database::transaction(function () use ($reg, $a, $clubId, $age, $natural, $weightCat, $ctx) {
            self::assertNoDuplicate((int) $reg['competition_id'], $a, (int) $reg['id']);
            if ((int) $age['id'] !== (int) $reg['age_category_id'] || ($weightCat['id'] ?? null) != $reg['weight_category_id']) {
                self::assertQuotas((int) $reg['competition_id'], (int) $age['id'], $a['sex'], $weightCat ? (int) $weightCat['id'] : null, (int) $reg['id']);
            }
            Database::update('athletes', [
                'last_name' => $a['last_name'], 'first_name' => $a['first_name'], 'sex' => $a['sex'], 'birth_date' => $a['birth_date'],
                'grade' => $a['grade'], 'license_number' => $a['license_number'], 'club_id' => $clubId,
            ], 'id = :id', ['id' => $reg['athlete_id']]);
            $row = [
                'club_id' => $clubId,
                'age_category_id' => (int) $age['id'],
                'natural_age_category_id' => (int) $natural['id'],
                'weight_category_id' => $weightCat['id'] ?? null,
                'weight_declared_by_category' => $byCategory ? 1 : 0,
                'declared_weight_kg' => $a['declared_weight_kg'],
            ];
            if ($ctx['actor'] !== 'club') {
                $row['admin_comment'] = $a['admin_comment'];
            }
            Database::update('registrations', $row, 'id = :id', ['id' => $reg['id']]);
            AuditLogger::log('registration.update', 'registration', (int) $reg['id'],
                array_intersect_key($reg, $row + ['last_name' => 1, 'first_name' => 1, 'birth_date' => 1]),
                $row + ['last_name' => $a['last_name'], 'first_name' => $a['first_name'], 'birth_date' => $a['birth_date']],
                null, (int) $reg['competition_id']);
        });
    }

    public static function withdraw(int $id, array $ctx, ?string $reason = null): void
    {
        $reg = self::find($id);
        $competition = CompetitionService::find((int) $reg['competition_id']);
        CompetitionService::assertWritable($competition);
        if ($ctx['actor'] === 'club') {
            self::assertClubOwns($reg, $ctx['club_id'] ?? null);
            if (!self::clubCanEdit($competition)) {
                throw new DomainError('La date limite est dépassée : contactez l\'organisateur pour retirer cet athlète.');
            }
        }
        if (self::inPool($id)) {
            throw new DomainError('Cet athlète est placé dans une poule : retirez-le de la poule (ou déclarez-le forfait) depuis la gestion des poules.');
        }
        Database::run("UPDATE registrations SET status = 'withdrawn' WHERE id = ?", [$id]);
        AuditLogger::log('registration.withdraw', 'registration', $id, ['status' => $reg['status']], ['status' => 'withdrawn'], $reason, (int) $reg['competition_id']);
    }

    public static function restore(int $id): void
    {
        $reg = self::find($id);
        CompetitionService::assertWritable((int) $reg['competition_id']);
        self::assertNoDuplicate((int) $reg['competition_id'], $reg, $id);
        Database::run("UPDATE registrations SET status = 'confirmed', needs_validation = 0 WHERE id = ?", [$id]);
        AuditLogger::log('registration.restore', 'registration', $id, ['status' => $reg['status']], ['status' => 'confirmed'], null, (int) $reg['competition_id']);
    }

    public static function validate(int $id, bool $accept, ?string $reason = null): void
    {
        $reg = self::find($id);
        CompetitionService::assertWritable((int) $reg['competition_id']);
        $status = $accept ? 'confirmed' : 'rejected';
        Database::run('UPDATE registrations SET status = ?, needs_validation = 0, validated_by = ?, validated_at = NOW() WHERE id = ?', [$status, Auth::id(), $id]);
        AuditLogger::log($accept ? 'registration.validate' : 'registration.reject', 'registration', $id, ['status' => $reg['status']], ['status' => $status], $reason, (int) $reg['competition_id']);
    }

    /**
     * Liste filtrée. $clubId non nul = restreint au club (isolation des données entre clubs).
     */
    public static function list(int $competitionId, array $f = [], ?int $clubId = null, int $limit = 1000, int $offset = 0): array
    {
        [$where, $p] = self::filterSql($competitionId, $f, $clubId);
        return Database::all(
            "SELECT r.*, a.last_name, a.first_name, a.sex, a.birth_date, a.grade, a.license_number, a.photo_document_id,
                    c.name AS club_name, c.short_name AS club_short, c.logo_path AS club_logo, ac.name AS age_name, ac.name_m, ac.name_f, ac.code AS age_code, wc.label AS weight_label,
                    u.display_name AS attendance_by_name
             FROM registrations r JOIN athletes a ON a.id = r.athlete_id
             LEFT JOIN clubs c ON c.id = r.club_id
             JOIN age_categories ac ON ac.id = r.age_category_id
             LEFT JOIN weight_categories wc ON wc.id = r.weight_category_id
             LEFT JOIN users u ON u.id = r.attendance_by
             WHERE $where
             ORDER BY ac.sort_order, a.sex, COALESCE(wc.sort_order, 0), a.last_name, a.first_name
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
    }

    public static function count(int $competitionId, array $f = [], ?int $clubId = null): int
    {
        [$where, $p] = self::filterSql($competitionId, $f, $clubId);
        return (int) Database::value(
            "SELECT COUNT(*) FROM registrations r JOIN athletes a ON a.id = r.athlete_id LEFT JOIN clubs c ON c.id = r.club_id
             JOIN age_categories ac ON ac.id = r.age_category_id WHERE $where",
            $p
        );
    }

    private static function filterSql(int $competitionId, array $f, ?int $clubId): array
    {
        $where = ['r.competition_id = :cid'];
        $p = ['cid' => $competitionId];
        if ($clubId !== null) {
            $where[] = 'r.club_id = :club';
            $p['club'] = $clubId;
        } elseif (!empty($f['club_id'])) {
            $where[] = 'r.club_id = :club';
            $p['club'] = (int) $f['club_id'];
        }
        if (!empty($f['age_category_id'])) {
            $where[] = 'r.age_category_id = :age';
            $p['age'] = (int) $f['age_category_id'];
        }
        if (!empty($f['sex']) && in_array($f['sex'], ['M', 'F'], true)) {
            $where[] = 'a.sex = :sex';
            $p['sex'] = $f['sex'];
        }
        if (!empty($f['attendance']) && isset(self::ATTENDANCE[$f['attendance']])) {
            $where[] = 'r.attendance_status = :att';
            $p['att'] = $f['attendance'];
        }
        $status = $f['status'] ?? 'active';
        if ($status === 'active') {
            $where[] = "r.status IN ('pending','confirmed')";
        } elseif ($status === 'pending') {
            $where[] = 'r.needs_validation = 1';
        } elseif (isset(self::STATUS[$status])) {
            $where[] = 'r.status = :st';
            $p['st'] = $status;
        }
        if (!empty($f['q'])) {
            $where[] = '(a.last_name LIKE :q1 OR a.first_name LIKE :q2 OR c.name LIKE :q3 OR CONCAT(a.first_name, " ", a.last_name) LIKE :q4)';
            $q = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $f['q']) . '%';
            $p += ['q1' => $q, 'q2' => $q, 'q3' => $q, 'q4' => $q];
        }
        return [implode(' AND ', $where), $p];
    }

    // ------------------------------------------------------------------
    // CSV
    // ------------------------------------------------------------------
    public const CSV_COLUMNS = ['nom', 'prenom', 'sexe', 'date_naissance', 'poids_declare', 'grade', 'licence', 'club', 'categorie_age', 'categorie_poids', 'statut', 'presence', 'poids_mesure'];

    public static function exportCsv(int $competitionId, ?int $clubId = null): string
    {
        $rows = self::list($competitionId, ['status' => 'all'], $clubId, 100000);
        return CsvService::build(self::CSV_COLUMNS, array_map(fn ($r) => [
            $r['last_name'], $r['first_name'], $r['sex'], date('d/m/Y', strtotime($r['birth_date'])),
            $r['declared_weight_kg'] === null ? '' : str_replace('.', ',', (string) (float) $r['declared_weight_kg']),
            $r['grade'], $r['license_number'], $r['club_name'] ?? $r['club_exception_label'], $r['age_name'], $r['weight_label'],
            self::STATUS[$r['status']], self::ATTENDANCE[$r['attendance_status']],
            $r['measured_weight_kg'] === null ? '' : str_replace('.', ',', (string) (float) $r['measured_weight_kg']),
        ], $rows));
    }

    /**
     * Import CSV (séparateur ; ou ,). Colonnes minimales : nom, prenom, sexe, date_naissance ; poids_declare ; club (admin).
     * Chaque ligne est traitée indépendamment : les lignes en erreur sont listées, aucune n'est ignorée silencieusement.
     *
     * @return array{created: int, errors: array<int, string>}
     */
    public static function importCsv(int $competitionId, string $content, array $ctx): array
    {
        $rows = CsvService::parse($content);
        if (!$rows) {
            throw new DomainError('Fichier vide ou illisible.');
        }
        $created = 0;
        $errors = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            try {
                $clubId = $ctx['club_id'] ?? null;
                if ($ctx['actor'] === 'admin') {
                    $clubName = trim((string) ($row['club'] ?? ''));
                    $clubId = $clubName === '' ? null : (int) Database::value('SELECT id FROM clubs WHERE name = ? OR short_name = ? LIMIT 1', [$clubName, $clubName]);
                    if (!$clubId) {
                        throw new DomainError("club « $clubName » inconnu");
                    }
                }
                $birth = self::parseDate((string) ($row['date_naissance'] ?? ''));
                $sex = strtoupper(substr(trim((string) ($row['sexe'] ?? '')), 0, 1));
                $sex = $sex === 'H' ? 'M' : $sex;
                self::register($competitionId, [
                    'last_name' => $row['nom'] ?? '',
                    'first_name' => $row['prenom'] ?? ($row['prénom'] ?? ''),
                    'sex' => $sex,
                    'birth_date' => $birth,
                    'declared_weight_kg' => str_replace(',', '.', (string) ($row['poids_declare'] ?? ($row['poids'] ?? ''))),
                    'grade' => $row['grade'] ?? null,
                    'license_number' => $row['licence'] ?? null,
                    'weight_category_label' => $row['categorie_poids'] ?? null,
                ], ['actor' => $ctx['actor'], 'club_id' => $clubId]);
                $created++;
            } catch (HttpException $e) {
                $errors[$line] = $e instanceof \App\Core\ValidationException ? implode(' ', $e->errors) : $e->getMessage();
            }
        }
        AuditLogger::log('registration.import', 'competition', $competitionId, null, ['created' => $created, 'errors' => count($errors)], null, $competitionId);
        return ['created' => $created, 'errors' => $errors];
    }

    private static function parseDate(string $s): string
    {
        $s = trim($s);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!' . $fmt, $s);
            if ($d && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }
        return $s;
    }
}
