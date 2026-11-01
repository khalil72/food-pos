<?php
declare(strict_types=1);

final class OrdersController extends Controller
{
    /** Builds WHERE clause from list filters. Cashiers only see their own orders. */
    private function filters(): array
    {
        [$from, $to, $preset] = date_range('today');
        $where = ['o.created_at BETWEEN ? AND ?'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $u = user();
        if ($u['role'] === 'cashier') {
            $where[] = 'o.user_id = ?';
            $params[] = $u['id'];
        } elseif ($uid = input_int('user')) {
            $where[] = 'o.user_id = ?';
            $params[] = $uid;
        }
        if (in_array($t = (string)input('type', ''), ['dine_in', 'takeaway', 'delivery'], true)) {
            $where[] = 'o.order_type = ?';
            $params[] = $t;
        }
        if (in_array($m = (string)input('method', ''), ['cash', 'card', 'online'], true)) {
            $where[] = 'o.payment_method = ?';
            $params[] = $m;
        }
        if (in_array($s = (string)input('status', ''), ['completed', 'partially_refunded', 'refunded'], true)) {
            $where[] = 'o.status = ?';
            $params[] = $s;
        }
        if (($q = (string)input('q', '')) !== '') {
            $where[] = '(o.order_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR o.table_no = ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", $q);
        }
        return [implode(' AND ', $where), $params, $from, $to, $preset];
    }

    public function index(): void
    {
        [$where, $params, $from, $to, $preset] = $this->filters();
        $base = "FROM orders o LEFT JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = o.user_id WHERE $where";
        $totals = $this->db->fetch("SELECT COUNT(*) cnt, COALESCE(SUM(o.total),0) total, COALESCE(SUM(o.refunded_amount),0) refunded, COALESCE(SUM(o.tax_amount),0) tax, COALESCE(SUM(o.discount_amount + o.loyalty_discount),0) discounts $base", $params);
        $p = paginate((int)$totals['cnt'], 25);
        $orders = $this->db->fetchAll(
            "SELECT o.*, c.name customer_name, c.phone customer_phone, u.name cashier,
                    (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) item_count
             $base ORDER BY o.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('orders/index', [
            'title' => 'Orders',
            'subtitle' => range_label($from, $to),
            'orders' => $orders, 'p' => $p, 'totals' => $totals,
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'users' => has_role('admin', 'manager') ? $this->db->fetchAll('SELECT id, name FROM users ORDER BY name') : [],
        ]);
    }

    private function loadOrder(int $id): array
    {
        $order = $this->db->fetch(
            'SELECT o.*, c.name customer_name, c.phone customer_phone, c.email customer_email, c.address customer_address, c.loyalty_points customer_points, u.name cashier
               FROM orders o LEFT JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = o.user_id WHERE o.id = ?',
            [$id]
        );
        if (!$order) {
            App::abort(404);
        }
        $u = user();
        if ($u['role'] === 'cashier' && (int)$order['user_id'] !== (int)$u['id']) {
            App::abort(403);
        }
        $order['items'] = $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
        $order['payments'] = $this->db->fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
        $order['returns'] = $this->db->fetchAll('SELECT r.*, u.name user_name FROM returns r JOIN users u ON u.id = r.user_id WHERE r.order_id = ? ORDER BY r.id', [$id]);
        return $order;
    }

    public function show(): void
    {
        $order = $this->loadOrder(input_int('id'));
        $this->view('orders/view', ['title' => 'Order ' . $order['order_no'], 'subtitle' => fmt_datetime($order['created_at']) . ' · ' . $order['cashier'], 'order' => $order]);
    }

    public function receipt(): void
    {
        $order = $this->loadOrder(input_int('id'));
        $size = in_array($s = (string)input('size', setting('receipt_size', '80')), ['58', '80', 'a4'], true) ? $s : '80';
        View::render('orders/receipt', [
            'order'     => $order,
            'size'      => $size,
            'autoprint' => input('autoprint') === '1',
            'embed'     => input('embed') === '1',
        ], null);
    }

    public function refund(): void
    {
        $this->requirePost();
        $order = $this->loadOrder(input_int('id'));
        $lines = [];
        foreach ((array)($_POST['qty'] ?? []) as $itemId => $q) {
            if ((int)$q > 0) {
                $lines[(int)$itemId] = (int)$q;
            }
        }
        $u = user();
        try {
            $result = OrderService::refund(
                $order,
                $lines,
                (string)input('refund_method', 'cash'),
                input('restock') === '1',
                (string)input('reason', ''),
                $u,
                Register::currentFor((int)$u['id'])
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        flash('success', 'Refund of ' . money($result['amount']) . ' recorded.');
        redirect('orders/view', ['id' => $order['id']]);
    }

    public function export(): void
    {
        [$where, $params, $from, $to] = $this->filters();
        $rows = $this->db->fetchAll(
            "SELECT o.order_no, o.created_at, o.order_type, o.table_no, COALESCE(c.name,'Walk-in') customer, u.name cashier,
                    o.subtotal, o.discount_amount + o.loyalty_discount discount, o.tax_amount, o.delivery_fee, o.total,
                    o.payment_method, o.refunded_amount, o.status
               FROM orders o LEFT JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = o.user_id
              WHERE $where ORDER BY o.id",
            $params
        );
        audit('export', 'orders', null, "Orders $from to $to");
        csv_download("orders-$from-$to.csv",
            ['Order #', 'Date', 'Type', 'Table', 'Customer', 'Cashier', 'Subtotal', 'Discount', 'Tax', 'Delivery', 'Total', 'Payment', 'Refunded', 'Status'],
            $rows
        );
    }
}
