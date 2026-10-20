<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Paramètres sportifs et d'affichage propres à chaque compétition (table competition_settings, valeurs JSON).
 * Les valeurs ci-dessous sont des EXEMPLES DE DÉMONSTRATION à confirmer selon le règlement retenu :
 * aucune n'est une règle officielle.
 */
final class SettingsService
{
    public const DEFAULTS = [
        // Modèle de score (inspiré des règles IJF récentes, à confirmer)
        'score_model' => [
            'use_yuko' => true,
            'wazaari_for_ippon' => 2,
            'shido_for_hansoku' => 3,
            'osaekomi' => ['yuko' => 5, 'wazaari' => 10, 'ippon' => 20],
            'golden_score_first_score_wins' => true,
            'shido_decides' => false,
        ],
        // Couleur du premier appelé à l'écran : red (rouge/blanc) ou blue (bleu/blanc)
        'fighter_color' => 'red',
        // Composition des poules
        'pool_grouping' => 'morphological',      // morphological = âge × sexe trié par poids ; weight_category = âge × sexe × catégorie de poids
        'use_measured_weight' => true,           // poids mesuré à la pesée prioritaire sur le poids déclaré
        'preferred_pool_size' => 4,
        'max_weight_spread_pct' => 10,           // seuil d'alerte (écart de poids dans une poule, en %)
        'avoid_same_club' => true,
        'interleave_pools' => true,              // enchaîne les poules d'un même tapis par blocs de combats (récupération)
        'interleave_block' => 2,                 // nombre de combats d'une poule avant de passer à la suivante
        'interleave_scope' => 'age',             // 'age' : poules d'une même catégorie d'âge · 'tatami' : toutes les poules du tapis
        'pool_group_overrides' => [],            // décisions de l'administrateur pour les groupes sans répartition valide
        // Classement des poules
        'pool_ranking' => [
            'criteria' => ['wins', 'value_points', 'head_to_head'],
            'value_points' => ['ippon' => 10, 'hansoku_make' => 10, 'fusen_gachi' => 10, 'kiken_gachi' => 10, 'wazaari' => 7, 'yuko' => 5, 'shido' => 1, 'decision' => 1],
        ],
        'tie_handling' => 'manual',              // manual = départage par l'administrateur ; shared = places ex æquo
        'forfeit_policy' => 'no_points',         // no_points = un athlète n'ayant disputé aucun combat ne marque ni points ni médaille ; ranked = classé normalement
        'medal_order' => ['gold', 'silver', 'bronze', 'fourth', 'fifth', 'points'],
        // Planning et affichage
        'bout_gap_seconds' => 60,
        'tv_refresh_ms' => 1000,
        'tv_result_seconds' => 20,               // durée d'affichage de « VICTOIRE » avant le combat suivant sur l'écran TV
        'public_refresh_seconds' => 120,
        'public_mat_results' => true,
        'public_live' => true,
        'public_podium_photos' => false,         // photos des judokas sur l'annonce des podiums (accord des familles requis)
        'pools_published' => false,
        // Inscriptions
        'late_registration_allowed' => true,
        'late_registration_requires_validation' => true,
        'club_can_edit_until_close' => true,
        'require_license' => false,
        // weight = catégorie déduite du poids déclaré (ou mesuré) ; category = le club choisit la catégorie de poids (pas de pesée)
        'weight_input' => 'weight',
        'card_payment_accepted' => true,
    ];

    private static array $cache = [];

    public static function all(int $competitionId): array
    {
        if (!isset(self::$cache[$competitionId])) {
            $rows = Database::all('SELECT setting_key, setting_value FROM competition_settings WHERE competition_id = ?', [$competitionId]);
            $values = self::DEFAULTS;
            foreach ($rows as $r) {
                $decoded = json_decode($r['setting_value'], true);
                $values[$r['setting_key']] = $decoded ?? $r['setting_value'];
            }
            self::$cache[$competitionId] = $values;
        }
        return self::$cache[$competitionId];
    }

    public static function get(int $competitionId, string $key): mixed
    {
        return self::all($competitionId)[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(int $competitionId, string $key, mixed $value): void
    {
        Database::run(
            'INSERT INTO competition_settings (competition_id, setting_key, setting_value, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$competitionId, $key, json_encode($value, JSON_UNESCAPED_UNICODE), Auth::id()]
        );
        unset(self::$cache[$competitionId]);
    }

    public static function initialize(int $competitionId): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Database::run(
                'INSERT IGNORE INTO competition_settings (competition_id, setting_key, setting_value) VALUES (?, ?, ?)',
                [$competitionId, $key, json_encode($value, JSON_UNESCAPED_UNICODE)]
            );
        }
        unset(self::$cache[$competitionId]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
