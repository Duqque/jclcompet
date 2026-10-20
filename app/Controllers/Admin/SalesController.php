<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\CompetitionService;
use App\Services\DomainError;
use App\Services\SalesService;

final class SalesController extends AdminController
{
    public function index(Request $r): Response
    {
        $c = $this->competition($r);
        $cid = (int) $c['id'];
        $day = isset($r->query['jour']) && \App\Core\Validator::isDate((string) $r->query['jour']) ? (string) $r->query['jour'] : null;
        return $this->admin('sales', [
            'title' => 'Buvette et boutique', 'competition' => $c, 'c' => $c, 'day' => $day,
            'products' => SalesService::products($cid, false), 'summary' => SalesService::summary($cid, $day), 'recent' => SalesService::recent($cid, 40),
        ]);
    }

    public function action(Request $r): Response
    {
        $c = $this->competition($r);
        CompetitionService::assertWritable($c);
        $cid = (int) $c['id'];
        switch ($r->str('op')) {
            case 'product_save':
                $d = Validator::validate($r->post, [
                    'name' => 'required|maxlen:120', 'description' => 'nullable|maxlen:255', 'category' => 'required|maxlen:30',
                    'price' => 'required|numeric|min:0|max:10000', 'sort_order' => 'nullable|int', 'is_active' => 'bool',
                ], ['name' => 'Produit', 'price' => 'Prix', 'category' => 'Rubrique']);
                $row = ['name' => $d['name'], 'description' => $d['description'], 'category' => preg_replace('/[^a-z0-9_]/', '', strtolower($d['category'])) ?: 'restauration',
                    'price_cents' => (int) round($d['price'] * 100), 'sort_order' => (int) ($d['sort_order'] ?? 0), 'is_active' => $d['is_active']];
                $id = $r->int('product_id');
                if ($id) {
                    $old = Database::one('SELECT * FROM products WHERE id = ? AND (competition_id = ? OR competition_id IS NULL)', [$id, $cid]);
                    if (!$old) {
                        throw new DomainError('Produit introuvable.');
                    }
                    Database::update('products', $row, 'id = :id', ['id' => $id]);
                    AuditLogger::log('sales.product.update', 'product', $id, ['price_cents' => $old['price_cents'], 'name' => $old['name']], $row, null, $cid);
                } else {
                    $id = Database::insert('products', $row + ['competition_id' => $cid]);
                    AuditLogger::log('sales.product.create', 'product', $id, null, $row, null, $cid);
                }
                return $this->back($r, 'Produit enregistré. Le prix des ventes déjà enregistrées ne change pas.');
            case 'refund':
                SalesService::refund((int) $r->int('sale_id'), $r->str('reason'));
                return $this->back($r, 'Remboursement enregistré.');
        }
        throw new DomainError('Action inconnue.');
    }

    public function export(Request $r): Response
    {
        $c = $this->competition($r);
        return Response::download(SalesService::exportCsv((int) $c['id']), 'ventes-' . $c['slug'] . '.csv', 'text/csv; charset=utf-8');
    }
}
