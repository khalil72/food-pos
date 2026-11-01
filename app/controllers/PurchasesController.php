<?php
declare(strict_types=1);

final class PurchasesController extends Controller
{
    public function index(): void
    {
        [$from, $to, $preset] = date_range('30d');
        $where = ['p.purchase_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($sid = input_int('supplier')) {
            $where[] = 'p.supplier_id = ?';
            $params[] = $sid;
        }
        $status = (string)input('status', '');
        if ($status === 'paid') {
            $where[] = 'p.paid >= p.total';
        } elseif ($status === 'partial') {
            $where[] = 'p.paid > 0 AND p.paid < p.total';
        } elseif ($status === 'unpaid') {
            $where[] = 'p.paid = 0 AND p.total > 0';
        }
        $w = implode(' AND ', $where);
        $sum = $this->db->fetch("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) total, COALESCE(SUM(paid),0) paid FROM purchases p WHERE $w", $params);
        $p = paginate((int)$sum['cnt'], 25);
        $rows = $this->db->fetchAll(
            "SELECT p.*, s.name supplier_name, u.name user_name, (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) item_count
               FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id JOIN users u ON u.id = p.user_id
              WHERE $w ORDER BY p.purchase_date DESC, p.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('purchases/index', [
            'title' => 'Purchases',
            'subtitle' => 'Stock received from suppliers',
            'purchases' => $rows, 'p' => $p, 'sum' => $sum,
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'suppliers' => $this->db->fetchAll('SELECT id, name FROM suppliers ORDER BY name'),
        ]);
    }

    public function create(): void
    {
        $this->view('purchases/form', [
            'title' => 'New purchase',
            'subtitle' => 'Receive stock and update costs',
            'suppliers' => $this->db->fetchAll('SELECT id, name FROM suppliers ORDER BY name'),
            'ingredients' => $this->db->fetchAll('SELECT id, name, unit, cost_per_unit cost FROM ingredients ORDER BY name'),
            'products' => $this->db->fetchAll('SELECT id, name, cost FROM products WHERE track_stock = 1 ORDER BY name'),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'supplier_id'   => ['Supplier', 'int'],
            'purchase_date' => ['Date', 'required|date'],
            'reference'     => ['Reference', 'maxlen:60'],
            'paid'          => ['Amount paid', 'numeric|min:0|max:999999999'],
            'notes'         => ['Notes', 'maxlen:500'],
        ]);
        $lines = [];
        foreach ((array)($_POST['items'] ?? []) as $it) {
            [$type, $iid] = array_pad(explode(':', (string)($it['item'] ?? ''), 2), 2, '0');
            $q = (float)($it['qty'] ?? 0);
            $cost = (float)($it['unit_cost'] ?? 0);
            if (!in_array($type, ['product', 'ingredient'], true) || !(int)$iid) {
                continue;
            }
            if ($q <= 0 || $cost < 0) {
                $this->fail('Each line needs a quantity above zero and a valid cost.');
            }
            $table = $type === 'product' ? 'products' : 'ingredients';
            $name = $this->db->value("SELECT name FROM `$table` WHERE id = ?", [(int)$iid]);
            if (!$name) {
                $this->fail('One of the selected items no longer exists.');
            }
            $lines[] = ['item_type' => $type, 'item_id' => (int)$iid, 'item_name' => $name, 'qty' => $q, 'unit_cost' => $cost, 'line_total' => round($q * $cost, 2)];
        }
        if (!$lines) {
            $this->fail('Add at least one item to the purchase.');
        }
        $total = round(array_sum(array_column($lines, 'line_total')), 2);
        $paid = min($total, round((float)($data['paid'] ?? 0), 2));
        $supplierId = ($data['supplier_id'] ?? '') !== '' ? (int)$data['supplier_id'] : null;

        $id = $this->db->transaction(function (Database $db) use ($lines, $total, $paid, $supplierId, $data) {
            $id = $db->insert('purchases', [
                'supplier_id'   => $supplierId,
                'user_id'       => Auth::id(),
                'reference'     => trim((string)($data['reference'] ?? '')) ?: null,
                'purchase_date' => $data['purchase_date'],
                'total'         => $total,
                'paid'          => $paid,
                'notes'         => trim((string)($data['notes'] ?? '')) ?: null,
            ]);
            $ref = 'PUR-' . $id;
            foreach ($lines as $l) {
                $db->insert('purchase_items', $l + ['purchase_id' => $id]);
                Stock::move($l['item_type'], $l['item_id'], $l['qty'], 'purchase', $ref);
                if ($l['item_type'] === 'ingredient') {
                    $db->update('ingredients', ['cost_per_unit' => $l['unit_cost']], 'id = ?', [$l['item_id']]);
                    if ($supplierId) {
                        $db->query('UPDATE ingredients SET supplier_id = ? WHERE id = ? AND supplier_id IS NULL', [$supplierId, $l['item_id']]);
                    }
                } else {
                    $db->update('products', ['cost' => round($l['unit_cost'], 2)], 'id = ?', [$l['item_id']]);
                }
            }
            return $id;
        });
        audit('purchase_created', 'purchase', $id, 'PUR-' . $id . ' · ' . money($total));
        flash('success', 'Purchase recorded and stock updated.');
        redirect('purchases/view', ['id' => $id]);
    }

    public function show(): void
    {
        $pu = $this->db->fetch(
            'SELECT p.*, s.name supplier_name, s.phone supplier_phone, s.email supplier_email, s.address supplier_address, u.name user_name
               FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id JOIN users u ON u.id = p.user_id WHERE p.id = ?',
            [input_int('id')]
        );
        if (!$pu) {
            App::abort(404);
        }
        $this->view('purchases/view', [
            'title' => 'Purchase ' . ($pu['reference'] ?: 'PUR-' . $pu['id']),
            'subtitle' => fmt_date($pu['purchase_date']) . ' · ' . ($pu['supplier_name'] ?? 'No supplier'),
            'purchase' => $pu,
            'items' => $this->db->fetchAll('SELECT * FROM purchase_items WHERE purchase_id = ?', [$pu['id']]),
        ]);
    }

    public function payment(): void
    {
        $this->requirePost();
        $pu = $this->findOr404('purchases', input_int('id'));
        $data = $this->validate(['amount' => ['Amount', 'required|numeric|min:0.01']]);
        $due = (float)$pu['total'] - (float)$pu['paid'];
        $amount = min($due, round((float)$data['amount'], 2));
        if ($amount <= 0) {
            $this->fail('This purchase is already fully paid.');
        }
        $this->db->update('purchases', ['paid' => (float)$pu['paid'] + $amount], 'id = ?', [$pu['id']]);
        audit('purchase_payment', 'purchase', (int)$pu['id'], money($amount));
        flash('success', 'Payment of ' . money($amount) . ' recorded.');
        redirect('purchases/view', ['id' => $pu['id']]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $pu = $this->findOr404('purchases', input_int('id'));
        $this->db->transaction(function (Database $db) use ($pu) {
            foreach ($db->fetchAll('SELECT * FROM purchase_items WHERE purchase_id = ?', [$pu['id']]) as $l) {
                $table = $l['item_type'] === 'product' ? 'products' : 'ingredients';
                if ($db->value("SELECT id FROM `$table` WHERE id = ?", [$l['item_id']])) {
                    Stock::move($l['item_type'], (int)$l['item_id'], -(float)$l['qty'], 'purchase_delete', 'PUR-' . $pu['id']);
                }
            }
            $db->delete('purchases', 'id = ?', [$pu['id']]);
        });
        audit('purchase_deleted', 'purchase', (int)$pu['id'], 'PUR-' . $pu['id'] . ' · ' . money($pu['total']));
        flash('success', 'Purchase deleted and stock reversed.');
        redirect('purchases');
    }
}
