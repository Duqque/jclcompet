<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Catégories d'âge et de poids. Les modèles ci-dessous sont des EXEMPLES à confirmer avec le règlement
 * de chaque compétition : l'administrateur ajuste années de naissance, poids et surclassements.
 */
final class CategoryService
{
    /** Âge atteint dans l'année de référence (année de la date de référence de la compétition). */
    public const TEMPLATES = [
        ['code' => 'U9',  'name' => 'Mini-poussin(e)s', 'name_m' => 'Mini-poussins', 'name_f' => 'Mini-poussines', 'min_age' => 7,  'max_age' => 8],
        ['code' => 'U11', 'name' => 'Poussin(e)s', 'name_m' => 'Poussins', 'name_f' => 'Poussines',      'min_age' => 9,  'max_age' => 10],
        ['code' => 'U13', 'name' => 'Benjamin(e)s', 'name_m' => 'Benjamins', 'name_f' => 'Benjamines',     'min_age' => 11, 'max_age' => 12],
        ['code' => 'U15', 'name' => 'Minimes', 'name_m' => 'Minimes Garçons', 'name_f' => 'Minimes Filles',       'min_age' => 13, 'max_age' => 14],
        ['code' => 'U18', 'name' => 'Cadet(te)s', 'name_m' => 'Cadets', 'name_f' => 'Cadettes',        'min_age' => 15, 'max_age' => 17],
        ['code' => 'U21', 'name' => 'Juniors', 'name_m' => 'Juniors Garçons', 'name_f' => 'Juniors Filles',       'min_age' => 18, 'max_age' => 20],
        ['code' => 'SEN', 'name' => 'Seniors', 'name_m' => 'Seniors Hommes', 'name_f' => 'Seniors Femmes',       'min_age' => 21, 'max_age' => 45],
    ];

    /** Catégories de poids d'exemple (kg, limite supérieure ; null = « + »). */
    public const WEIGHT_TEMPLATES = [
        'U13' => [
            'M' => [30, 34, 38, 42, 46, 50, 55, 60, null],
            'F' => [28, 32, 36, 40, 44, 48, 52, 57, null],
        ],
        'U15' => [
            'M' => [34, 38, 42, 46, 50, 55, 60, 66, 73, null],
            'F' => [36, 40, 44, 48, 52, 57, 63, 70, null],
        ],
        'U18' => [
            'M' => [46, 50, 55, 60, 66, 73, 81, 90, null],
            'F' => [40, 44, 48, 52, 57, 63, 70, null],
        ],
        'U21' => [
            'M' => [60, 66, 73, 81, 90, 100, null],
            'F' => [48, 52, 57, 63, 70, 78, null],
        ],
        'SEN' => [
            'M' => [60, 66, 73, 81, 90, 100, null],
            'F' => [48, 52, 57, 63, 70, 78, null],
        ],
    ];

    public static function referenceYear(array $competition): int
    {
        $ref = $competition['age_reference_date'] ?: $competition['event_date'];
        return (int) substr((string) $ref, 0, 4);
    }

    /** Crée les catégories d'âge choisies (codes) pour une compétition, avec poids d'exemple facultatifs. */
    public static function createFromTemplates(array $competition, array $codes, bool $withWeights = true, array $sexes = ['M', 'F']): void
    {
        $year = self::referenceYear($competition);
        $order = 0;
        foreach (self::TEMPLATES as $tpl) {
            if (!in_array($tpl['code'], $codes, true)) {
                continue;
            }
            $order += 10;
            $ageId = Database::insert('age_categories', [
                'competition_id' => (int) $competition['id'],
                'code' => $tpl['code'],
                'name' => $tpl['name'],
                'name_m' => $tpl['name_m'],
                'name_f' => $tpl['name_f'],
                'birth_year_min' => $year - $tpl['max_age'],
                'birth_year_max' => $year - $tpl['min_age'],
                'sort_order' => $order,
            ]);
            foreach ($sexes as $sex) {
                Database::insert('competition_categories', [
                    'competition_id' => (int) $competition['id'],
                    'age_category_id' => $ageId,
                    'sex' => $sex,
                ]);
                if ($withWeights && isset(self::WEIGHT_TEMPLATES[$tpl['code']][$sex])) {
                    self::createWeights((int) $competition['id'], $ageId, $sex, self::WEIGHT_TEMPLATES[$tpl['code']][$sex]);
                }
            }
        }
    }

    public static function createWeights(int $competitionId, int $ageId, string $sex, array $limits): void
    {
        $prev = null;
        foreach (array_values($limits) as $i => $max) {
            Database::insert('weight_categories', [
                'competition_id' => $competitionId,
                'age_category_id' => $ageId,
                'sex' => $sex,
                'label' => $max === null ? '+' . $prev : '-' . $max,
                'min_weight_kg' => $prev,
                'max_weight_kg' => $max,
                'sort_order' => ($i + 1) * 10,
            ]);
            $prev = $max ?? $prev;
        }
    }

