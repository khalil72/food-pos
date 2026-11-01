<?php
declare(strict_types=1);

final class Register
{
    public static function currentFor(int $userId): ?array
    {
        return Database::instance()->fetch(
            "SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
            [$userId]
        );
    }

    /** Totals for a register session; expected cash is what should be in the drawer. */
    public static function summary(array $register): array
    {
        $db = Database::instance();
        $id = (int)$register['id'];

        $pay = ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0];
        foreach ($db->fetchAll('SELECT method, SUM(amount) total FROM payments WHERE register_id = ? GROUP BY method', [$id]) as $r) {
            $pay[$r['method']] = (float)$r['total'];
        }
        $refunds = ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0];
        foreach ($db->fetchAll('SELECT refund_method, SUM(amount) total FROM returns WHERE register_id = ? GROUP BY refund_method', [$id]) as $r) {
            $refunds[$r['refund_method']] = (float)$r['total'];
        }
        $cashIn = (float)$db->value("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE register_id = ? AND type = 'in'", [$id]);
        $cashOut = (float)$db->value("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE register_id = ? AND type = 'out'", [$id]);
        $expenses = (float)$db->value('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE register_id = ?', [$id]);
        $orders = $db->fetch('SELECT COUNT(*) cnt, COALESCE(SUM(total),0) total, COALESCE(SUM(discount_amount + loyalty_discount),0) discounts FROM orders WHERE register_id = ?', [$id]);

        $expected = (float)$register['opening_cash'] + $pay['cash'] - $refunds['cash'] + $cashIn - $cashOut - $expenses;

        return [
            'opening'        => (float)$register['opening_cash'],
            'payments'       => $pay,
            'refunds'        => $refunds,
            'refund_total'   => array_sum($refunds),
            'cash_in'        => $cashIn,
            'cash_out'       => $cashOut,
            'expenses'       => $expenses,
            'orders'         => (int)$orders['cnt'],
            'sales_total'    => (float)$orders['total'],
            'discounts'      => (float)$orders['discounts'],
            'expected_cash'  => round($expected, 2),
        ];
    }
}
