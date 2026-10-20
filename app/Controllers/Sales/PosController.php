<?php
declare(strict_types=1);

namespace App\Controllers\Sales;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompetitionService;
use App\Services\SalesService;

/** Caisse buvette / boutique (profil vendeur ou administrateur). */
final class PosController extends Controller
{
    public function index(Request $r): Response
    {
        $c = Database::one("SELECT * FROM competitions WHERE status NOT IN ('draft','archived') ORDER BY ABS(DATEDIFF(event_date, CURDATE())) LIMIT 1");
        if (!$c) {
            return $this->view('pos/none', ['title' => 'Caisse'], 'attendance');
        }
        return Response::redirect('/caisse/' . $c['id']);
    }

    public function show(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        $products = SalesService::products((int) $c['id']);
        return $this->view('pos/show', [
            'title' => 'Caisse', 'c' => $c, 'scripts' => ['pos.js'],
            'config' => [
                'storeUrl' => url('/api/caisse/' . $c['id'] . '/ventes'),
                'products' => array_map(fn ($p) => ['id' => (int) $p['id'], 'name' => $p['name'], 'desc' => $p['description'], 'category' => $p['category'], 'price' => (int) $p['price_cents']], $products),
                'categories' => SalesService::CATEGORIES, 'payments' => SalesService::PAYMENTS,
            ],
            'today' => SalesService::summary((int) $c['id'], date('Y-m-d'))['totals'],
        ], 'attendance');
    }

    public function store(Request $r): Response
    {
        $c = CompetitionService::find((int) $r->param('id'));
        $in = $r->json();
        $id = SalesService::record((int) $c['id'], (array) ($in['items'] ?? []), (string) ($in['payment'] ?? 'cash'), isset($in['client_ref']) ? substr((string) $in['client_ref'], 0, 64) : null, $in['note'] ?? null);
        return $this->json(['ok' => true, 'sale_id' => $id, 'today' => SalesService::summary((int) $c['id'], date('Y-m-d'))['totals']]);
    }
}
