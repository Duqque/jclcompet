<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Règles de durée de combat. Les durées réglementaires ne sont jamais codées en dur dans le moteur :
 * chaque combat copie la règle applicable au moment de sa création (instantané), une modification ultérieure
 * ne change pas les combats existants sans action explicite (reapplyToScheduledBouts).
 */
final class DurationRuleService
{
    /** Exemples de démonstration (is_example = 1) — à confirmer selon le règlement. */
    public const EXAMPLES = [
        ''    => ['duration' => 180, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Règle par défaut (exemple)'],
        'U9'  => ['duration' => 90,  'gs' => 'limited',   'gs_s' => 60,   'recovery' => 0, 'label' => 'Mini-poussins (exemple)'],
        'U11' => ['duration' => 120, 'gs' => 'limited',   'gs_s' => 60,   'recovery' => 0, 'label' => 'Poussins (exemple)'],
        'U13' => ['duration' => 120, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Benjamins (exemple)'],
        'U15' => ['duration' => 180, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Minimes (exemple)'],
        'U18' => ['duration' => 240, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Cadets (exemple)'],
        'U21' => ['duration' => 240, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Juniors (exemple)'],
        'SEN' => ['duration' => 240, 'gs' => 'unlimited', 'gs_s' => null, 'recovery' => 0, 'label' => 'Seniors (exemple)'],
    ];

    public static function createExamples(int $competitionId): void
    {
        $ages = Database::all('SELECT id, code FROM age_categories WHERE competition_id = ?', [$competitionId]);
        $byCode = array_column($ages, 'id', 'code');
        foreach (self::EXAMPLES as $code => $ex) {
            if ($code !== '' && !isset($byCode[$code])) {
                continue;
            }
            Database::insert('fight_duration_rules', [
                'competition_id' => $competitionId,
                'age_category_id' => $code ? $byCode[$code] : null,
                'phase' => 'all',
                'label' => $ex['label'],
                'duration_seconds' => $ex['duration'],
                'golden_score_mode' => $ex['gs'],
                'golden_score_seconds' => $ex['gs_s'],
                'recovery_seconds' => $ex['recovery'],
                'priority' => $code ? 10 : 0,
                'is_example' => 1,
            ]);
        }
    }

    public static function rules(int $competitionId): array
    {
        return Database::all(
            'SELECT r.*, a.name AS age_name, a.code AS age_code FROM fight_duration_rules r
             LEFT JOIN age_categories a ON a.id = r.age_category_id
             WHERE r.competition_id = ? ORDER BY a.sort_order IS NULL, a.sort_order, r.sex, r.phase, r.priority DESC',
            [$competitionId]
        );
    }

    /**
     * Règle applicable : la plus spécifique (catégorie d'âge, sexe, phase), puis la priorité la plus haute,
     * parmi les règles valides à la date de la compétition.
     */
    public static function resolve(int $competitionId, int $ageId, string $sex, string $phase = 'pool', ?string $date = null): ?array
    {
        $date ??= (string) Database::value('SELECT event_date FROM competitions WHERE id = ?', [$competitionId]);
        return Database::one(
            "SELECT * FROM fight_duration_rules
             WHERE competition_id = :cid
               AND (age_category_id = :age OR age_category_id IS NULL)
               AND (sex = :sex OR sex IS NULL)
               AND (phase = :phase OR phase = 'all')
               AND (valid_from IS NULL OR valid_from <= :d1)
               AND (valid_until IS NULL OR valid_until >= :d2)
             ORDER BY (age_category_id IS NOT NULL) * 4 + (sex IS NOT NULL) * 2 + (phase <> 'all') DESC, priority DESC, id DESC
             LIMIT 1",
            ['cid' => $competitionId, 'age' => $ageId, 'sex' => $sex, 'phase' => $phase, 'd1' => $date, 'd2' => $date]
        );
    }

    /** Anomalies à signaler avant le lancement : tableaux sans règle, règles par défaut ou exemples non confirmés. */
    public static function configurationIssues(int $competitionId): array
    {
        $issues = [];
        $cats = Database::all(
            'SELECT cc.age_category_id, cc.sex, a.name FROM competition_categories cc JOIN age_categories a ON a.id = cc.age_category_id
             WHERE cc.competition_id = ? AND cc.is_open = 1 ORDER BY a.sort_order, cc.sex',
            [$competitionId]
        );
        foreach ($cats as $cat) {
            $rule = self::resolve($competitionId, (int) $cat['age_category_id'], $cat['sex']);
            $label = $cat['name'] . ' ' . sex_label($cat['sex']);
            if (!$rule) {
                $issues[] = ['level' => 'error', 'message' => "Aucune durée de combat définie pour $label."];
            } elseif ($rule['age_category_id'] === null) {
                $issues[] = ['level' => 'warning', 'message' => "$label utilise la règle par défaut (" . duration_label((int) $rule['duration_seconds']) . ').'];
            }
        }
        $examples = (int) Database::value('SELECT COUNT(*) FROM fight_duration_rules WHERE competition_id = ? AND is_example = 1', [$competitionId]);
        if ($examples) {
            $issues[] = ['level' => 'warning', 'message' => "$examples configuration(s) de combat sont encore des exemples non confirmés."];
        }
        return $issues;
    }

    /** Champs d'une configuration qui redéfinissent le barème de la compétition (NULL = valeur des paramètres sportifs). */
    public const OVERRIDES = ['use_yuko', 'osaekomi_yuko_s', 'osaekomi_wazaari_s', 'osaekomi_ippon_s', 'wazaari_for_ippon', 'shido_for_hansoku', 'golden_first_score_wins'];

    /** Copie figée de la configuration, enregistrée sur chaque combat. */
    public static function snapshot(?array $rule): ?string
    {
        if (!$rule) {
            return null;
        }
        $out = ['label' => $rule['label'] ?? null, 'special_rules' => $rule['special_rules'] ?? null];
        foreach (self::OVERRIDES as $k) {
            if (isset($rule[$k]) && $rule[$k] !== null && $rule[$k] !== '') {
                $out[$k] = (int) $rule[$k];
            }
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** Barème applicable à un combat : paramètres sportifs de la compétition + configuration de la catégorie. */
    public static function model(int $competitionId, ?string $rulesJson): array
    {
        $m = (array) SettingsService::get($competitionId, 'score_model');
        $m['osaekomi'] = (array) ($m['osaekomi'] ?? ['yuko' => 5, 'wazaari' => 10, 'ippon' => 20]);
        $r = $rulesJson ? (json_decode($rulesJson, true) ?: []) : [];
        if (isset($r['use_yuko'])) { $m['use_yuko'] = (bool) $r['use_yuko']; }
        if (isset($r['osaekomi_yuko_s'])) { $m['osaekomi']['yuko'] = (int) $r['osaekomi_yuko_s']; }
        if (isset($r['osaekomi_wazaari_s'])) { $m['osaekomi']['wazaari'] = (int) $r['osaekomi_wazaari_s']; }
        if (isset($r['osaekomi_ippon_s'])) { $m['osaekomi']['ippon'] = (int) $r['osaekomi_ippon_s']; }
        if (isset($r['wazaari_for_ippon'])) { $m['wazaari_for_ippon'] = (int) $r['wazaari_for_ippon']; }
        if (isset($r['shido_for_hansoku'])) { $m['shido_for_hansoku'] = (int) $r['shido_for_hansoku']; }
        if (isset($r['golden_first_score_wins'])) { $m['golden_score_first_score_wins'] = (bool) $r['golden_first_score_wins']; }
        $m['config_label'] = $r['label'] ?? null;
        $m['special_rules'] = $r['special_rules'] ?? null;
        return $m;
    }

    /** Réapplique les règles actuelles aux combats non commencés (action explicite de l'administrateur). */
    public static function reapplyToScheduledBouts(int $competitionId): int
    {
        CompetitionService::assertWritable($competitionId);
        $bouts = Database::all(
            "SELECT b.id, b.phase, p.age_category_id, p.sex FROM bouts b JOIN pools p ON p.id = b.pool_id
             WHERE b.competition_id = ? AND b.status = 'scheduled'",
            [$competitionId]
        );
        $n = 0;
        foreach ($bouts as $b) {
            $rule = self::resolve($competitionId, (int) $b['age_category_id'], $b['sex'], $b['phase']);
            if (!$rule) {
                continue;
            }
            $n += Database::run(
                "UPDATE bouts SET duration_rule_id = ?, duration_seconds = ?, golden_score_mode = ?, golden_score_seconds = ?,
                        recovery_seconds = ?, rules_json = ?, version = version + 1 WHERE id = ? AND status = 'scheduled'",
                [$rule['id'], $rule['duration_seconds'], $rule['golden_score_mode'], $rule['golden_score_seconds'], $rule['recovery_seconds'], self::snapshot($rule), $b['id']]
            )->rowCount();
        }
        AuditLogger::log('durations.reapply', 'competition', $competitionId, null, ['bouts' => $n], null, $competitionId);
        return $n;
    }
}
