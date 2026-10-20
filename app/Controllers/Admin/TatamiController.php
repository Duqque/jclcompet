<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\TvTokenService;

final class TatamiController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $assign = [];
        foreach (Database::all(
            'SELECT ct.*, u.display_name, u.email FROM commissioner_tatamis ct JOIN users u ON u.id = ct.user_id JOIN tatamis t ON t.id = ct.tatami_id WHERE t.competition_id = ?',
            [$cid]
        ) as $a) {
            $assign[$a['tatami_id']][] = $a;
        }
        $tokens = [];
        foreach (TvTokenService::forCompetition($cid) as $t) {
            $tokens[$t['tatami_id']][] = $t;
        }
        return $this->admin('tatamis', [
            'title' => 'Tapis et écrans TV', 'competition' => $c, 'c' => $c,
            'tatamis' => CompetitionService::tatamis($cid, false), 'assign' => $assign, 'tokens' => $tokens,
            'commissioners' => Database::all("SELECT u.id, u.display_name, u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'commissioner' AND u.is_active = 1 ORDER BY u.display_name"),
            'newLink' => Session::get('tv_new_link'),
        ]);
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        $tatamiId = (int) $r->int('tatami_id');
        if ($tatamiId && !Database::value('SELECT 1 FROM tatamis WHERE id = ? AND competition_id = ?', [$tatamiId, $cid])) {
            throw new DomainError('Tapis invalide.');
        }
        Session::forget('tv_new_link');
        switch ($r->str('op')) {
            case 'assign':
                $userId = (int) $r->int('user_id');
                if (!Database::value("SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.code = 'commissioner'", [$userId])) {
                    throw new DomainError('Choisissez un compte commissaire.');
                }
                $hasActive = Database::value('SELECT 1 FROM commissioner_tatamis ct JOIN tatamis t ON t.id = ct.tatami_id WHERE ct.user_id = ? AND ct.is_active = 1 AND t.competition_id = ?', [$userId, $cid]);
                Database::run('INSERT IGNORE INTO commissioner_tatamis (user_id, tatami_id, is_active, assigned_by) VALUES (?, ?, ?, ?)', [$userId, $tatamiId, $hasActive ? 0 : 1, \App\Core\Auth::id()]);
                AuditLogger::log('tatami.assign', 'tatami', $tatamiId, null, ['user_id' => $userId], null, $cid);
                return $this->back($r, 'Commissaire affecté.');
            case 'unassign':
                Database::run('DELETE FROM commissioner_tatamis WHERE user_id = ? AND tatami_id = ?', [(int) $r->int('user_id'), $tatamiId]);
                AuditLogger::log('tatami.unassign', 'tatami', $tatamiId, ['user_id' => (int) $r->int('user_id')], null, null, $cid);
                return $this->back($r, 'Affectation retirée.');
            case 'rename':
                $name = mb_substr($r->str('name'), 0, 60);
                Database::run('UPDATE tatamis SET name = ? WHERE id = ?', [$name ?: null, $tatamiId]);
                return $this->back($r, 'Tapis renommé.');
            case 'toggle':
                Database::run('UPDATE tatamis SET is_active = 1 - is_active WHERE id = ?', [$tatamiId]);
                AuditLogger::log('tatami.toggle', 'tatami', $tatamiId, null, null, null, $cid);
                return $this->back($r, 'Disponibilité du tapis modifiée.');
            case 'tv_create':
                $link = TvTokenService::create($tatamiId, $r->str('label') ?: null, $r->int('days'));
                Session::put('tv_new_link', ['tatami_id' => $tatamiId, 'url' => $link['url']]);
                return $this->back($r, 'Lien d\'affichage créé : copiez-le maintenant, il ne sera plus affiché en entier.');
            case 'tv_reset':
                $link = TvTokenService::reset($tatamiId);
                Session::put('tv_new_link', ['tatami_id' => $tatamiId, 'url' => $link['url']]);
                return $this->back($r, 'Affichage réinitialisé : les anciens liens sont révoqués.');
            case 'tv_disable':
                TvTokenService::setActive((int) $r->int('token_id'), false);
                return $this->back($r, 'Affichage désactivé.');
            case 'tv_enable':
                TvTokenService::setActive((int) $r->int('token_id'), true);
                return $this->back($r, 'Affichage réactivé.');
            case 'tv_revoke':
                TvTokenService::revoke((int) $r->int('token_id'));
                return $this->back($r, 'Lien révoqué définitivement.');
        }
        throw new DomainError('Action inconnue.');
    }
}
