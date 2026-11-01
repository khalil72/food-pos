<?php
declare(strict_types=1);

final class Catalog
{
    /**
     * Products keyed by id, each with variants, allowed add-ons and recipe cost.
     * Pass $ids to restrict the lookup (used at checkout).
     */
    public static function products(?array $ids = null, bool $activeOnly = true): array
    {
        $db = Database::instance();
        $where = [];
        $params = [];
        if ($activeOnly) {
            $where[] = 'p.is_active = 1';
        }
        if ($ids !== null) {
            if (!$ids) {
                return [];
            }
            $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_values($ids);
        }
        $rows = $db->fetchAll(
            'SELECT p.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
               FROM products p LEFT JOIN categories c ON c.id = p.category_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.sort_order, p.name',
            $params
        );
        $products = [];
        foreach ($rows as $r) {
            $r['variants'] = [];
            $r['addons'] = [];
            $r['recipe_cost'] = null;
            $products[(int)$r['id']] = $r;
        }
        if (!$products) {
            return [];
        }
        $in = implode(',', array_keys($products));

        foreach ($db->fetchAll("SELECT * FROM product_variants WHERE product_id IN ($in) ORDER BY sort_order, id") as $v) {
            $products[(int)$v['product_id']]['variants'][] = $v;
        }
        foreach ($db->fetchAll("SELECT pa.product_id, a.* FROM product_addons pa JOIN addons a ON a.id = pa.addon_id WHERE pa.product_id IN ($in) AND a.is_active = 1 ORDER BY a.name") as $a) {
            $products[(int)$a['product_id']]['addons'][] = $a;
        }
        foreach ($db->fetchAll("SELECT pi.product_id, SUM(pi.qty * i.cost_per_unit) cost FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id WHERE pi.product_id IN ($in) GROUP BY pi.product_id") as $rc) {
            $products[(int)$rc['product_id']]['recipe_cost'] = (float)$rc['cost'];
        }
        return $products;
    }

    /** Compact structure for the POS front-end. */
    public static function forPos(): array
    {
        $out = [];
        foreach (self::products() as $p) {
            $out[] = [
                'id'          => (int)$p['id'],
                'name'        => $p['name'],
                'category_id' => (int)$p['category_id'],
                'price'       => (float)$p['price'],
                'sku'         => (string)$p['sku'],
                'barcode'     => (string)$p['barcode'],
                'image'       => upload_url($p['image']),
                'color'       => $p['category_color'] ?: '#E8572A',
                'icon'        => $p['category_icon'] ?: 'bi-egg-fried',
                'description' => (string)$p['description'],
                'track_stock' => (bool)$p['track_stock'],
                'stock'       => (float)$p['stock_qty'],
                'variants'    => array_map(fn($v) => ['id' => (int)$v['id'], 'name' => $v['name'], 'price' => (float)$v['price']], $p['variants']),
                'addons'      => array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name'], 'price' => (float)$a['price']], $p['addons']),
            ];
        }
        return $out;
    }
}
