<?php
declare(strict_types=1);

final class Stock
{
    /** Changes stock for a product or ingredient and records the movement. Returns the new balance. */
    public static function move(string $type, int $id, float $delta, string $reason, ?string $reference = null, ?string $note = null): float
    {
        $db = Database::instance();
        $table = $type === 'product' ? 'products' : 'ingredients';
        $db->query("UPDATE `$table` SET stock_qty = stock_qty + ? WHERE id = ?", [$delta, $id]);
        $balance = (float)$db->value("SELECT stock_qty FROM `$table` WHERE id = ?", [$id]);
        $db->insert('stock_movements', [
            'item_type'     => $type,
            'item_id'       => $id,
            'change_qty'    => $delta,
            'balance_after' => $balance,
            'reason'        => $reason,
            'reference'     => $reference,
            'note'          => $note ? mb_substr($note, 0, 255) : null,
            'user_id'       => Auth::id(),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return $balance;
    }

    /** Products and ingredients at or below their reorder level. */
    public static function lowStock(int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            "SELECT * FROM (
                SELECT 'product' AS type, id, name, stock_qty, low_stock_level, 'pcs' AS unit
                  FROM products WHERE track_stock = 1 AND is_active = 1 AND stock_qty <= low_stock_level
                UNION ALL
                SELECT 'ingredient', id, name, stock_qty, low_stock_level, unit
                  FROM ingredients WHERE stock_qty <= low_stock_level
             ) s ORDER BY (stock_qty - low_stock_level) ASC LIMIT " . (int)$limit
        );
    }
}
