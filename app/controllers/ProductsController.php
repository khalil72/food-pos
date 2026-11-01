<?php
declare(strict_types=1);

final class ProductsController extends Controller
{
    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        if ($cat = input_int('category')) {
            $where[] = 'p.category_id = ?';
            $params[] = $cat;
        }
        if (($q = (string)input('q', '')) !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $status = (string)input('status', '');
        if ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        } elseif ($status === 'low') {
            $where[] = 'p.track_stock = 1 AND p.stock_qty <= p.low_stock_level';
        }
        $w = implode(' AND ', $where);
        $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM products p WHERE $w", $params), 24);
        $products = $this->db->fetchAll(
            "SELECT p.*, c.name category_name, c.color category_color, c.icon category_icon,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) variant_count,
                    (SELECT COALESCE(SUM(pi.qty * i.cost_per_unit), NULL) FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id WHERE pi.product_id = p.id) recipe_cost
               FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE $w ORDER BY p.is_active DESC, c.sort_order, p.name LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('products/index', [
            'title' => 'Products',
            'subtitle' => 'Your menu items, prices and stock',
            'products' => $products,
            'p' => $p,
            'categories' => $this->db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name'),
        ]);
    }

    public function form(): void
    {
        $id = input_int('id');
        $product = $id ? $this->findOr404('products', $id) : null;
        $this->view('products/form', [
            'title' => $product ? 'Edit product' : 'New product',
            'subtitle' => $product ? $product['name'] : 'Add an item to your menu',
            'product' => $product,
            'categories' => $this->db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name'),
            'addons' => $this->db->fetchAll('SELECT * FROM addons ORDER BY name'),
            'ingredients' => $this->db->fetchAll('SELECT id, name, unit, cost_per_unit FROM ingredients ORDER BY name'),
            'variants' => $id ? $this->db->fetchAll('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id', [$id]) : [],
            'selectedAddons' => $id ? array_map('intval', array_column($this->db->fetchAll('SELECT addon_id FROM product_addons WHERE product_id = ?', [$id]), 'addon_id')) : [],
            'recipe' => $id ? $this->db->fetchAll('SELECT * FROM product_ingredients WHERE product_id = ?', [$id]) : [],
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $existing = $id ? $this->findOr404('products', $id) : null;
        $data = $this->validate([
            'name'            => ['Name', 'required|maxlen:120'],
            'category_id'     => ['Category', 'int'],
            'sku'             => ['SKU', 'maxlen:50'],
            'barcode'         => ['Barcode', 'maxlen:64'],
            'price'           => ['Price', 'required|numeric|min:0|max:99999999'],
            'cost'            => ['Cost', 'numeric|min:0|max:99999999'],
            'stock_qty'       => ['Stock quantity', 'numeric|min:-999999|max:999999'],
            'low_stock_level' => ['Low stock level', 'numeric|min:0|max:999999'],
            'description'     => ['Description', 'maxlen:500'],
        ]);
        $sku = trim((string)($data['sku'] ?? '')) ?: null;
        $barcode = trim((string)($data['barcode'] ?? '')) ?: null;
        if ($sku && $this->db->value('SELECT id FROM products WHERE sku = ? AND id <> ?', [$sku, $id])) {
            $this->fail('Another product already uses this SKU.');
        }
        if ($barcode && $this->db->value('SELECT id FROM products WHERE barcode = ? AND id <> ?', [$barcode, $id])) {
            $this->fail('Another product already uses this barcode.');
        }

        // Variants
        $variants = [];
        foreach ((array)($_POST['variants'] ?? []) as $v) {
            $vname = trim((string)($v['name'] ?? ''));
            if ($vname === '') {
                continue;
            }
            if (!is_numeric($v['price'] ?? null) || (float)$v['price'] < 0) {
                $this->fail("Enter a valid price for option “{$vname}”.");
            }
            $variants[] = ['id' => (int)($v['id'] ?? 0), 'name' => mb_substr($vname, 0, 60), 'price' => (float)$v['price'], 'cost' => max(0, (float)($v['cost'] ?? 0))];
        }
        // Recipe
        $recipe = [];
        foreach ((array)($_POST['recipe'] ?? []) as $r) {
            $iid = (int)($r['ingredient_id'] ?? 0);
            $q = (float)($r['qty'] ?? 0);
            if ($iid && $q > 0) {
                $recipe[$iid] = $q;
            }
        }
        $addonIds = array_unique(array_filter(array_map('intval', (array)($_POST['addons'] ?? []))));

        try {
            $image = store_image_upload($_FILES['image'] ?? null, 'products');
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $row = [
            'name'            => trim($data['name']),
            'category_id'     => ($data['category_id'] ?? '') !== '' ? (int)$data['category_id'] : null,
            'sku'             => $sku,
            'barcode'         => $barcode,
            'price'           => round((float)$data['price'], 2),
            'cost'            => round((float)($data['cost'] ?? 0), 2),
            'description'     => trim((string)($data['description'] ?? '')) ?: null,
            'track_stock'     => input('track_stock') === '1' ? 1 : 0,
            'low_stock_level' => (float)($data['low_stock_level'] ?? 0),
            'is_active'       => input('is_active') === '1' ? 1 : 0,
        ];
        if ($image) {
            $row['image'] = $image;
        } elseif (input('remove_image') === '1') {
            $row['image'] = null;
        }

        $newStock = (float)($data['stock_qty'] ?? 0);
        $id = $this->db->transaction(function (Database $db) use ($id, $existing, $row, $variants, $recipe, $addonIds, $newStock) {
            if ($existing) {
                $db->update('products', $row, 'id = ?', [$id]);
                $delta = $newStock - (float)$existing['stock_qty'];
                if ($row['track_stock'] && abs($delta) > 0.0001) {
                    Stock::move('product', $id, $delta, 'adjustment', null, 'Edited on product form');
                }
            } else {
                $id = $db->insert('products', $row + ['stock_qty' => 0]);
                if ($row['track_stock'] && $newStock != 0) {
                    Stock::move('product', $id, $newStock, 'adjustment', null, 'Opening stock');
                }
            }
            // Variants: update existing, insert new, delete removed
            $keep = [];
            foreach ($variants as $i => $v) {
                $vid = $v['id'];
                $vrow = ['name' => $v['name'], 'price' => $v['price'], 'cost' => $v['cost'], 'sort_order' => $i];
                if ($vid && $db->value('SELECT id FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $id])) {
                    $db->update('product_variants', $vrow, 'id = ?', [$vid]);
                } else {
                    $vid = $db->insert('product_variants', $vrow + ['product_id' => $id]);
                }
                $keep[] = $vid;
            }
            $keep ? $db->query('DELETE FROM product_variants WHERE product_id = ? AND id NOT IN (' . implode(',', $keep) . ')', [$id])
                  : $db->delete('product_variants', 'product_id = ?', [$id]);

            $db->delete('product_addons', 'product_id = ?', [$id]);
            foreach ($addonIds as $aid) {
                if ($db->value('SELECT id FROM addons WHERE id = ?', [$aid])) {
                    $db->insert('product_addons', ['product_id' => $id, 'addon_id' => $aid]);
                }
            }
            $db->delete('product_ingredients', 'product_id = ?', [$id]);
            foreach ($recipe as $iid => $q) {
                if ($db->value('SELECT id FROM ingredients WHERE id = ?', [$iid])) {
                    $db->insert('product_ingredients', ['product_id' => $id, 'ingredient_id' => $iid, 'qty' => $q]);
                }
            }
            return $id;
        });

        if ($existing && array_key_exists('image', $row) && $existing['image'] && $existing['image'] !== ($row['image'] ?? null)) {
            delete_upload($existing['image']);
        }
        audit($existing ? 'product_updated' : 'product_created', 'product', $id, $row['name'] . ' · ' . money($row['price']));
        flash('success', 'Product “' . $row['name'] . '” saved.');
        redirect(input('save_new') === '1' ? 'products/create' : 'products');
    }

    public function toggle(): void
    {
        $this->requirePost();
        $p = $this->findOr404('products', input_int('id'));
        $this->db->update('products', ['is_active' => (int)!$p['is_active']], 'id = ?', [$p['id']]);
        audit('product_toggled', 'product', (int)$p['id'], $p['name'] . ($p['is_active'] ? ' disabled' : ' enabled'));
        if (is_ajax()) {
            json_out(['ok' => true, 'active' => !$p['is_active']]);
        }
        flash('success', $p['name'] . ($p['is_active'] ? ' hidden from POS.' : ' is now available.'));
        redirect_back('products');
    }

    public function delete(): void
    {
        $this->requirePost();
        $p = $this->findOr404('products', input_int('id'));
        if ($this->db->value('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1', [$p['id']])) {
            $this->db->update('products', ['is_active' => 0], 'id = ?', [$p['id']]);
            audit('product_archived', 'product', (int)$p['id'], $p['name']);
            flash('warning', '“' . $p['name'] . '” has sales history, so it was archived (hidden) instead of deleted.');
        } else {
            $this->db->delete('products', 'id = ?', [$p['id']]);
            delete_upload($p['image']);
            audit('product_deleted', 'product', (int)$p['id'], $p['name']);
            flash('success', '“' . $p['name'] . '” deleted.');
        }
        redirect('products');
    }

    public function export(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT p.name, COALESCE(c.name,'') category, p.sku, p.barcode, p.price, p.cost, IF(p.track_stock, p.stock_qty, '') stock,
                    p.low_stock_level, IF(p.is_active,'Active','Inactive') status
               FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY c.sort_order, p.name"
        );
        csv_download('products-' . date('Y-m-d') . '.csv', ['Name', 'Category', 'SKU', 'Barcode', 'Price', 'Cost', 'Stock', 'Low stock level', 'Status'], $rows);
    }
}
