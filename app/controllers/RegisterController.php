<?php
declare(strict_types=1);

final class RegisterController extends Controller
{
    public function index(): void
    {
        $uid = (int)Auth::id();
        $reg = Register::currentFor($uid);
        $this->view('register/index', [
            'title' => 'Cash Register',
            'subtitle' => $reg ? 'Shift opened ' . fmt_datetime($reg['opened_at']) : 'Start a shift to take payments',
            'register' => $reg,
            'summary' => $reg ? Register::summary($reg) : null,
            'movements' => $reg ? $this->db->fetchAll('SELECT * FROM cash_movements WHERE register_id = ? ORDER BY id DESC', [$reg['id']]) : [],
            'recent' => $this->db->fetchAll("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'closed' ORDER BY id DESC LIMIT 5", [$uid]),
        ]);
    }

    public function open(): void
    {
        $this->requirePost();
        $data = $this->validate(['opening_cash' => ['Opening cash', 'required|numeric|min:0|max:99999999']]);
        $uid = (int)Auth::id();
        if (Register::currentFor($uid)) {
            flash('info', 'Your register is already open.');
        } else {
            $id = $this->db->insert('cash_registers', [
                'user_id'      => $uid,
                'opened_at'    => date('Y-m-d H:i:s'),
                'opening_cash' => round((float)$data['opening_cash'], 2),
                'status'       => 'open',
            ]);
            audit('register_opened', 'register', $id, 'Opening cash ' . money($data['opening_cash']));
            flash('success', 'Register opened with ' . money($data['opening_cash']) . '. Have a great shift!');
        }
        redirect(input('return') === 'pos' ? 'pos' : 'register');
    }

    public function movement(): void
    {
        $this->requirePost();
        $reg = Register::currentFor((int)Auth::id());
        if (!$reg) {
            $this->fail('Open your register first.');
        }
        $data = $this->validate([
            'type'   => ['Type', 'required|in:in,out'],
            'amount' => ['Amount', 'required|numeric|min:0.01|max:99999999'],
            'reason' => ['Reason', 'required|maxlen:255'],
        ]);
        if ($data['type'] === 'out' && (float)$data['amount'] > Register::summary($reg)['expected_cash'] + 0.001) {
            $this->fail('There is not enough cash in the drawer for this withdrawal.');
        }
        $this->db->insert('cash_movements', [
            'register_id' => $reg['id'],
            'user_id'     => Auth::id(),
            'type'        => $data['type'],
            'amount'      => round((float)$data['amount'], 2),
            'reason'      => trim($data['reason']),
        ]);
        audit('cash_' . $data['type'], 'register', (int)$reg['id'], money($data['amount']) . ' · ' . trim($data['reason']));
        flash('success', ($data['type'] === 'in' ? 'Cash in' : 'Cash out') . ' of ' . money($data['amount']) . ' recorded.');
        redirect('register');
    }

    public function close(): void
    {
        $this->requirePost();
        $reg = Register::currentFor((int)Auth::id());
        if (!$reg) {
            $this->fail('You have no open register.');
        }
        $data = $this->validate([
            'closing_cash' => ['Counted cash', 'required|numeric|min:0|max:99999999'],
            'notes'        => ['Notes', 'maxlen:255'],
        ]);
        $sum = Register::summary($reg);
        $counted = round((float)$data['closing_cash'], 2);
        $diff = round($counted - $sum['expected_cash'], 2);
        $this->db->update('cash_registers', [
            'closed_at'     => date('Y-m-d H:i:s'),
            'closing_cash'  => $counted,
            'expected_cash' => $sum['expected_cash'],
            'difference'    => $diff,
            'notes'         => trim((string)($data['notes'] ?? '')) ?: null,
            'status'        => 'closed',
        ], 'id = ?', [$reg['id']]);
        audit('register_closed', 'register', (int)$reg['id'], sprintf('Expected %s, counted %s, difference %s', money($sum['expected_cash']), money($counted), money($diff)));
        flash($diff == 0.0 ? 'success' : 'warning', 'Register closed. ' . ($diff == 0.0 ? 'Cash balanced perfectly.' : ($diff > 0 ? 'Over by ' : 'Short by ') . money(abs($diff)) . '.'));
        redirect('register/view', ['id' => $reg['id']]);
    }

    public function history(): void
    {
        [$from, $to, $preset] = date_range('30d');
        $where = 'r.opened_at BETWEEN ? AND ?';
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM cash_registers r WHERE $where", $params), 25);
        $rows = $this->db->fetchAll(
            "SELECT r.*, u.name user_name,
                    (SELECT COUNT(*) FROM orders o WHERE o.register_id = r.id) orders,
                    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.register_id = r.id) sales
               FROM cash_registers r JOIN users u ON u.id = r.user_id WHERE $where ORDER BY r.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('register/history', ['title' => 'Register sessions', 'subtitle' => 'Shift history across all staff', 'rows' => $rows, 'p' => $p, 'from' => $from, 'to' => $to, 'preset' => $preset]);
    }

    public function show(): void
    {
        $reg = $this->db->fetch('SELECT r.*, u.name user_name FROM cash_registers r JOIN users u ON u.id = r.user_id WHERE r.id = ?', [input_int('id')]);
        if (!$reg) {
            App::abort(404);
        }
        if (has_role('cashier') && (int)$reg['user_id'] !== (int)Auth::id()) {
            App::abort(403);
        }
        $this->view('register/view', [
            'title' => 'Register #' . $reg['id'],
            'subtitle' => $reg['user_name'] . ' · ' . fmt_datetime($reg['opened_at']),
            'reg' => $reg,
            'summary' => Register::summary($reg),
            'movements' => $this->db->fetchAll('SELECT m.*, u.name user_name FROM cash_movements m JOIN users u ON u.id = m.user_id WHERE m.register_id = ? ORDER BY m.id', [$reg['id']]),
            'expenses' => $this->db->fetchAll('SELECT * FROM expenses WHERE register_id = ? ORDER BY id', [$reg['id']]),
            'types' => $this->db->fetchAll('SELECT order_type, COUNT(*) c, SUM(total) s FROM orders WHERE register_id = ? GROUP BY order_type', [$reg['id']]),
        ]);
    }
}
