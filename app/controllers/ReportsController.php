<?php
declare(strict_types=1);

final class ReportsController extends Controller
{
    public function index(): void
    {
        [$from, $to] = date_range('month');
        $this->view('reports/index', [
            'title' => 'Reports',
            'subtitle' => 'Insights to run the restaurant',
            'summary' => Metrics::summary($from, $to),
        ]);
    }

    /** Renders a tabular report, or streams the first section as CSV when ?export=csv. */
    private function render(string $title, string $slug, array $cards, array $sections, array $extra = []): void
    {
        [$from, $to, $preset] = date_range($extra['default_range'] ?? 'month');
        if (input('export') === 'csv') {
            $s = $sections[0];
            $cols = $s['columns'];
            $cell = fn($v) => is_float($v) ? round($v, 2) : $v;
            $rows = array_map(fn($r) => array_map(fn($k) => $cell($r[$k] ?? ''), array_keys($cols)), $s['rows']);
            if (!empty($s['footer'])) {
                $rows[] = array_map(fn($k) => $cell($s['footer'][$k] ?? ''), array_keys($cols));
            }
            audit('export', 'report', null, "$title $from to $to");
            csv_download("$slug-$from-$to.csv", array_map(fn($c) => $c[0], $cols), $rows);
        }
        $this->view('reports/table', [
            'title' => $title,
            'subtitle' => range_label($from, $to),
            'slug' => $slug, 'cards' => $cards, 'sections' => $sections,
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'chart' => $extra['chart'] ?? null,
            'note' => $extra['note'] ?? null,
            'hideDates' => $extra['hide_dates'] ?? false,
            'scripts' => isset($extra['chart']) ? ['vendor/chartjs/chart.umd.min.js'] : [],
        ]);
    }

    public function sales(): void
    {
        [$from, $to] = date_range('month');
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $days = Metrics::daily($from, $to);
        $disc = [];
        foreach ($this->db->fetchAll('SELECT DATE(created_at) d, SUM(discount_amount + loyalty_discount) x, SUM(delivery_fee) f FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)', $range) as $r) {
            $disc[$r['d']] = $r;
        }
        $rows = [];
        foreach ($days as $d) {
            $rows[] = [
                'date' => $d['date'], 'orders' => $d['orders'], 'sales' => $d['sales'],
                'discounts' => (float)($disc[$d['date']]['x'] ?? 0), 'tax' => $d['tax'], 'delivery' => (float)($disc[$d['date']]['f'] ?? 0),
                'refunds' => $d['refunds'], 'net' => $d['sales'] - $d['refunds'],
                'avg' => $d['orders'] ? $d['sales'] / $d['orders'] : 0,
            ];
        }
        $sum = fn($k) => array_sum(array_column($rows, $k));
        $m = Metrics::summary($from, $to);
        $types = $this->db->fetchAll('SELECT order_type, COUNT(*) orders, SUM(total) total, AVG(total) avg FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY order_type ORDER BY total DESC', $range);

        $this->render('Sales report', 'sales', [
            ['Gross sales', money($m['gross_sales'])], ['Orders', number_format($m['orders'])], ['Avg order', money($m['avg_order'])],
            ['Discounts', money($m['discounts'])], ['Tax collected', money($m['tax'])], ['Net sales', money($m['net_sales'])],
        ], [
            [
                'title' => 'Daily breakdown',
                'columns' => ['date' => ['Date', 'date'], 'orders' => ['Orders', 'int'], 'sales' => ['Gross sales', 'money'], 'discounts' => ['Discounts', 'money'],
                    'tax' => ['Tax', 'money'], 'delivery' => ['Delivery fees', 'money'], 'refunds' => ['Refunds', 'money'], 'net' => ['Net sales', 'money'], 'avg' => ['Avg order', 'money']],
                'rows' => array_reverse($rows),
                'footer' => ['date' => 'Total', 'orders' => $sum('orders'), 'sales' => $sum('sales'), 'discounts' => $sum('discounts'), 'tax' => $sum('tax'),
                    'delivery' => $sum('delivery'), 'refunds' => $sum('refunds'), 'net' => $sum('net'), 'avg' => $sum('orders') ? $sum('sales') / $sum('orders') : 0],
            ],
            [
                'title' => 'By order type',
                'columns' => ['type' => ['Order type', 'text'], 'orders' => ['Orders', 'int'], 'total' => ['Sales', 'money'], 'avg' => ['Avg order', 'money']],
                'rows' => array_map(fn($t) => ['type' => order_type_label($t['order_type']), 'orders' => $t['orders'], 'total' => $t['total'], 'avg' => $t['avg']], $types),
            ],
        ], ['chart' => ['type' => 'bar', 'labels' => array_map(fn($r) => date('d M', strtotime($r['date'])), $rows), 'series' => [['Net sales', array_column($rows, 'net'), '#E8572A']]]]);
    }

