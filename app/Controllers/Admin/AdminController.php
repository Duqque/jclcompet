<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompetitionService;

abstract class AdminController extends Controller
{
    protected function competition(Request $r): array
    {
        $c = CompetitionService::find((int) $r->param('id'));
        \App\Core\Session::put('admin_competition_id', (int) $c['id']);
        return $c;
    }

    /** Compétition affichée dans le menu : la dernière ouverte, sinon la plus pertinente (en cours, à venir, récente). */
    public static function current(): ?array
    {
        $id = (int) \App\Core\Session::get('admin_competition_id', 0);
        if ($id && ($c = \App\Core\Database::one('SELECT * FROM competitions WHERE id = ?', [$id]))) {
            return $c;
        }
        return \App\Core\Database::one("SELECT * FROM competitions WHERE status IN ('in_progress','pool_preparation','attendance') ORDER BY event_date LIMIT 1")
            ?? \App\Core\Database::one("SELECT * FROM competitions WHERE status <> 'archived' AND event_date >= CURDATE() ORDER BY event_date LIMIT 1")
            ?? \App\Core\Database::one('SELECT * FROM competitions ORDER BY event_date DESC LIMIT 1');
    }

    protected function admin(string $template, array $data = []): Response
    {
        return $this->view('admin/' . $template, $data, 'admin');
    }
}
