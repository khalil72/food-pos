<?php
declare(strict_types=1);

/**
 * Financial figures shared by the dashboard and reports.
 *
 * Net revenue = sales excluding tax, minus refunds excluding tax.
 * Gross profit = net revenue − cost of goods sold (reversed for restocked returns).
 * Net profit = gross profit − operating expenses.
 */
final class Metrics
{
    public static function summary(string $from, string $to): array
    {
        $db = Database::instance();
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $o = $db->fetch(
            'SELECT COUNT(*) orders, COALESCE(SUM(total),0) gross, COALESCE(SUM(tax_amount),0) tax,
                    COALESCE(SUM(discount_amount + loyalty_discount),0) discounts, COALESCE(SUM(cost_total),0) cogs,
                    COALESCE(SUM(delivery_fee),0) delivery, COALESCE(SUM(subtotal),0) subtotal
               FROM orders WHERE created_at BETWEEN ? AND ?',
            $range
        );
        $r = $db->fetch(
            'SELECT COUNT(*) cnt, COALESCE(SUM(amount),0) amount, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(cost_reversed),0) cost
               FROM returns WHERE created_at BETWEEN ? AND ?',
            $range
        );
        $expenses = (float)$db->value('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?', [$from, $to]);

        $gross = (float)$o['gross'];
        $netRevenue = ($gross - (float)$o['tax']) - ((float)$r['amount'] - (float)$r['tax']);
        $cogs = (float)$o['cogs'] - (float)$r['cost'];
        $grossProfit = $netRevenue - $cogs;

        return [
            'orders'        => (int)$o['orders'],
            'gross_sales'   => $gross,
            'subtotal'      => (float)$o['subtotal'],
            'discounts'     => (float)$o['discounts'],
            'tax'           => (float)$o['tax'] - (float)$r['tax'],
            'delivery_fees' => (float)$o['delivery'],
            'refunds'       => (float)$r['amount'],
            'refund_count'  => (int)$r['cnt'],
            'net_sales'     => $gross - (float)$r['amount'],
            'net_revenue'   => round($netRevenue, 2),
            'cogs'          => round($cogs, 2),
            'gross_profit'  => round($grossProfit, 2),
            'expenses'      => $expenses,
            'net_profit'    => round($grossProfit - $expenses, 2),
            'avg_order'     => (int)$o['orders'] > 0 ? $gross / (int)$o['orders'] : 0,
            'gross_margin'  => $netRevenue > 0 ? $grossProfit / $netRevenue * 100 : 0,
        ];
    }

    /** Per-day series of net sales, expenses, profit for charts and P&L. */
    public static function daily(string $from, string $to): array
    {
        $db = Database::instance();
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $days = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $days[date('Y-m-d', $d)] = ['date' => date('Y-m-d', $d), 'orders' => 0, 'sales' => 0.0, 'tax' => 0.0, 'cogs' => 0.0,
                'refunds' => 0.0, 'refund_tax' => 0.0, 'refund_cost' => 0.0, 'expenses' => 0.0];
        }
        foreach ($db->fetchAll('SELECT DATE(created_at) d, COUNT(*) c, SUM(total) s, SUM(tax_amount) t, SUM(cost_total) k FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)', $range) as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']]['orders'] = (int)$r['c'];
                $days[$r['d']]['sales'] = (float)$r['s'];
                $days[$r['d']]['tax'] = (float)$r['t'];
                $days[$r['d']]['cogs'] = (float)$r['k'];
            }
        }
        foreach ($db->fetchAll('SELECT DATE(created_at) d, SUM(amount) a, SUM(tax_amount) t, SUM(cost_reversed) k FROM returns WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)', $range) as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']]['refunds'] = (float)$r['a'];
                $days[$r['d']]['refund_tax'] = (float)$r['t'];
                $days[$r['d']]['refund_cost'] = (float)$r['k'];
            }
        }
        foreach ($db->fetchAll('SELECT expense_date d, SUM(amount) a FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY expense_date', [$from, $to]) as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']]['expenses'] = (float)$r['a'];
            }
        }
        foreach ($days as &$d) {
            $d['net_revenue'] = round(($d['sales'] - $d['tax']) - ($d['refunds'] - $d['refund_tax']), 2);
            $d['gross_profit'] = round($d['net_revenue'] - ($d['cogs'] - $d['refund_cost']), 2);
            $d['net_profit'] = round($d['gross_profit'] - $d['expenses'], 2);
        }
        return array_values($days);
    }
}
