<?php
declare(strict_types=1);

final class PosController extends Controller
{
    public function index(): void
    {
        $user = user();
        $register = Register::currentFor((int)$user['id']);
        $categories = $this->db->fetchAll('SELECT id, name, icon, color FROM categories WHERE is_active = 1 ORDER BY sort_order, name');

        $this->view('pos/index', [
            'title'      => 'Point of Sale',
            'register'   => $register,
            'categories' => $categories,
            'config'     => [
                'products'          => Catalog::forPos(),
                'categories'        => $categories,
                'taxRate'           => (float)setting('tax_rate', 0),
                'taxInclusive'      => setting('tax_inclusive', '0') === '1',
                'taxName'           => setting('tax_name', 'Tax'),
                'deliveryFee'       => (float)setting('delivery_fee', 0),
                'defaultType'       => setting('default_order_type', 'takeaway'),
                'loyalty'           => [
                    'enabled'    => setting('loyalty_enabled', '1') === '1',
                    'pointValue' => (float)setting('loyalty_point_value', 1),
                    'minRedeem'  => (int)setting('loyalty_min_redeem', 0),
                    'earnAmount' => (float)setting('loyalty_earn_amount', 100),
                ],
                'role'              => $user['role'],
                'maxDiscount'       => $user['role'] === 'cashier' ? (float)setting('cashier_max_discount', 20) : 100,
                'requireRegister'   => setting('require_register', '1') === '1',
                'hasRegister'       => (bool)$register,
                'autoPrint'         => setting('auto_print', '0') === '1',
                'receiptSize'       => setting('receipt_size', '80'),
                'userId'            => (int)$user['id'],
            ],
        ], 'pos');
    }

    public function catalog(): void
    {
        json_out(['ok' => true, 'products' => Catalog::forPos()]);
    }

    public function checkout(): void
    {
        $this->requirePost();
        $user = user();
        $register = Register::currentFor((int)$user['id']);
        if (!$register && setting('require_register', '1') === '1') {
            json_out(['ok' => false, 'message' => 'Open the cash register before taking orders.'], 422);
        }
        try {
            $order = OrderService::create(json_body(), $user, $register);
        } catch (InvalidArgumentException $e) {
            json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        json_out([
            'ok'     => true,
            'order'  => [
                'id'       => $order['id'],
                'order_no' => $order['order_no'],
                'total'    => (float)$order['total'],
                'tendered' => (float)$order['tendered'],
                'change'   => (float)$order['change_amount'],
                'method'   => $order['payment_method'],
                'points'   => (int)$order['loyalty_points_earned'],
            ],
            'receipt' => url('orders/receipt', ['id' => $order['id']]),
        ]);
    }

    public function customers(): void
    {
        $q = (string)input('q', '');
        $like = '%' . $q . '%';
        $rows = $this->db->fetchAll(
            'SELECT id, name, phone, email, address, loyalty_points FROM customers
              WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name LIMIT 12',
            [$like, $like, $like]
        );
        json_out(['ok' => true, 'customers' => $rows]);
    }

    public function customerSave(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'    => ['Name', 'required|maxlen:120'],
            'phone'   => ['Phone', 'maxlen:30'],
            'email'   => ['Email', 'email|maxlen:150'],
            'address' => ['Address', 'maxlen:255'],
        ]);
        $phone = trim((string)($data['phone'] ?? '')) ?: null;
        if ($phone && $this->db->value('SELECT id FROM customers WHERE phone = ?', [$phone])) {
            json_out(['ok' => false, 'message' => 'A customer with this phone number already exists.'], 422);
        }
        $id = $this->db->insert('customers', [
            'name'    => trim($data['name']),
            'phone'   => $phone,
            'email'   => trim((string)($data['email'] ?? '')) ?: null,
            'address' => trim((string)($data['address'] ?? '')) ?: null,
        ]);
        audit('customer_created', 'customer', $id, trim($data['name']));
        json_out(['ok' => true, 'customer' => $this->db->fetch('SELECT id, name, phone, email, address, loyalty_points FROM customers WHERE id = ?', [$id])]);
    }

    public function recent(): void
    {
        $user = user();
        $params = [date('Y-m-d 00:00:00')];
        $where = 'o.created_at >= ?';
        if ($user['role'] === 'cashier') {
            $where .= ' AND o.user_id = ?';
            $params[] = $user['id'];
        }
        $rows = $this->db->fetchAll(
            "SELECT o.id, o.order_no, o.order_type, o.table_no, o.total, o.payment_method, o.status, o.created_at, c.name customer
               FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE $where ORDER BY o.id DESC LIMIT 25",
            $params
        );
        foreach ($rows as &$r) {
            $r['time'] = date('h:i A', strtotime($r['created_at']));
            $r['type_label'] = order_type_label($r['order_type']);
            $r['total_fmt'] = money($r['total']);
            $r['receipt'] = url('orders/receipt', ['id' => $r['id']]);
        }
        json_out(['ok' => true, 'orders' => $rows]);
    }
}
