<?php
declare(strict_types=1);

final class CustomersController extends Controller
{
    public function index(): void
    {
        $where = '1=1';
        $params = [];
        if (($q = (string)input('q', '')) !== '') {
            $where = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $params = ["%$q%", "%$q%", "%$q%"];
        }
        $sort = match ((string)input('sort', '')) {
            'spent'  => 'total_spent DESC',
            'points' => 'loyalty_points DESC',
            'recent' => 'last_order DESC',
            default  => 'name ASC',
        };
        $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM customers WHERE $where", $params), 25);
        $rows = $this->db->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) order_count,
                    (SELECT MAX(created_at) FROM orders o WHERE o.customer_id = c.id) last_order
               FROM customers c WHERE $where ORDER BY $sort LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('customers/index', [
            'title' => 'Customers',
            'subtitle' => 'Guests, loyalty points and order history',
            'customers' => $rows, 'p' => $p,
            'stats' => $this->db->fetch('SELECT COUNT(*) cnt, COALESCE(SUM(loyalty_points),0) points, COALESCE(SUM(total_spent),0) spent FROM customers'),
        ]);
    }

    public function show(): void
    {
        $c = $this->findOr404('customers', input_int('id'));
        $params = [$c['id']];
        $own = '';
        if (has_role('cashier')) {
            $own = ' AND user_id = ?';
            $params[] = Auth::id();
        }
        $orders = $this->db->fetchAll("SELECT * FROM orders WHERE customer_id = ?$own ORDER BY id DESC LIMIT 50", $params);
        $stats = $this->db->fetch('SELECT COUNT(*) cnt, COALESCE(AVG(total),0) avg, MIN(created_at) first, MAX(created_at) last FROM orders WHERE customer_id = ?', [$c['id']]);
        $favourites = $this->db->fetchAll(
            'SELECT oi.product_name, SUM(oi.qty) qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.customer_id = ? GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5',
            [$c['id']]
        );
        $this->view('customers/view', ['title' => $c['name'], 'subtitle' => 'Customer profile', 'customer' => $c, 'orders' => $orders, 'stats' => $stats, 'favourites' => $favourites]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'    => ['Name', 'required|maxlen:120'],
            'phone'   => ['Phone', 'maxlen:30'],
            'email'   => ['Email', 'email|maxlen:150'],
            'address' => ['Address', 'maxlen:255'],
            'notes'   => ['Notes', 'maxlen:1000'],
        ]);
        $id = input_int('id');
        $phone = trim((string)($data['phone'] ?? '')) ?: null;
        if ($phone && $this->db->value('SELECT id FROM customers WHERE phone = ? AND id <> ?', [$phone, $id])) {
            $this->fail('Another customer already uses this phone number.');
        }
        $row = [
            'name'    => trim($data['name']),
            'phone'   => $phone,
            'email'   => trim((string)($data['email'] ?? '')) ?: null,
            'address' => trim((string)($data['address'] ?? '')) ?: null,
            'notes'   => trim((string)($data['notes'] ?? '')) ?: null,
        ];
        if ($id) {
            $this->findOr404('customers', $id);
            $this->db->update('customers', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('customers', $row);
        }
        audit('customer_saved', 'customer', $id, $row['name']);
        flash('success', 'Customer “' . $row['name'] . '” saved.');
        redirect_back('customers');
    }

    public function points(): void
    {
        $this->requirePost();
        $c = $this->findOr404('customers', input_int('id'));
        $data = $this->validate([
            'points' => ['Points', 'required|int|min:-1000000|max:1000000'],
            'reason' => ['Reason', 'required|maxlen:200'],
        ]);
        $new = max(0, (int)$c['loyalty_points'] + (int)$data['points']);
        $this->db->update('customers', ['loyalty_points' => $new], 'id = ?', [$c['id']]);
        audit('loyalty_adjusted', 'customer', (int)$c['id'], sprintf('%+d points (%s) → %d', (int)$data['points'], $data['reason'], $new));
        flash('success', "Loyalty balance is now $new points.");
        redirect('customers/view', ['id' => $c['id']]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $c = $this->findOr404('customers', input_int('id'));
        $this->db->delete('customers', 'id = ?', [$c['id']]);
        audit('customer_deleted', 'customer', (int)$c['id'], $c['name']);
        flash('success', 'Customer deleted. Their past orders remain as walk-in orders.');
        redirect('customers');
    }

    public function export(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT c.name, c.phone, c.email, c.address, c.loyalty_points, c.total_spent, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id), c.created_at FROM customers c ORDER BY c.name'
        );
        csv_download('customers-' . date('Y-m-d') . '.csv', ['Name', 'Phone', 'Email', 'Address', 'Points', 'Total spent', 'Orders', 'Since'], $rows);
    }
}
