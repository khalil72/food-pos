<?php
declare(strict_types=1);

final class InventoryController extends Controller
{
    public function index(): void
    {
        $tab = in_array($t = (string)input('tab', 'ingredients'), ['ingredients', 'products', 'low', 'movements'], true) ? $t : 'ingredients';
        $data = [
            'title' => 'Stock & Ingredients',
            'subtitle' => 'Track raw materials, ready-made items and stock movements',
            'tab' => $tab,
            'suppliers' => $this->db->fetchAll('SELECT id, name FROM suppliers ORDER BY name'),
            'allIngredients' => $this->db->fetchAll('SELECT id, name, unit FROM ingredients ORDER BY name'),
            'allProducts' => $this->db->fetchAll('SELECT id, name FROM products WHERE track_stock = 1 ORDER BY name'),
            'valuation' => (float)$this->db->value('SELECT COALESCE(SUM(GREATEST(stock_qty,0) * cost_per_unit),0) FROM ingredients')
                + (float)$this->db->value('SELECT COALESCE(SUM(GREATEST(stock_qty,0) * cost),0) FROM products WHERE track_stock = 1'),
            'lowCount' => count(Stock::lowStock(1000)),
        ];

        if ($tab === 'ingredients') {
            $data['ingredients'] = $this->db->fetchAll(
                'SELECT i.*, s.name supplier_name, (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.ingredient_id = i.id) used_in
                   FROM ingredients i LEFT JOIN suppliers s ON s.id = i.supplier_id ORDER BY i.name'
            );
        } elseif ($tab === 'products') {
            $data['products'] = $this->db->fetchAll(
                'SELECT p.id, p.name, p.sku, p.stock_qty, p.low_stock_level, p.cost, c.name category_name
                   FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.track_stock = 1 ORDER BY p.name'
            );
        } elseif ($tab === 'low') {
            $data['low'] = Stock::lowStock(500);
        } else {
            $where = ['1=1'];
            $params = [];
            if (in_array($type = (string)input('type', ''), ['product', 'ingredient'], true)) {
                $where[] = 'm.item_type = ?';
                $params[] = $type;
            }
            if (in_array($reason = (string)input('reason', ''), ['sale', 'purchase', 'return', 'adjustment', 'wastage', 'purchase_delete'], true)) {
                $where[] = 'm.reason = ?';
                $params[] = $reason;
            }
            $w = implode(' AND ', $where);
            $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM stock_movements m WHERE $w", $params), 30);
            $data['p'] = $p;
            $data['movements'] = $this->db->fetchAll(
                "SELECT m.*, u.name user_name,
                        CASE m.item_type WHEN 'product' THEN (SELECT name FROM products WHERE id = m.item_id) ELSE (SELECT name FROM ingredients WHERE id = m.item_id) END item_name,
                        CASE m.item_type WHEN 'ingredient' THEN (SELECT unit FROM ingredients WHERE id = m.item_id) ELSE 'pcs' END unit
                   FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id
                  WHERE $w ORDER BY m.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
                $params
            );
        }
        $this->view('inventory/index', $data);
    }

    public function movements(): void
    {
        redirect('inventory', ['tab' => 'movements'] + array_intersect_key($_GET, array_flip(['type', 'reason'])));
    }

    public function stock(): void
    {
        redirect('inventory', ['tab' => 'products']);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'            => ['Name', 'required|maxlen:100'],
            'unit'            => ['Unit', 'required|maxlen:20'],
            'low_stock_level' => ['Reorder level', 'numeric|min:0|max:9999999'],
            'cost_per_unit'   => ['Cost per unit', 'numeric|min:0|max:9999999'],
            'supplier_id'     => ['Supplier', 'int'],
            'stock_qty'       => ['Opening stock', 'numeric|min:0|max:9999999'],
        ]);
        $row = [
            'name'            => trim($data['name']),
            'unit'            => trim($data['unit']),
            'low_stock_level' => (float)($data['low_stock_level'] ?? 0),
            'cost_per_unit'   => (float)($data['cost_per_unit'] ?? 0),
            'supplier_id'     => ($data['supplier_id'] ?? '') !== '' ? (int)$data['supplier_id'] : null,
        ];
        if ($id = input_int('id')) {
            $this->findOr404('ingredients', $id);
            $this->db->update('ingredients', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('ingredients', $row);
            if (($opening = (float)($data['stock_qty'] ?? 0)) > 0) {
                Stock::move('ingredient', $id, $opening, 'adjustment', null, 'Opening stock');
            }
        }
        audit('ingredient_saved', 'ingredient', $id, $row['name']);
        flash('success', 'Ingredient “' . $row['name'] . '” saved.');
        redirect('inventory');
    }

    public function delete(): void
    {
        $this->requirePost();
        $i = $this->findOr404('ingredients', input_int('id'));
        $this->db->delete('ingredients', 'id = ?', [$i['id']]);
        audit('ingredient_deleted', 'ingredient', (int)$i['id'], $i['name']);
        flash('success', 'Ingredient deleted and removed from recipes.');
        redirect('inventory');
    }

    public function adjust(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'item'      => ['Item', 'required'],
            'direction' => ['Direction', 'required|in:add,remove,set'],
            'qty'       => ['Quantity', 'required|numeric|min:0|max:9999999'],
            'reason'    => ['Reason', 'required|in:adjustment,wastage'],
            'note'      => ['Note', 'maxlen:255'],
        ]);
        [$type, $itemId] = array_pad(explode(':', (string)$data['item'], 2), 2, '0');
        if (!in_array($type, ['product', 'ingredient'], true)) {
            $this->fail('Choose a valid item.');
        }
        $table = $type === 'product' ? 'products' : 'ingredients';
        $item = $this->db->fetch("SELECT id, name, stock_qty FROM `$table` WHERE id = ?", [(int)$itemId]);
        if (!$item) {
            $this->fail('Item not found.');
        }
        $q = (float)$data['qty'];
        $delta = match ($data['direction']) {
            'add'    => $q,
            'remove' => -$q,
            'set'    => $q - (float)$item['stock_qty'],
        };
        if (abs($delta) < 0.0001) {
            $this->fail('The stock level would not change.');
        }
        $reason = $delta > 0 ? 'adjustment' : $data['reason'];
        $balance = Stock::move($type, (int)$item['id'], $delta, $reason, null, trim((string)($data['note'] ?? '')) ?: null);
        audit('stock_adjusted', $type, (int)$item['id'], sprintf('%s %+g → %s', $item['name'], $delta, qty($balance)));
        flash('success', sprintf('%s stock updated to %s.', $item['name'], qty($balance)));
        redirect_back('inventory');
    }
}
