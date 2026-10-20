<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompetitionService;
use App\Services\NotificationService;
use App\Services\TvTokenService;

final class DashboardController extends AdminController
{
    public function index(Request $r): Response
    {
        $current = Database::one("SELECT * FROM competitions WHERE status IN ('in_progress','pool_preparation','attendance') ORDER BY event_date LIMIT 1")
            ?? Database::one("SELECT * FROM competitions WHERE status NOT IN ('archived') AND event_date >= CURDATE() ORDER BY event_date LIMIT 1")
            ?? Database::one('SELECT * FROM competitions ORDER BY event_date DESC LIMIT 1');
        $data = ['title' => 'Tableau de bord', 'current' => $current];
        if ($current) {
            $cid = (int) $current['id'];
            $data['stats'] = CompetitionService::stats($cid);
            $data['progress'] = CompetitionService::tatamiProgress($cid);
            $data['tv'] = TvTokenService::forCompetition($cid);
            $data['recent'] = Database::all('SELECT * FROM audit_logs WHERE competition_id = ? ORDER BY id DESC LIMIT 12', [$cid]);
        }
        $data['competitions'] = Database::all("SELECT * FROM competitions WHERE status <> 'archived' ORDER BY event_date DESC LIMIT 8");
        $data['notifications'] = NotificationService::forUser(Auth::user(), 8);
        $data['counts'] = [
            'clubs' => (int) Database::value('SELECT COUNT(*) FROM clubs WHERE is_active = 1'),
            'users' => (int) Database::value('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'competitions' => (int) Database::value('SELECT COUNT(*) FROM competitions'),
        ];
        return $this->admin('dashboard', $data);
    }

    public function readNotifications(Request $r): Response
    {
        NotificationService::markAllRead(Auth::user());
        return $this->back($r, 'Notifications marquées comme lues.', 'success', '/admin');
    }
}
