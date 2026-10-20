<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Registre des ventes buvette / boutique (sans encaissement bancaire).
 * Chiffre d'affaires brut, remboursements et recettes nettes sont distingués ; aucune notion de bénéfice
 * (les coûts d'achat ne sont pas connus de l'application).
 */
final class SalesService
{
    public const CATEGORIES = ['restauration' => 'Restauration', 'boissons' => 'Boissons', 'boutique' => 'Boutique', 'loterie' => 'Loterie', 'buvette' => 'Buvette'];
    public const PAYMENTS = ['cash' => 'Espèces', 'card' => 'Carte bancaire', 'check' => 'Chèque', 'other' => 'Autre'];

    public static function products(int $competitionId, bool $activeOnly = true): array
    {
        return Database::all(
            'SELECT * FROM products WHERE (competition_id = ? OR competition_id IS NULL)' . ($activeOnly ? ' AND is_active = 1' : '') .
            " ORDER BY FIELD(category, 'restauration', 'boissons', 'loterie', 'boutique'), sort_order, name",
            [$competitionId]
        );
    }

    /** @param list<array{product_id:int, quantity:int}> $items */
    public static function record(int $competitionId, array $items, string $payment, ?string $clientRef = null, ?string $note = null): int
    {
        CompetitionService::assertWritable($competitionId);
        if (!isset(self::PAYMENTS[$payment])) {
            throw new DomainError('Moyen de paiement invalide.');
        }
        if ($clientRef !== null && ($existing = Database::value('SELECT id FROM sales WHERE client_ref = ?', [$clientRef]))) {
            return (int) $existing; // renvoi d'une vente déjà enregistrée
        }
        $lines = [];
        $total = 0;
        foreach ($items as $it) {
            $qty = (int) ($it['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if ($qty > 500) {
                throw new DomainError('Quantité trop élevée.');
            }
            $p = Database::one('SELECT * FROM products WHERE id = ? AND is_active = 1 AND (competition_id = ? OR competition_id IS NULL)', [(int) $it['product_id'], $competitionId]);
            if (!$p) {
                throw new DomainError('Produit introuvable ou inactif.');
            }
            $lines[] = ['p' => $p, 'qty' => $qty];
            $total += $qty * (int) $p['price_cents'];
        }
        if (!$lines) {
            throw new DomainError('Ajoutez au moins un produit.');
        }
        return Database::transaction(function () use ($competitionId, $lines, $total, $payment, $clientRef, $note) {
            $saleId = Database::insert('sales', [
                'competition_id' => $competitionId, 'kind' => 'sale', 'payment_method' => $payment, 'total_cents' => $total,
                'note' => $note ? mb_substr($note, 0, 255) : null, 'seller_id' => Auth::id(), 'client_ref' => $clientRef,
            ]);
            foreach ($lines as $l) {
                Database::insert('sale_items', [
                    'sale_id' => $saleId, 'product_id' => $l['p']['id'], 'product_name' => $l['p']['name'], 'category' => $l['p']['category'],
                    'unit_price_cents' => $l['p']['price_cents'], 'quantity' => $l['qty'], 'line_total_cents' => $l['qty'] * (int) $l['p']['price_cents'],
                ]);
            }
            AuditLogger::log('sales.record', 'sale', $saleId, null, ['total_cents' => $total, 'payment' => $payment], null, $competitionId);
            return $saleId;
        });
    }

    /** Remboursement total d'une vente (vente négative liée). */
    public static function refund(int $saleId, string $reason): int
    {
        $sale = Database::one('SELECT * FROM sales WHERE id = ?', [$saleId]);
        if (!$sale || $sale['kind'] !== 'sale') {
            throw new HttpException(404, 'Vente introuvable.');
        }
        CompetitionService::assertWritable((int) $sale['competition_id']);
        if (Database::value("SELECT 1 FROM sales WHERE refund_of_sale_id = ? AND kind = 'refund'", [$saleId])) {
            throw new DomainError('Cette vente a déjà été remboursée.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new DomainError('Indiquez le motif du remboursement.');
        }
        return Database::transaction(function () use ($sale, $saleId, $reason) {
            $id = Database::insert('sales', [
                'competition_id' => $sale['competition_id'], 'kind' => 'refund', 'refund_of_sale_id' => $saleId,
                'payment_method' => $sale['payment_method'], 'total_cents' => -(int) $sale['total_cents'],
                'note' => mb_substr($reason, 0, 255), 'seller_id' => Auth::id(),
            ]);
            foreach (Database::all('SELECT * FROM sale_items WHERE sale_id = ?', [$saleId]) as $it) {
                Database::insert('sale_items', [
                    'sale_id' => $id, 'product_id' => $it['product_id'], 'product_name' => $it['product_name'], 'category' => $it['category'],
                    'unit_price_cents' => $it['unit_price_cents'], 'quantity' => -(int) $it['quantity'], 'line_total_cents' => -(int) $it['line_total_cents'],
                ]);
            }
            AuditLogger::log('sales.refund', 'sale', $id, null, ['refund_of' => $saleId, 'total_cents' => -(int) $sale['total_cents']], $reason, (int) $sale['competition_id']);
            return $id;
        });
    }

    public static function summary(int $competitionId, ?string $day = null): array
    {
        $where = 'competition_id = ?';
        $p = [$competitionId];
        if ($day) {
            $where .= ' AND DATE(sold_at) = ?';
            $p[] = $day;
        }
        $t = Database::one(
            "SELECT COALESCE(SUM(CASE WHEN kind = 'sale' THEN total_cents END), 0) AS gross,
                    COALESCE(-SUM(CASE WHEN kind = 'refund' THEN total_cents END), 0) AS refunds,
                    COALESCE(SUM(total_cents), 0) AS net,
                    SUM(kind = 'sale') AS sales_count
             FROM sales WHERE $where",
            $p
        );
        $byProduct = Database::all(
            "SELECT si.product_name, si.category, SUM(si.quantity) AS qty, SUM(si.line_total_cents) AS net,
                    SUM(CASE WHEN si.quantity > 0 THEN si.line_total_cents ELSE 0 END) AS gross
             FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.$where
             GROUP BY si.product_name, si.category ORDER BY net DESC",
            $p
        );
        $byDay = Database::all(
            "SELECT DATE(sold_at) AS day, COALESCE(SUM(CASE WHEN kind = 'sale' THEN total_cents END), 0) AS gross,
                    COALESCE(-SUM(CASE WHEN kind = 'refund' THEN total_cents END), 0) AS refunds, SUM(total_cents) AS net
             FROM sales WHERE competition_id = ? GROUP BY DATE(sold_at) ORDER BY day",
            [$competitionId]
        );
        $byPayment = Database::all("SELECT payment_method, SUM(total_cents) AS net FROM sales WHERE $where GROUP BY payment_method", $p);
        return ['totals' => $t, 'by_product' => $byProduct, 'by_day' => $byDay, 'by_payment' => $byPayment];
    }

    public static function recent(int $competitionId, int $limit = 50): array
    {
        return Database::all(
            'SELECT s.*, u.display_name AS seller,
                    (SELECT GROUP_CONCAT(CONCAT(si.quantity, " × ", si.product_name) SEPARATOR ", ") FROM sale_items si WHERE si.sale_id = s.id) AS items,
                    (SELECT 1 FROM sales r WHERE r.refund_of_sale_id = s.id LIMIT 1) AS refunded
             FROM sales s LEFT JOIN users u ON u.id = s.seller_id WHERE s.competition_id = ? ORDER BY s.id DESC LIMIT ' . (int) $limit,
            [$competitionId]
        );
    }

    public static function exportCsv(int $competitionId): string
    {
        $rows = Database::all(
            'SELECT s.id, s.sold_at, s.kind, s.payment_method, si.product_name, si.category, si.unit_price_cents, si.quantity, si.line_total_cents, u.display_name
             FROM sales s JOIN sale_items si ON si.sale_id = s.id LEFT JOIN users u ON u.id = s.seller_id WHERE s.competition_id = ? ORDER BY s.id, si.id',
            [$competitionId]
        );
        return CsvService::build(
            ['vente', 'date', 'type', 'paiement', 'produit', 'rubrique', 'prix_unitaire', 'quantite', 'montant', 'vendeur'],
            array_map(fn ($r) => [
                $r['id'], $r['sold_at'], $r['kind'] === 'sale' ? 'vente' : 'remboursement', self::PAYMENTS[$r['payment_method']] ?? $r['payment_method'],
                $r['product_name'], $r['category'], number_format($r['unit_price_cents'] / 100, 2, ',', ''), $r['quantity'],
                number_format($r['line_total_cents'] / 100, 2, ',', ''), $r['display_name'],
            ], $rows)
        );
    }
}
