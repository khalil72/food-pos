<?php
declare(strict_types=1);

/**
 * Creates orders and refunds. All prices are recomputed from the database; the
 * client only supplies product/variant/add-on ids and quantities.
 */
final class OrderService
{
    /** Rounds to the currency precision so stored totals match what receipts show. */
    public static function money(float $v): float
    {
        return round($v + 0.0000001, min(2, (int)setting('currency_decimals', 2)));
    }

    /**
     * Prices a cart and returns the computed order. Throws InvalidArgumentException with a
     * user-facing message when the cart is invalid.
     */
    public static function price(array $payload, array $user): array
    {
        $db = Database::instance();
        $lines = $payload['items'] ?? [];
        if (!is_array($lines) || !$lines) {
            throw new InvalidArgumentException('The cart is empty.');
        }
        if (count($lines) > 200) {
            throw new InvalidArgumentException('Too many lines in one order.');
        }

        $ids = array_unique(array_map(fn($l) => (int)($l['product_id'] ?? 0), $lines));
        $products = Catalog::products($ids);
        $allowNegative = setting('allow_negative_stock', '0') === '1';

        $items = [];
        $subtotal = 0.0;
        $costTotal = 0.0;
        $needed = []; // product_id => qty, for tracked stock

        foreach ($lines as $l) {
            $pid = (int)($l['product_id'] ?? 0);
            $qty = (int)($l['qty'] ?? 0);
            if (!isset($products[$pid])) {
                throw new InvalidArgumentException('A product in the cart is no longer available.');
            }
            if ($qty < 1 || $qty > 999) {
                throw new InvalidArgumentException('Invalid quantity for ' . $products[$pid]['name'] . '.');
            }
            $p = $products[$pid];
            $price = (float)$p['price'];
            $cost = $p['recipe_cost'] !== null ? (float)$p['recipe_cost'] : (float)$p['cost'];
            $variantName = null;

            if ($p['variants']) {
                $vid = (int)($l['variant_id'] ?? 0);
                $variant = null;
                foreach ($p['variants'] as $v) {
                    if ((int)$v['id'] === $vid) {
                        $variant = $v;
                    }
                }
                if (!$variant) {
                    throw new InvalidArgumentException('Please choose an option for ' . $p['name'] . '.');
                }
                $price = (float)$variant['price'];
                if ((float)$variant['cost'] > 0) {
                    $cost = (float)$variant['cost'];
                }
                $variantName = $variant['name'];
            }

            $addonNames = [];
            $allowed = [];
            foreach ($p['addons'] as $a) {
                $allowed[(int)$a['id']] = $a;
            }
            foreach (array_unique(array_map('intval', (array)($l['addon_ids'] ?? []))) as $aid) {
                if (!isset($allowed[$aid])) {
                    throw new InvalidArgumentException('An add-on is not available for ' . $p['name'] . '.');
                }
                $price += (float)$allowed[$aid]['price'];
                $cost += (float)$allowed[$aid]['cost'];
                $addonNames[] = $allowed[$aid]['name'];
            }

            $lineTotal = self::money($price * $qty);
            $subtotal += $lineTotal;
            $costTotal += $cost * $qty;
            if ((int)$p['track_stock']) {
                $needed[$pid] = ($needed[$pid] ?? 0) + $qty;
            }
            $items[] = [
                'product_id'   => $pid,
                'product_name' => $p['name'],
                'variant_name' => $variantName,
                'addons_text'  => $addonNames ? implode(', ', $addonNames) : null,
                'unit_price'   => self::money($price),
                'unit_cost'    => round($cost, 2),
                'qty'          => $qty,
                'line_total'   => $lineTotal,
                'note'         => mb_substr(trim((string)($l['note'] ?? '')), 0, 255) ?: null,
            ];
        }

        if (!$allowNegative) {
            foreach ($needed as $pid => $q) {
                if ((float)$products[$pid]['stock_qty'] < $q) {
                    throw new InvalidArgumentException(sprintf('Only %s left in stock for %s.', qty($products[$pid]['stock_qty']), $products[$pid]['name']));
                }
            }
        }

        $subtotal = self::money($subtotal);

        // Order type
        $type = (string)($payload['order_type'] ?? 'takeaway');
        if (!in_array($type, ['dine_in', 'takeaway', 'delivery'], true)) {
            throw new InvalidArgumentException('Invalid order type.');
        }

        // Customer
        $customer = null;
        if (!empty($payload['customer_id'])) {
            $customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [(int)$payload['customer_id']]);
            if (!$customer) {
                throw new InvalidArgumentException('Selected customer was not found.');
            }
        }
        $address = trim((string)($payload['delivery_address'] ?? ''));
        if ($type === 'delivery') {
            if ($address === '' && $customer) {
                $address = (string)$customer['address'];
            }
            if ($address === '') {
                throw new InvalidArgumentException('A delivery address is required for delivery orders.');
            }
        }

