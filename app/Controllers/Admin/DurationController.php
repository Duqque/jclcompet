<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\DurationRuleService;

final class DurationController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        return $this->admin('durations', [
            'title' => 'Durées de combat', 'competition' => $c, 'c' => $c, 'rules' => DurationRuleService::rules($cid),
            'ages' => CategoryService::ageCategories($cid), 'issues' => DurationRuleService::configurationIssues($cid),
            'scheduled' => (int) Database::value("SELECT COUNT(*) FROM bouts WHERE competition_id = ? AND status = 'scheduled'", [$cid]),
        ]);
    }

    public function save(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        $op = $r->str('op');
        if ($op === 'delete') {
            $id = (int) $r->int('rule_id');
            Database::run('DELETE FROM fight_duration_rules WHERE id = ? AND competition_id = ?', [$id, $cid]);
            AuditLogger::log('durations.delete', 'fight_duration_rule', $id, null, null, null, $cid);
            return $this->back($r, 'Règle supprimée. Les combats déjà créés conservent leur durée.');
        }
        if ($op === 'reapply') {
            $n = DurationRuleService::reapplyToScheduledBouts($cid);
            return $this->back($r, "$n combat(s) à venir mis à jour avec les règles actuelles.");
        }
        $d = Validator::validate($r->post, [
            'age_category_id' => 'nullable|int', 'sex' => 'nullable|in:M,F', 'phase' => 'required|in:all,pool,final', 'label' => 'nullable|maxlen:120',
            'minutes' => 'required|int|min:0|max:20', 'seconds' => 'required|int|min:0|max:59', 'golden_score_mode' => 'required|in:none,limited,unlimited',
            'golden_score_seconds' => 'nullable|int|min:0|max:600', 'recovery_seconds' => 'nullable|int|min:0|max:3600', 'overtime_rule' => 'nullable|maxlen:255',
            'priority' => 'nullable|int', 'valid_from' => 'nullable|date', 'valid_until' => 'nullable|date', 'is_example' => 'bool',
        ], ['minutes' => 'Minutes', 'seconds' => 'Secondes']);
        $duration = $d['minutes'] * 60 + $d['seconds'];
        if ($duration < 10) {
            throw new DomainError('La durée réglementaire doit être d\'au moins 10 secondes.');
        }
        if ($d['golden_score_mode'] === 'limited' && !$d['golden_score_seconds']) {
            throw new DomainError('Précisez la durée du golden score limité.');
        }
        $row = [
            'competition_id' => $cid, 'age_category_id' => $d['age_category_id'] ?: null, 'sex' => $d['sex'], 'phase' => $d['phase'], 'label' => $d['label'],
            'duration_seconds' => $duration, 'golden_score_mode' => $d['golden_score_mode'],
            'golden_score_seconds' => $d['golden_score_mode'] === 'limited' ? $d['golden_score_seconds'] : null,
            'recovery_seconds' => (int) ($d['recovery_seconds'] ?? 0), 'overtime_rule' => $d['overtime_rule'], 'priority' => (int) ($d['priority'] ?? 0),
            'valid_from' => $d['valid_from'], 'valid_until' => $d['valid_until'], 'is_example' => $d['is_example'],
        ];
        $id = $r->int('rule_id');
        if ($id) {
            Database::update('fight_duration_rules', $row, 'id = :id AND competition_id = :cid', ['id' => $id, 'cid' => $cid]);
        } else {
            $id = Database::insert('fight_duration_rules', $row);
        }
        AuditLogger::log('durations.save', 'fight_duration_rule', $id, null, $row, null, $cid);
        return $this->back($r, 'Règle enregistrée. Les combats déjà créés conservent leur durée (utilisez « Réappliquer » pour les combats à venir).');
    }
}
