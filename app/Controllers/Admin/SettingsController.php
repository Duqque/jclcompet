<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\SettingsService;

final class SettingsController extends AdminController
{
    public const MEDAL_CRITERIA = ['gold' => 'Or', 'silver' => 'Argent', 'bronze' => 'Bronze', 'fourth' => '4e places', 'fifth' => '5e places', 'points' => 'Points', 'participants' => 'Participants'];

    public function edit(Request $r): Response
    {
        $c = $this->competition($r);
        return $this->admin('settings', ['title' => 'Paramètres sportifs', 'competition' => $c, 'c' => $c, 's' => SettingsService::all((int) $c['id'])]);
    }

    public function update(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        $p = $r->post;
        $int = fn ($k, $min, $max, $def) => max($min, min($max, (int) ($p[$k] ?? $def)));
        $bool = fn ($k) => !empty($p[$k]);
        $pick = function ($k, array $allowed) use ($p) {
            if (!in_array($p[$k] ?? null, $allowed, true)) {
                throw new DomainError('Valeur invalide pour ' . $k . '.');
            }
            return $p[$k];
        };
        $osae = ['yuko' => $int('osae_yuko', 1, 30, 5), 'wazaari' => $int('osae_wazaari', 1, 30, 10), 'ippon' => $int('osae_ippon', 1, 40, 20)];
        if (!($osae['yuko'] < $osae['wazaari'] && $osae['wazaari'] < $osae['ippon'])) {
            throw new DomainError('Les seuils d\'osaekomi doivent être croissants (yuko < waza-ari < ippon).');
        }
        $values = [];
        foreach (array_keys(SettingsService::DEFAULTS['pool_ranking']['value_points']) as $m) {
            $values[$m] = max(0, min(100, (int) ($p['vp_' . $m] ?? 0)));
        }
        $order = [];
        foreach ((array) ($p['medal_order'] ?? []) as $crit) {
            if (isset(self::MEDAL_CRITERIA[$crit]) && !in_array($crit, $order, true)) {
                $order[] = $crit;
            }
        }
        $new = [
            'fighter_color' => $pick('fighter_color', ['red', 'blue']),
            'pool_grouping' => $pick('pool_grouping', ['morphological', 'weight_category']),
            'weight_input' => $pick('weight_input', ['weight', 'category']),
            'use_measured_weight' => $bool('use_measured_weight'),
            'preferred_pool_size' => $int('preferred_pool_size', 3, 5, 4),
            'max_weight_spread_pct' => $int('max_weight_spread_pct', 0, 100, 10),
            'avoid_same_club' => $bool('avoid_same_club'),
            'interleave_pools' => $bool('interleave_pools'),
            'interleave_block' => max(1, min(10, (int) ($r->post['interleave_block'] ?? 2))),
            'interleave_scope' => ($r->post['interleave_scope'] ?? 'age') === 'tatami' ? 'tatami' : 'age',
            'score_model' => [
                'use_yuko' => $bool('use_yuko'),
                'wazaari_for_ippon' => $int('wazaari_for_ippon', 1, 5, 2),
                'shido_for_hansoku' => $int('shido_for_hansoku', 1, 5, 3),
                'osaekomi' => $osae,
                'golden_score_first_score_wins' => $bool('golden_score_first_score_wins'),
                'shido_decides' => $bool('shido_decides'),
            ],
            'pool_ranking' => ['criteria' => ['wins', 'value_points', 'head_to_head'], 'value_points' => $values],
            'tie_handling' => $pick('tie_handling', ['manual', 'shared']),
            'forfeit_policy' => $pick('forfeit_policy', ['no_points', 'ranked']),
            'medal_order' => $order ?: SettingsService::DEFAULTS['medal_order'],
            'bout_gap_seconds' => $int('bout_gap_seconds', 0, 900, 60),
            'tv_refresh_ms' => $int('tv_refresh_ms', 500, 10000, 1000),
            'tv_result_seconds' => $int('tv_result_seconds', 3, 300, 20),
            'public_refresh_seconds' => $int('public_refresh_seconds', 30, 900, 120),
            'public_mat_results' => $bool('public_mat_results'),
            'public_live' => $bool('public_live'),
            'public_podium_photos' => $bool('public_podium_photos'),
            'pools_published' => $bool('pools_published'),
            'late_registration_allowed' => $bool('late_registration_allowed'),
            'late_registration_requires_validation' => $bool('late_registration_requires_validation'),
            'require_license' => $bool('require_license'),
            'card_payment_accepted' => $bool('card_payment_accepted'),
        ];
        $old = SettingsService::all($cid);
        $changed = [];
        foreach ($new as $k => $v) {
            if (json_encode($old[$k] ?? null) !== json_encode($v)) {
                SettingsService::set($cid, $k, $v);
                $changed[$k] = $v;
            }
        }
        if ($changed) {
            AuditLogger::log('competition.settings', 'competition', $cid, array_intersect_key($old, $changed), $changed, null, $cid);
        }
        return $this->redirect('/admin/competitions/' . $cid . '/parametres', $changed ? 'Paramètres enregistrés.' : 'Aucune modification.');
    }
}