        // Discount
        $discountType = in_array($payload['discount_type'] ?? '', ['percent', 'fixed'], true) ? $payload['discount_type'] : null;
        $discountValue = max(0.0, (float)($payload['discount_value'] ?? 0));
        $discount = 0.0;
        if ($discountType && $discountValue > 0) {
            if ($discountType === 'percent') {
                $discountValue = min($discountValue, 100);
                $discount = $subtotal * $discountValue / 100;
            } else {
                $discount = min($discountValue, $subtotal);
            }
        }
        $discount = self::money($discount);
        if ($user['role'] === 'cashier' && $subtotal > 0) {
            $maxPct = (float)setting('cashier_max_discount', 20);
            if ($discount / $subtotal * 100 > $maxPct + 0.001) {
                throw new InvalidArgumentException("Cashiers may give a discount of at most {$maxPct}%. Ask a manager.");
            }
        }

        // Loyalty redemption
        $pointsUsed = 0;
        $loyaltyDiscount = 0.0;
        $loyaltyOn = setting('loyalty_enabled', '1') === '1';
        $pointValue = max(0.01, (float)setting('loyalty_point_value', 1));
        $redeem = (int)($payload['redeem_points'] ?? 0);
        if ($redeem > 0) {
            if (!$loyaltyOn || !$customer) {
                throw new InvalidArgumentException('Select a customer to redeem loyalty points.');
            }
            if ($redeem > (int)$customer['loyalty_points']) {
                throw new InvalidArgumentException('The customer only has ' . (int)$customer['loyalty_points'] . ' points.');
            }
            $min = (int)setting('loyalty_min_redeem', 0);
            if ($redeem < $min) {
                throw new InvalidArgumentException("A minimum of $min points must be redeemed.");
            }
            $loyaltyDiscount = self::money(min($redeem * $pointValue, $subtotal - $discount));
            $pointsUsed = (int)ceil($loyaltyDiscount / $pointValue - 0.0001);
        }

        // Tax
        $taxRate = max(0.0, (float)setting('tax_rate', 0));
        $inclusive = setting('tax_inclusive', '0') === '1';
        $taxable = max(0.0, $subtotal - $discount - $loyaltyDiscount);
        if ($inclusive) {
            $tax = self::money($taxable - $taxable / (1 + $taxRate / 100));
            $afterTax = $taxable;
        } else {
            $tax = self::money($taxable * $taxRate / 100);
            $afterTax = $taxable + $tax;
        }

        $deliveryFee = $type === 'delivery' ? max(0.0, (float)($payload['delivery_fee'] ?? setting('delivery_fee', 0))) : 0.0;
        $total = self::money($afterTax + $deliveryFee);

        // Payment
        $method = (string)($payload['payment_method'] ?? 'cash');
        if (!in_array($method, ['cash', 'card', 'online'], true)) {
            throw new InvalidArgumentException('Invalid payment method.');
        }
        $tendered = $method === 'cash' ? self::money((float)($payload['tendered'] ?? $total)) : $total;
        if ($tendered + 0.001 < $total) {
            throw new InvalidArgumentException('Amount received is less than the order total.');
        }

        $pointsEarned = 0;
        $earnPer = (float)setting('loyalty_earn_amount', 100);
        if ($loyaltyOn && $customer && $earnPer > 0) {
            $pointsEarned = (int)floor(($total - $deliveryFee) / $earnPer);
        }

