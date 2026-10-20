<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;

final class AuditController extends AdminController
{
    public function index(Request $r): Response
    {
        $cid = $r->param('id') ? (int) $r->param('id') : null;
        $c = $cid ? $this->competition($r) : null;
        $q = trim((string) ($r->query['q'] ?? ''));
        [$page, $per, $offset] = $this->page($r, 100);
        $where = '(? IS NULL OR competition_id = ?) AND (? = "" OR action LIKE ? OR user_label LIKE ? OR reason LIKE ? OR entity_type = ?)';
        $params = [$cid, $cid, $q, "%$q%", "%$q%", "%$q%", $q];
        $total = (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE $where", $params);
        return $this->admin('audit', [
            'title' => 'Journal d\'activité', 'competition' => $c, 'c' => $c, 'q' => $q,
            'rows' => Database::all("SELECT * FROM audit_logs WHERE $where ORDER BY id DESC LIMIT $per OFFSET $offset", $params),
            'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $per),
            'chain' => isset($r->query['verifier']) ? AuditLogger::verifyChain() : null,
        ]);
    }
}
