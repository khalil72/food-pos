<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        [$from, $to, $preset] = date_range('today');
        $days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        $now = Metrics::summary($from, $to);
        $prev = Metrics::summary($prevFrom, $prevTo);
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];

        // Trend: at least 7 days so a single-day view still shows context
        $trendFrom = $days < 7 ? date('Y-m-d', strtotime($to . ' -6 days')) : $from;
        $trend = Metrics::daily($trendFrom, $to);

        $categories = $this->db->fetchAll(
            "SELECT COALESCE(c.name, 'Uncategorised') name, COALESCE(c.color, '#9CA3AF') color, SUM(oi.line_total) total
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
               LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
              WHERE o.created_at BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC",
            $range
        );
        $hourlyRows = $this->db->fetchAll('SELECT HOUR(created_at) h, COUNT(*) c, SUM(total) s FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY HOUR(created_at)', $range);
        $hourly = array_fill(0, 24, 0);
        foreach ($hourlyRows as $r) {
            $hourly[(int)$r['h']] = (int)$r['c'];
        }
        $types = $this->db->fetchAll('SELECT order_type, COUNT(*) c, SUM(total) s FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY order_type', $range);
        $payments = $this->db->fetchAll('SELECT method, SUM(amount) s FROM payments WHERE created_at BETWEEN ? AND ? GROUP BY method', $range);

        $topProducts = $this->db->fetchAll(
            'SELECT oi.product_name name, SUM(oi.qty - oi.returned_qty) qty, SUM(oi.line_total) revenue, MAX(p.image) image
               FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id
              WHERE o.created_at BETWEEN ? AND ? GROUP BY oi.product_name ORDER BY qty DESC LIMIT 6',
            $range
        );
        $recent = $this->db->fetchAll(
            'SELECT o.id, o.order_no, o.order_type, o.total, o.status, o.payment_method, o.created_at, c.name customer, u.name cashier
               FROM orders o LEFT JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = o.user_id
              ORDER BY o.id DESC LIMIT 8'
        );
        $openRegisters = $this->db->fetchAll("SELECT r.*, u.name user_name FROM cash_registers r JOIN users u ON u.id = r.user_id WHERE r.status = 'open' ORDER BY r.opened_at");

        $this->view('dashboard/index', [
            'title'    => 'Dashboard',
            'subtitle' => 'Performance for ' . range_label($from, $to),
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'now' => $now, 'prev' => $prev,
            'trend' => $trend, 'categories' => $categories, 'hourly' => $hourly,
            'types' => $types, 'payments' => $payments,
            'topProducts' => $topProducts, 'recent' => $recent,
            'lowStock' => Stock::lowStock(8),
            'openRegisters' => $openRegisters,
            'scripts' => ['vendor/chartjs/chart.umd.min.js', 'js/dashboard.js'],
        ]);
    }
}