    public static function ageCategories(int $competitionId): array
    {
        return Database::all('SELECT * FROM age_categories WHERE competition_id = ? ORDER BY sort_order, birth_year_max DESC', [$competitionId]);
    }

    public static function weightCategories(int $competitionId, ?int $ageId = null, ?string $sex = null): array
    {
        $sql = 'SELECT * FROM weight_categories WHERE competition_id = ?';
        $p = [$competitionId];
        if ($ageId !== null) {
            $sql .= ' AND age_category_id = ?';
            $p[] = $ageId;
        }
        if ($sex !== null) {
            $sql .= ' AND sex = ?';
            $p[] = $sex;
        }
        return Database::all($sql . ' ORDER BY age_category_id, sex, sort_order', $p);
    }

    /** Catégorie d'âge « naturelle » d'après l'année de naissance. */
    public static function naturalAgeCategory(int $competitionId, string $birthDate): ?array
    {
        $year = (int) substr($birthDate, 0, 4);
        return Database::one(
            'SELECT * FROM age_categories WHERE competition_id = ? AND ? BETWEEN birth_year_min AND birth_year_max ORDER BY sort_order LIMIT 1',
            [$competitionId, $year]
        );
    }

    /**
     * Détermine la catégorie d'âge de compétition : la catégorie naturelle, ou la catégorie demandée si elle
     * correspond à un surclassement autorisé. Retourne [catégorie retenue, catégorie naturelle] ou lève une erreur.
     *
     * @return array{0: array, 1: array}
     */
    public static function resolveAgeCategory(int $competitionId, string $birthDate, string $sex, ?int $requestedId = null): array
    {
        $natural = self::naturalAgeCategory($competitionId, $birthDate);
        if (!$natural) {
            throw new DomainError("L'année de naissance " . substr($birthDate, 0, 4) . " ne correspond à aucune catégorie d'âge autorisée pour cette compétition.");
        }
        $chosen = $natural;
        if ($requestedId !== null && $requestedId !== (int) $natural['id']) {
            if ((int) ($natural['upgrade_to_id'] ?? 0) !== $requestedId) {
                throw new DomainError("Surclassement non autorisé de « {$natural['name']} » vers la catégorie demandée.");
            }
            $chosen = Database::one('SELECT * FROM age_categories WHERE id = ? AND competition_id = ?', [$requestedId, $competitionId]);
            if (!$chosen) {
                throw new DomainError('Catégorie demandée introuvable.');
            }
        }
        $open = Database::one(
            'SELECT * FROM competition_categories WHERE competition_id = ? AND age_category_id = ? AND sex = ? AND is_open = 1',
            [$competitionId, (int) $chosen['id'], $sex]
        );
        if (!$open) {
            throw new DomainError("La catégorie « {$chosen['name']} " . sex_label($sex) . " » n'est pas ouverte dans cette compétition.");
        }
        return [$chosen, $natural];
    }

    /**
     * Catégorie de poids à partir du poids (déclaré ou mesuré). La catégorie de poids ne se déduit jamais de l'âge seul.
     * Retourne null si la catégorie d'âge n'a pas de catégories de poids (poules morphologiques).
     */
    public static function weightCategoryFor(int $ageId, string $sex, ?float $weight): ?array
    {
        $cats = Database::all('SELECT * FROM weight_categories WHERE age_category_id = ? AND sex = ? ORDER BY sort_order', [$ageId, $sex]);
        if (!$cats) {
            return null;
        }
        if ($weight === null) {
            throw new DomainError('Le poids est obligatoire pour déterminer la catégorie de poids.');
        }
        foreach ($cats as $c) {
            if ($c['max_weight_kg'] === null || $weight <= (float) $c['max_weight_kg']) {
                return $c;
            }
        }
        throw new DomainError('Aucune catégorie de poids ne correspond à ' . kg($weight) . '.');
    }

    /** Intitulé selon le sexe (« Poussines », « Minimes Garçons »…), à défaut l'intitulé commun. */
    public static function label(array $age, ?string $sex = null): string
    {
        $name = match ($sex) {
            'M' => $age['name_m'] ?? null,
            'F' => $age['name_f'] ?? null,
            default => null,
        };
        if ($name) {
            return $name;
        }
        return (string) $age['name'] . ($sex ? ' ' . ($sex === 'M' ? 'Garçons' : 'Filles') : '');
    }

    public static function birthYearsLabel(array $age): string
    {
        $years = range((int) $age['birth_year_max'], (int) $age['birth_year_min']);
        return implode('/', array_reverse($years));
    }

    public static function hasWeightCategories(int $ageId, string $sex): bool
    {
        return (bool) Database::value('SELECT 1 FROM weight_categories WHERE age_category_id = ? AND sex = ? LIMIT 1', [$ageId, $sex]);
    }

    public static function ageAt(string $birthDate, string $date): int
    {
        return (new \DateTime($birthDate))->diff(new \DateTime($date))->y;
    }
}