        return [
            'items'      => $items,
            'needed'     => $needed,
            'products'   => $products,
            'customer'   => $customer,
            'order' => [
                'customer_id'           => $customer['id'] ?? null,
                'order_type'            => $type,
                'table_no'              => $type === 'dine_in' ? (mb_substr(trim((string)($payload['table_no'] ?? '')), 0, 20) ?: null) : null,
                'delivery_address'      => $type === 'delivery' ? mb_substr($address, 0, 255) : null,
                'subtotal'              => $subtotal,
                'discount_type'         => $discount > 0 ? $discountType : null,
                'discount_value'        => $discount > 0 ? $discountValue : 0,
                'discount_amount'       => $discount,
                'loyalty_points_used'   => $pointsUsed,
                'loyalty_discount'      => $loyaltyDiscount,
                'loyalty_points_earned' => $pointsEarned,
                'tax_rate'              => $taxRate,
                'tax_inclusive'         => $inclusive ? 1 : 0,
                'tax_amount'            => $tax,
                'delivery_fee'          => self::money($deliveryFee),
                'total'                 => $total,
                'cost_total'            => round($costTotal, 2),
                'paid_amount'           => $total,
                'tendered'              => $tendered,
                'change_amount'         => self::money($tendered - $total),
                'payment_method'        => $method,
                'notes'                 => mb_substr(trim((string)($payload['notes'] ?? '')), 0, 500) ?: null,
            ],
        ];
    }

    public static function create(array $payload, array $user, ?array $register): array
    {
        $priced = self::price($payload, $user);
        $db = Database::instance();

        return $db->transaction(function (Database $db) use ($priced, $user, $register, $payload) {
            $now = date('Y-m-d H:i:s');
            $order = $priced['order'] + [
                'order_no'    => 'TMP-' . bin2hex(random_bytes(8)),
                'user_id'     => (int)$user['id'],
                'register_id' => $register['id'] ?? null,
                'created_at'  => $now,
            ];
            $orderId = $db->insert('orders', $order);
            $orderNo = setting('order_prefix', 'ORD-') . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
            $db->update('orders', ['order_no' => $orderNo], 'id = ?', [$orderId]);

            foreach ($priced['items'] as $item) {
                $db->insert('order_items', $item + ['order_id' => $orderId]);
            }

            $db->insert('payments', [
                'order_id'    => $orderId,
                'register_id' => $register['id'] ?? null,
                'method'      => $order['payment_method'],
                'amount'      => $order['total'],
                'reference'   => mb_substr(trim((string)($payload['payment_ref'] ?? '')), 0, 100) ?: null,
                'created_at'  => $now,
            ]);

            // Stock: tracked products, then recipe ingredients
            foreach ($priced['needed'] as $pid => $q) {
                Stock::move('product', $pid, -$q, 'sale', $orderNo);
            }
            $qtyByProduct = [];
            foreach ($priced['items'] as $item) {
                $qtyByProduct[$item['product_id']] = ($qtyByProduct[$item['product_id']] ?? 0) + $item['qty'];
            }
            $in = implode(',', array_map('intval', array_keys($qtyByProduct)));
            $ingredientUse = [];
            foreach ($db->fetchAll("SELECT product_id, ingredient_id, qty FROM product_ingredients WHERE product_id IN ($in)") as $r) {
                $ingredientUse[(int)$r['ingredient_id']] = ($ingredientUse[(int)$r['ingredient_id']] ?? 0) + (float)$r['qty'] * $qtyByProduct[(int)$r['product_id']];
            }
            foreach ($ingredientUse as $iid => $q) {
                Stock::move('ingredient', $iid, -$q, 'sale', $orderNo);
            }

            if ($priced['customer']) {
                $db->query(
                    'UPDATE customers SET loyalty_points = GREATEST(0, loyalty_points - ? + ?), total_spent = total_spent + ? WHERE id = ?',
                    [$order['loyalty_points_used'], $order['loyalty_points_earned'], $order['total'], $priced['customer']['id']]
                );
            }

            audit('order_created', 'order', $orderId, $orderNo . ' · ' . money($order['total']) . ' · ' . payment_label($order['payment_method']));

            return ['id' => $orderId, 'order_no' => $orderNo] + $order;
        });
    }

    /**
     * Refunds selected quantities. $lines = [order_item_id => qty].
     */
    public static function refund(array $order, array $lines, string $method, bool $restock, string $reason, array $user, ?array $register): array
    {
        $db = Database::instance();
        if (!in_array($method, ['cash', 'card', 'online'], true)) {
            throw new InvalidArgumentException('Invalid refund method.');
        }
        if ($order['status'] === 'refunded') {
            throw new InvalidArgumentException('This order has already been fully refunded.');
        }

        return $db->transaction(function (Database $db) use ($order, $lines, $method, $restock, $reason, $user, $register) {
            $items = $db->fetchAll('SELECT oi.*, p.track_stock FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? FOR UPDATE', [$order['id']]);
            $ratio = (float)$order['subtotal'] > 0 ? ((float)$order['total'] - (float)$order['delivery_fee']) / (float)$order['subtotal'] : 0;

            $amount = 0.0;
            $costReversed = 0.0;
            $selected = [];
            foreach ($items as $it) {
                $q = (int)($lines[$it['id']] ?? 0);
                if ($q <= 0) {
                    continue;
                }
                $available = (int)$it['qty'] - (int)$it['returned_qty'];
                if ($q > $available) {
                    throw new InvalidArgumentException("Only $available of {$it['product_name']} can be refunded.");
                }
                $lineAmount = self::money((float)$it['unit_price'] * $q * $ratio);
                $amount += $lineAmount;
                $selected[] = [$it, $q, $lineAmount];
            }
            if (!$selected) {
                throw new InvalidArgumentException('Select at least one item to refund.');
            }

            $remainingAfter = 0;
            foreach ($items as $it) {
                $remainingAfter += (int)$it['qty'] - (int)$it['returned_qty'] - (int)($lines[$it['id']] ?? 0);
            }
            $alreadyRefunded = (float)$order['refunded_amount'];
            if ($remainingAfter === 0) {
                // Final refund closes out the order exactly, including any delivery fee.
                $amount = (float)$order['total'] - $alreadyRefunded;
            }
            $amount = self::money(min($amount, (float)$order['total'] - $alreadyRefunded));
            $tax = (float)$order['total'] > 0 ? self::money($amount * (float)$order['tax_amount'] / (float)$order['total']) : 0;
            $now = date('Y-m-d H:i:s');

            $returnId = $db->insert('returns', [
                'order_id'      => $order['id'],
                'user_id'       => $user['id'],
                'register_id'   => $register['id'] ?? null,
                'amount'        => $amount,
                'tax_amount'    => $tax,
                'cost_reversed' => 0,
                'refund_method' => $method,
                'restocked'     => $restock ? 1 : 0,
                'reason'        => mb_substr($reason, 0, 255) ?: null,
                'created_at'    => $now,
            ]);
            foreach ($selected as [$it, $q, $lineAmount]) {
                $db->insert('return_items', ['return_id' => $returnId, 'order_item_id' => $it['id'], 'qty' => $q, 'amount' => $lineAmount]);
                $db->query('UPDATE order_items SET returned_qty = returned_qty + ? WHERE id = ?', [$q, $it['id']]);
                if ($restock && $it['product_id'] && (int)$it['track_stock']) {
                    Stock::move('product', (int)$it['product_id'], $q, 'return', $order['order_no']);
                    $costReversed += (float)$it['unit_cost'] * $q;
                }
            }
            $db->update('returns', ['cost_reversed' => self::money($costReversed)], 'id = ?', [$returnId]);

            $newRefunded = self::money($alreadyRefunded + $amount);
            $status = $remainingAfter === 0 ? 'refunded' : 'partially_refunded';
            $db->update('orders', ['refunded_amount' => $newRefunded, 'status' => $status], 'id = ?', [$order['id']]);

            if ($order['customer_id']) {
                $pointsBack = (float)$order['total'] > 0 ? (int)floor((int)$order['loyalty_points_earned'] * $amount / (float)$order['total']) : 0;
                $db->query(
                    'UPDATE customers SET total_spent = GREATEST(0, total_spent - ?), loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?',
                    [$amount, $pointsBack, $order['customer_id']]
                );
            }

            audit('order_refunded', 'order', (int)$order['id'], $order['order_no'] . ' · ' . money($amount) . ' via ' . payment_label($method) . ($reason ? " · $reason" : ''));
            return ['return_id' => $returnId, 'amount' => $amount, 'status' => $status];
        });
    }
}
