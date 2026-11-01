<?php
declare(strict_types=1);

final class ExpensesController extends Controller
{
    private function filters(): array
    {
        [$from, $to, $preset] = date_range('month');
        $where = ['e.expense_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($cid = input_int('category')) {
            $where[] = 'e.category_id = ?';
            $params[] = $cid;
        }
        if (($q = (string)input('q', '')) !== '') {
            $where[] = '(e.description LIKE ? OR e.reference LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }
        return [implode(' AND ', $where), $params, $from, $to, $preset];
    }

    public function index(): void
    {
        [$w, $params, $from, $to, $preset] = $this->filters();
        $total = (float)$this->db->value("SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE $w", $params);
        $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM expenses e WHERE $w", $params), 25);
        $rows = $this->db->fetchAll(
            "SELECT e.*, c.name category_name, c.color category_color, u.name user_name
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id JOIN users u ON u.id = e.user_id
              WHERE $w ORDER BY e.expense_date DESC, e.created_at DESC, e.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $byCategory = $this->db->fetchAll(
            "SELECT COALESCE(c.name,'Uncategorised') name, COALESCE(c.color,'#9CA3AF') color, SUM(e.amount) total, COUNT(*) cnt
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE $w GROUP BY c.id ORDER BY total DESC",
            $params
        );
        $days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
        $largest = $this->db->fetch("SELECT e.description, e.amount, e.expense_date FROM expenses e WHERE $w ORDER BY e.amount DESC LIMIT 1", $params);
        $today = (float)$this->db->value('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = CURDATE()');
        $this->view('expenses/index', [
            'title' => 'Expenses',
            'subtitle' => 'Operating costs for ' . range_label($from, $to),
            'expenses' => $rows, 'p' => $p, 'total' => $total, 'byCategory' => $byCategory,
            'days' => $days, 'largest' => $largest, 'today' => $today,
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'categories' => $this->db->fetchAll('SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id) used FROM expense_categories c ORDER BY c.name'),
            'register' => Register::currentFor((int)Auth::id()),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'category_id'    => ['Category', 'required|int'],
            'expense_date'   => ['Date', 'required|date'],
            'amount'         => ['Amount', 'required|numeric|min:0.01|max:999999999'],
            'payment_method' => ['Payment method', 'required|in:cash,card,online,bank'],
            'reference'      => ['Reference', 'maxlen:60'],
            'description'    => ['Description', 'required|maxlen:255'],
        ]);
        if (!$this->db->value('SELECT id FROM expense_categories WHERE id = ?', [(int)$data['category_id']])) {
            $this->fail('Choose a valid category.');
        }
        $row = [
            'category_id'    => (int)$data['category_id'],
            'expense_date'   => $data['expense_date'],
            'amount'         => round((float)$data['amount'], 2),
            'payment_method' => $data['payment_method'],
            'reference'      => trim((string)($data['reference'] ?? '')) ?: null,
            'description'    => trim($data['description']),
        ];
        if ($id = input_int('id')) {
            $existing = $this->findOr404('expenses', $id);
            if ($existing['register_id'] && $row['payment_method'] !== 'cash') {
                $row['register_id'] = null; // no longer paid from the drawer
            }
            $this->db->update('expenses', $row, 'id = ?', [$id]);
        } else {
            $row['user_id'] = Auth::id();
            if (input('from_drawer') === '1' && $row['payment_method'] === 'cash') {
                $reg = Register::currentFor((int)Auth::id());
                if (!$reg) {
                    $this->fail('Open your cash register to pay an expense from the drawer.');
                }
                $row['register_id'] = $reg['id'];
            }
            $id = $this->db->insert('expenses', $row);
        }
        audit('expense_saved', 'expense', $id, $row['description'] . ' · ' . money($row['amount']));
        flash('success', 'Expense saved.');
        redirect_back('expenses');
    }

    public function delete(): void
    {
        $this->requirePost();
        $e = $this->findOr404('expenses', input_int('id'));
        $this->db->delete('expenses', 'id = ?', [$e['id']]);
        audit('expense_deleted', 'expense', (int)$e['id'], $e['description'] . ' · ' . money($e['amount']));
        flash('success', 'Expense deleted.');
        redirect_back('expenses');
    }

    public function export(): void
    {
        [$w, $params, $from, $to] = $this->filters();
        $rows = $this->db->fetchAll(
            "SELECT e.expense_date, COALESCE(c.name,''), e.description, e.payment_method, e.reference, e.amount, u.name
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id JOIN users u ON u.id = e.user_id
              WHERE $w ORDER BY e.expense_date, e.id",
            $params
        );
        csv_download("expenses-$from-$to.csv", ['Date', 'Category', 'Description', 'Method', 'Reference', 'Amount', 'Recorded by'], $rows);
    }

    public function categorySave(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'  => ['Name', 'required|maxlen:80'],
            'color' => ['Colour', 'required|color'],
        ]);
        $id = input_int('id');
        if ($this->db->value('SELECT id FROM expense_categories WHERE name = ? AND id <> ?', [trim($data['name']), $id])) {
            $this->fail('A category with this name already exists.');
        }
        $row = ['name' => trim($data['name']), 'color' => strtoupper($data['color'])];
        $id ? $this->db->update('expense_categories', $row, 'id = ?', [$id]) : $this->db->insert('expense_categories', $row);
        flash('success', 'Expense category saved.');
        redirect('expenses');
    }

    public function categoryDelete(): void
    {
        $this->requirePost();
        $c = $this->findOr404('expense_categories', input_int('id'));
        $this->db->delete('expense_categories', 'id = ?', [$c['id']]);
        flash('success', 'Category deleted. Its expenses are now uncategorised.');
        redirect('expenses');
    }
}