    public function products(): void
    {
        [$from, $to] = date_range('month');
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $rows = $this->db->fetchAll(
            "SELECT oi.product_name name, COALESCE(c.name,'—') category, SUM(oi.qty) sold, SUM(oi.returned_qty) returned,
                    SUM(oi.unit_price * (oi.qty - oi.returned_qty)) revenue, SUM(oi.unit_cost * (oi.qty - oi.returned_qty)) cost
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
               LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
              WHERE o.created_at BETWEEN ? AND ? GROUP BY oi.product_name, c.name ORDER BY revenue DESC",
            $range
        );
        foreach ($rows as &$r) {
            $r['profit'] = (float)$r['revenue'] - (float)$r['cost'];
            $r['margin'] = (float)$r['revenue'] > 0 ? $r['profit'] / (float)$r['revenue'] * 100 : 0;
        }
        unset($r);
        $cats = [];
        foreach ($rows as $r) {
            $c = &$cats[$r['category']];
            $c['category'] = $r['category'];
            $c['sold'] = ($c['sold'] ?? 0) + (int)$r['sold'] - (int)$r['returned'];
            $c['revenue'] = ($c['revenue'] ?? 0) + (float)$r['revenue'];
            $c['profit'] = ($c['profit'] ?? 0) + $r['profit'];
            unset($c);
        }
        usort($cats, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $sum = fn($k) => array_sum(array_column($rows, $k));
        $top = array_slice($rows, 0, 10);
        $this->render('Product sales', 'products', [
            ['Items sold', number_format($sum('sold') - $sum('returned'))], ['Item revenue', money($sum('revenue'))],
            ['Cost of goods', money($sum('cost'))], ['Item profit', money($sum('revenue') - $sum('cost'))],
        ], [
            [
                'title' => 'Products',
                'columns' => ['name' => ['Product', 'text'], 'category' => ['Category', 'text'], 'sold' => ['Sold', 'int'], 'returned' => ['Returned', 'int'],
                    'revenue' => ['Revenue', 'money'], 'cost' => ['Cost', 'money'], 'profit' => ['Profit', 'money'], 'margin' => ['Margin', 'pct']],
                'rows' => $rows,
                'footer' => ['name' => 'Total', 'sold' => $sum('sold'), 'returned' => $sum('returned'), 'revenue' => $sum('revenue'), 'cost' => $sum('cost'),
                    'profit' => $sum('profit'), 'margin' => $sum('revenue') > 0 ? $sum('profit') / $sum('revenue') * 100 : 0],
            ],
            [
                'title' => 'By category',
                'columns' => ['category' => ['Category', 'text'], 'sold' => ['Items sold', 'int'], 'revenue' => ['Revenue', 'money'], 'profit' => ['Profit', 'money']],
                'rows' => array_values($cats),
            ],
        ], [
            'note' => 'Revenue uses item prices before order-level discounts and excludes returned quantities.',
            'chart' => ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'name'), 'series' => [['Revenue', array_map('floatval', array_column($top, 'revenue')), '#E8572A'], ['Profit', array_map(fn($r) => round($r['profit'], 2), $top), '#15803D']]],
        ]);
    }

    public function profit(): void
    {
        [$from, $to, $preset] = date_range('month');
        $m = Metrics::summary($from, $to);
        $days = Metrics::daily($from, $to);
        $expenses = $this->db->fetchAll(
            "SELECT COALESCE(c.name,'Uncategorised') name, COALESCE(c.color,'#9CA3AF') color, SUM(e.amount) total
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.expense_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC",
            [$from, $to]
        );
        if (input('export') === 'csv') {
            $rows = [
                ['Gross sales (incl. tax)', $m['gross_sales']],
                ['Net revenue (ex. tax, after refunds)', $m['net_revenue']],
                ['Cost of goods sold', $m['cogs']],
                ['Gross profit', $m['gross_profit']],
            ];
            foreach ($expenses as $x) {
                $rows[] = ['Expense: ' . $x['name'], $x['total']];
            }
            $rows[] = ['Total expenses', $m['expenses']];
            $rows[] = ['Net profit', $m['net_profit']];
            $rows[] = [];
            $rows[] = ['Date', 'Net revenue', 'COGS', 'Gross profit', 'Expenses', 'Net profit'];
            foreach ($days as $d) {
                $rows[] = [$d['date'], $d['net_revenue'], round($d['cogs'] - $d['refund_cost'], 2), $d['gross_profit'], $d['expenses'], $d['net_profit']];
            }
            csv_download("profit-loss-$from-$to.csv", ['Line', 'Amount'], $rows);
        }
        $this->view('reports/profit', [
            'title' => 'Profit & loss',
            'subtitle' => range_label($from, $to),
            'm' => $m, 'days' => $days, 'expenses' => $expenses,
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'scripts' => ['vendor/chartjs/chart.umd.min.js'],
        ]);
    }

    public function expenses(): void
    {
        [$from, $to] = date_range('month');
        $byCat = $this->db->fetchAll(
            "SELECT COALESCE(c.name,'Uncategorised') name, COUNT(*) cnt, SUM(e.amount) total, COALESCE(c.color,'#9CA3AF') color
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.expense_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC",
            [$from, $to]
        );
        $list = $this->db->fetchAll(
            "SELECT e.expense_date date, COALESCE(c.name,'—') category, e.description, e.payment_method method, e.amount
               FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC, e.id DESC",
            [$from, $to]
        );
        foreach ($list as &$l) {
            $l['method'] = payment_label($l['method']);
        }
        unset($l);
        $total = array_sum(array_column($byCat, 'total'));
        foreach ($byCat as &$b) {
            $b['share'] = $total > 0 ? (float)$b['total'] / $total * 100 : 0;
        }
        unset($b);
        $this->render('Expense report', 'expenses', [
            ['Total expenses', money($total)], ['Entries', count($list)], ['Categories', count($byCat)],
            ['Daily average', money($total / max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1))],
        ], [
            ['title' => 'All expenses', 'columns' => ['date' => ['Date', 'date'], 'category' => ['Category', 'text'], 'description' => ['Description', 'text'], 'method' => ['Paid by', 'text'], 'amount' => ['Amount', 'money']],
                'rows' => $list, 'footer' => ['date' => 'Total', 'amount' => $total]],
            ['title' => 'By category', 'columns' => ['name' => ['Category', 'text'], 'cnt' => ['Entries', 'int'], 'total' => ['Amount', 'money'], 'share' => ['Share', 'pct']],
                'rows' => $byCat, 'footer' => ['name' => 'Total', 'cnt' => count($list), 'total' => $total, 'share' => $total > 0 ? 100 : 0]],
        ], ['chart' => ['type' => 'doughnut', 'labels' => array_column($byCat, 'name'), 'series' => [['Expenses', array_map('floatval', array_column($byCat, 'total')), array_column($byCat, 'color')]]]]);
    }

    public function payments(): void
    {
        [$from, $to] = date_range('month');
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $rows = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $rows[date('Y-m-d', $d)] = ['date' => date('Y-m-d', $d), 'cash' => 0.0, 'card' => 0.0, 'online' => 0.0, 'refunds' => 0.0];
        }
        foreach ($this->db->fetchAll('SELECT DATE(created_at) d, method, SUM(amount) s FROM payments WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at), method', $range) as $r) {
            $rows[$r['d']][$r['method']] = (float)$r['s'];
        }
        foreach ($this->db->fetchAll('SELECT DATE(created_at) d, SUM(amount) s FROM returns WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)', $range) as $r) {
            $rows[$r['d']]['refunds'] = (float)$r['s'];
        }
        foreach ($rows as &$r) {
            $r['total'] = $r['cash'] + $r['card'] + $r['online'] - $r['refunds'];
        }
        unset($r);
        $rows = array_values($rows);
        $sum = fn($k) => array_sum(array_column($rows, $k));
        $this->render('Payment methods', 'payments', [
            ['Cash', money($sum('cash'))], ['Card', money($sum('card'))], ['Online / Wallet', money($sum('online'))], ['Refunds', money($sum('refunds'))], ['Net received', money($sum('total'))],
        ], [[
            'title' => 'Daily collections',
            'columns' => ['date' => ['Date', 'date'], 'cash' => ['Cash', 'money'], 'card' => ['Card', 'money'], 'online' => ['Online', 'money'], 'refunds' => ['Refunds', 'money'], 'total' => ['Net', 'money']],
            'rows' => array_reverse($rows),
            'footer' => ['date' => 'Total', 'cash' => $sum('cash'), 'card' => $sum('card'), 'online' => $sum('online'), 'refunds' => $sum('refunds'), 'total' => $sum('total')],
        ]], ['chart' => ['type' => 'doughnut', 'labels' => ['Cash', 'Card', 'Online'], 'series' => [['Payments', [$sum('cash'), $sum('card'), $sum('online')], ['#E8572A', '#1F1B17', '#F5A524']]]]]);
    }

    public function inventory(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM (
                SELECT 'Ingredient' type, i.name, i.unit, i.stock_qty, i.low_stock_level, i.cost_per_unit cost, GREATEST(i.stock_qty,0) * i.cost_per_unit value
                  FROM ingredients i
                UNION ALL
                SELECT 'Product', p.name, 'pcs', p.stock_qty, p.low_stock_level, p.cost, GREATEST(p.stock_qty,0) * p.cost
                  FROM products p WHERE p.track_stock = 1
             ) s ORDER BY value DESC"
        );
        foreach ($rows as &$r) {
            $r['status'] = (float)$r['stock_qty'] <= 0 ? 'Out of stock' : ((float)$r['stock_qty'] <= (float)$r['low_stock_level'] ? 'Low' : 'OK');
        }
        unset($r);
        [$from, $to] = date_range('month');
        $usage = $this->db->fetchAll(
            "SELECT i.name, i.unit, SUM(-m.change_qty) used, SUM(-m.change_qty) * i.cost_per_unit value
               FROM stock_movements m JOIN ingredients i ON i.id = m.item_id
              WHERE m.item_type = 'ingredient' AND m.reason IN ('sale','wastage') AND m.created_at BETWEEN ? AND ?
              GROUP BY i.id ORDER BY value DESC",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        $wastage = (float)$this->db->value(
            "SELECT COALESCE(SUM(-m.change_qty * CASE m.item_type WHEN 'ingredient' THEN (SELECT cost_per_unit FROM ingredients WHERE id = m.item_id) ELSE (SELECT cost FROM products WHERE id = m.item_id) END),0)
               FROM stock_movements m WHERE m.reason = 'wastage' AND m.created_at BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );
        $this->render('Inventory valuation', 'inventory', [
            ['Stock value', money(array_sum(array_column($rows, 'value')))], ['Items tracked', count($rows)],
            ['Low / out of stock', count(array_filter($rows, fn($r) => $r['status'] !== 'OK'))], ['Wastage (period)', money($wastage)],
        ], [
            ['title' => 'Current stock', 'columns' => ['type' => ['Type', 'text'], 'name' => ['Item', 'text'], 'stock_qty' => ['In stock', 'qty'], 'unit' => ['Unit', 'text'],
                'low_stock_level' => ['Reorder at', 'qty'], 'cost' => ['Unit cost', 'money'], 'value' => ['Value', 'money'], 'status' => ['Status', 'status']],
                'rows' => $rows, 'footer' => ['type' => 'Total', 'value' => array_sum(array_column($rows, 'value'))]],
            ['title' => 'Ingredient usage in period', 'columns' => ['name' => ['Ingredient', 'text'], 'used' => ['Used', 'qty'], 'unit' => ['Unit', 'text'], 'value' => ['Cost', 'money']], 'rows' => $usage],
        ], ['note' => 'Stock levels are current; usage and wastage use the selected date range.']);
    }

    public function staff(): void
    {
        [$from, $to] = date_range('month');
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $rows = $this->db->fetchAll(
            "SELECT u.name, u.role,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.created_at BETWEEN ? AND ?) orders,
                    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.created_at BETWEEN ? AND ?) sales,
                    (SELECT COALESCE(SUM(discount_amount + loyalty_discount),0) FROM orders o WHERE o.user_id = u.id AND o.created_at BETWEEN ? AND ?) discounts,
                    (SELECT COALESCE(SUM(amount),0) FROM returns r WHERE r.user_id = u.id AND r.created_at BETWEEN ? AND ?) refunds,
                    (SELECT COUNT(*) FROM cash_registers c WHERE c.user_id = u.id AND c.opened_at BETWEEN ? AND ?) shifts,
                    (SELECT COALESCE(SUM(difference),0) FROM cash_registers c WHERE c.user_id = u.id AND c.status = 'closed' AND c.opened_at BETWEEN ? AND ?) cash_diff
               FROM users u ORDER BY sales DESC",
            [...$range, ...$range, ...$range, ...$range, ...$range, ...$range]
        );
        foreach ($rows as &$r) {
            $r['avg'] = $r['orders'] ? (float)$r['sales'] / (int)$r['orders'] : 0;
            $r['role'] = ucfirst($r['role']);
        }
        unset($r);
        $sum = fn($k) => array_sum(array_column($rows, $k));
        $this->render('Staff performance', 'staff', [
            ['Orders', number_format($sum('orders'))], ['Sales', money($sum('sales'))], ['Discounts given', money($sum('discounts'))], ['Cash over/short', money($sum('cash_diff'))],
        ], [[
            'title' => 'By staff member',
            'columns' => ['name' => ['Staff', 'text'], 'role' => ['Role', 'text'], 'orders' => ['Orders', 'int'], 'sales' => ['Sales', 'money'], 'avg' => ['Avg order', 'money'],
                'discounts' => ['Discounts', 'money'], 'refunds' => ['Refunds', 'money'], 'shifts' => ['Shifts', 'int'], 'cash_diff' => ['Cash over/short', 'money']],
            'rows' => $rows,
            'footer' => ['name' => 'Total', 'orders' => $sum('orders'), 'sales' => $sum('sales'), 'discounts' => $sum('discounts'), 'refunds' => $sum('refunds'), 'shifts' => $sum('shifts'), 'cash_diff' => $sum('cash_diff')],
        ]]);
    }
}
