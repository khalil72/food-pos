<?php
declare(strict_types=1);

/**
 * Populates a fresh database with a realistic fast-food menu and 30 days of trading,
 * using the real OrderService so every figure follows the same rules as live sales.
 */
final class Seeder
{
    private Database $db;
    private array $users = [];
    private array $ingredients = [];
    private array $products = [];
    private array $customers = [];

    public function __construct(private int $adminId)
    {
        $this->db = Database::instance();
        mt_srand(20241027);
    }

    public function run(): void
    {
        // One transaction keeps seeding fast (no per-row commit) and all-or-nothing.
        $this->db->transaction(function () {
            $this->users();
            $suppliers = $this->suppliers();
            $this->ingredients($suppliers);
            $addons = $this->addons();
            $this->menu($addons);
            $this->customers();
            $this->openingPurchases($suppliers);
            $this->trading();
            $this->lowStockDemo();
            $this->db->query("DELETE FROM audit_logs WHERE action IN ('order_created','order_refunded')");
        });
    }

    private function users(): void
    {
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $this->users['admin'] = $this->adminId;
        foreach ([['Sana Malik', 'manager', 'manager'], ['Ahmed Raza', 'cashier', 'cashier'], ['Hina Tariq', 'hina', 'cashier']] as [$name, $username, $role]) {
            $this->users[$username] = $this->db->insert('users', [
                'name' => $name, 'username' => $username, 'email' => $username . '@example.com',
                'phone' => '0300' . mt_rand(1000000, 9999999), 'password_hash' => $hash, 'role' => $role,
            ]);
        }
    }

    private function suppliers(): array
    {
        $out = [];
        foreach ([
            ['City Wholesale Mart', 'Imran Bashir', '042-35761234', 'orders@citywholesale.example', 'Badami Bagh, Lahore'],
            ['Prime Meat Suppliers', 'Kashif Nawaz', '0321-4455667', 'sales@primemeat.example', 'Shadman Market, Lahore'],
            ['FreshFizz Beverages', 'Adeel Saeed', '0333-9988776', 'trade@freshfizz.example', 'Kot Lakhpat Industrial Area'],
            ['PackRight Packaging', 'Rizwan Ali', '0345-1122334', 'hello@packright.example', 'Sundar Industrial Estate'],
            ['Green Valley Produce', 'Nadia Hussain', '0301-5566778', 'fresh@greenvalley.example', 'Sabzi Mandi, Ravi Road'],
        ] as $i => [$n, $c, $p, $e, $a]) {
            $out[$i] = $this->db->insert('suppliers', ['name' => $n, 'contact_person' => $c, 'phone' => $p, 'email' => $e, 'address' => $a, 'notes' => 'Delivers Mon/Wed/Sat. Payment within 15 days.']);
        }
        return $out;
    }

    private function ingredients(array $s): void
    {
        foreach ([
            // name, unit, cost/unit, reorder, supplier
            ['Burger Bun', 'pcs', 25, 150, 0], ['Beef Patty', 'pcs', 120, 80, 1], ['Chicken Fillet', 'pcs', 95, 80, 1],
            ['Cheese Slice', 'pcs', 18, 150, 0], ['Lettuce', 'kg', 300, 3, 4], ['Tomato', 'kg', 160, 4, 4],
            ['Potato', 'kg', 90, 25, 4], ['Cooking Oil', 'L', 520, 15, 0], ['Pizza Dough Ball', 'pcs', 70, 40, 0],
            ['Mozzarella', 'kg', 1800, 6, 0], ['Chicken (boneless)', 'kg', 780, 10, 1], ['Chicken Wings', 'kg', 620, 8, 1],
            ['Tortilla / Paratha', 'pcs', 22, 80, 0], ['Burger Sauce', 'kg', 650, 3, 0], ['Flour & Batter Mix', 'kg', 160, 10, 0],
            ['Food Box', 'pcs', 14, 200, 3], ['Paper Cup', 'pcs', 9, 200, 3], ['Milk', 'L', 220, 8, 4], ['Ice Cream Mix', 'L', 480, 4, 0],
        ] as [$name, $unit, $cost, $low, $sup]) {
            $this->ingredients[$name] = $this->db->insert('ingredients', ['name' => $name, 'unit' => $unit, 'cost_per_unit' => $cost, 'low_stock_level' => $low, 'supplier_id' => $s[$sup]]);
        }
    }

    private function addons(): array
    {
        $out = [];
        foreach ([['Extra Cheese', 80, 22], ['Jalapeños', 50, 12], ['Extra Patty', 250, 120], ['Fried Egg', 70, 25], ['Caramelised Onions', 60, 15],
                     ['Garlic Mayo Dip', 60, 15], ['Stuffed Crust', 200, 70], ['Black Olives', 70, 25], ['Mushrooms', 80, 30], ['Extra Chocolate', 70, 20]] as [$n, $p, $c]) {
            $out[$n] = $this->db->insert('addons', ['name' => $n, 'price' => $p, 'cost' => $c]);
        }
        return $out;
    }

    private function image(string $slug, string $emoji, string $color): string
    {
        $dir = UPLOAD_PATH . '/products';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $light = $this->mix($color, 0.55);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
  <defs>
    <radialGradient id="bg" cx="30%" cy="20%" r="90%"><stop offset="0" stop-color="$light"/><stop offset="1" stop-color="$color"/></radialGradient>
    <radialGradient id="plate" cx="50%" cy="45%" r="55%"><stop offset="0" stop-color="#ffffff" stop-opacity=".55"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/></radialGradient>
    <filter id="sh" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#000" flood-opacity=".28"/></filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg)"/>
  <circle cx="340" cy="40" r="70" fill="#fff" opacity=".08"/><circle cx="40" cy="270" r="90" fill="#fff" opacity=".07"/>
  <ellipse cx="200" cy="162" rx="128" ry="118" fill="url(#plate)"/>
  <text x="200" y="160" font-size="140" text-anchor="middle" dominant-baseline="central" filter="url(#sh)" font-family="Segoe UI Emoji, Apple Color Emoji, Noto Color Emoji, sans-serif">$emoji</text>
</svg>
SVG;
        file_put_contents("$dir/$slug.svg", $svg);
        return "products/$slug.svg";
    }

    private function mix(string $hex, float $w): string
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        return sprintf('#%02X%02X%02X', (int)($r + (255 - $r) * $w), (int)($g + (255 - $g) * $w), (int)($b + (255 - $b) * $w));
    }

    private function menu(array $a): void
    {
        $i = $this->ingredients;
        $cats = [
            'Burgers'        => ['#E8572A', 'bi-fire'],
            'Pizza'          => ['#C2410C', 'bi-star'],
            'Fried Chicken'  => ['#B45309', 'bi-egg-fried'],
            'Wraps & Rolls'  => ['#15803D', 'bi-basket'],
            'Sides'          => ['#CA8A04', 'bi-sun'],
            'Drinks'         => ['#2563EB', 'bi-cup-straw'],
            'Desserts'       => ['#DB2777', 'bi-cake2'],
            'Deals'          => ['#7C3AED', 'bi-gift'],
        ];
        $catIds = [];
        $sort = 0;
        foreach ($cats as $name => [$color, $icon]) {
            $catIds[$name] = $this->db->insert('categories', ['name' => $name, 'color' => $color, 'icon' => $icon, 'sort_order' => $sort++]);
        }
        $burgerAddons = [$a['Extra Cheese'], $a['Jalapeños'], $a['Extra Patty'], $a['Fried Egg'], $a['Caramelised Onions']];
        $pizzaAddons = [$a['Extra Cheese'], $a['Stuffed Crust'], $a['Black Olives'], $a['Mushrooms'], $a['Jalapeños']];
        $dip = [$a['Garlic Mayo Dip']];

        // name, category, price, cost, emoji, popularity, extra
        $menu = [
            ['Classic Beef Burger', 'Burgers', 650, 0, '🍔', 9, ['addons' => $burgerAddons, 'recipe' => ['Burger Bun' => 1, 'Beef Patty' => 1, 'Cheese Slice' => 1, 'Lettuce' => .02, 'Tomato' => .03, 'Burger Sauce' => .03, 'Food Box' => 1], 'desc' => 'Flame-grilled beef, cheddar, lettuce, tomato & house sauce.']],
            ['Zinger Burger', 'Burgers', 550, 0, '🍔', 12, ['variants' => [['Regular', 550, 0], ['Jumbo', 750, 0]], 'addons' => $burgerAddons, 'recipe' => ['Burger Bun' => 1, 'Chicken Fillet' => 1, 'Lettuce' => .02, 'Burger Sauce' => .03, 'Cooking Oil' => .05, 'Flour & Batter Mix' => .05, 'Food Box' => 1], 'desc' => 'Crispy spicy chicken fillet with mayo and lettuce.']],
            ['Double Smash Burger', 'Burgers', 950, 0, '🍔', 6, ['addons' => $burgerAddons, 'recipe' => ['Burger Bun' => 1, 'Beef Patty' => 2, 'Cheese Slice' => 2, 'Burger Sauce' => .04, 'Food Box' => 1], 'desc' => 'Two smashed patties, double cheese, pickles.']],
            ['Chicken Tikka Burger', 'Burgers', 600, 0, '🍔', 5, ['addons' => $burgerAddons, 'recipe' => ['Burger Bun' => 1, 'Chicken (boneless)' => .15, 'Lettuce' => .02, 'Burger Sauce' => .03, 'Food Box' => 1]]],
            ['Veggie Crunch Burger', 'Burgers', 450, 160, '🥬', 2, ['addons' => $burgerAddons]],
            ['Margherita Pizza', 'Pizza', 799, 0, '🍕', 4, ['variants' => [['Small 7"', 799, 260], ['Medium 10"', 1199, 380], ['Large 13"', 1599, 520]], 'addons' => $pizzaAddons, 'recipe' => ['Pizza Dough Ball' => 1, 'Mozzarella' => .12, 'Food Box' => 1]]],
            ['Chicken Tikka Pizza', 'Pizza', 899, 0, '🍕', 7, ['variants' => [['Small 7"', 899, 310], ['Medium 10"', 1399, 460], ['Large 13"', 1899, 640]], 'addons' => $pizzaAddons, 'recipe' => ['Pizza Dough Ball' => 1, 'Mozzarella' => .12, 'Chicken (boneless)' => .1, 'Food Box' => 1]]],
            ['Pepperoni Pizza', 'Pizza', 949, 0, '🍕', 5, ['variants' => [['Small 7"', 949, 330], ['Medium 10"', 1449, 490], ['Large 13"', 1999, 680]], 'addons' => $pizzaAddons, 'recipe' => ['Pizza Dough Ball' => 1, 'Mozzarella' => .12, 'Food Box' => 1]]],
            ['BBQ Chicken Pizza', 'Pizza', 899, 0, '🍕', 4, ['variants' => [['Small 7"', 899, 310], ['Medium 10"', 1399, 460], ['Large 13"', 1899, 640]], 'addons' => $pizzaAddons, 'recipe' => ['Pizza Dough Ball' => 1, 'Mozzarella' => .1, 'Chicken (boneless)' => .1, 'Food Box' => 1]]],
            ['Hot Wings (6 pcs)', 'Fried Chicken', 550, 0, '🍗', 7, ['addons' => $dip, 'recipe' => ['Chicken Wings' => .35, 'Cooking Oil' => .06, 'Flour & Batter Mix' => .05, 'Food Box' => 1]]],
            ['Crispy Chicken (2 pcs)', 'Fried Chicken', 520, 0, '🍗', 6, ['addons' => $dip, 'recipe' => ['Chicken (boneless)' => .3, 'Cooking Oil' => .06, 'Flour & Batter Mix' => .06, 'Food Box' => 1]]],
            ['Chicken Strips (5 pcs)', 'Fried Chicken', 600, 0, '🍗', 4, ['addons' => $dip, 'recipe' => ['Chicken (boneless)' => .25, 'Cooking Oil' => .05, 'Flour & Batter Mix' => .05, 'Food Box' => 1]]],
            ['Family Bucket (9 pcs)', 'Fried Chicken', 2499, 0, '🍗', 2, ['addons' => $dip, 'recipe' => ['Chicken (boneless)' => 1.3, 'Cooking Oil' => .25, 'Flour & Batter Mix' => .25, 'Food Box' => 1]]],
            ['Chicken Shawarma', 'Wraps & Rolls', 350, 0, '🥙', 8, ['addons' => [$a['Extra Cheese'], $a['Jalapeños']], 'recipe' => ['Tortilla / Paratha' => 1, 'Chicken (boneless)' => .12, 'Burger Sauce' => .03, 'Lettuce' => .02]]],
            ['Zinger Wrap', 'Wraps & Rolls', 480, 0, '🌯', 5, ['addons' => [$a['Extra Cheese'], $a['Jalapeños']], 'recipe' => ['Tortilla / Paratha' => 1, 'Chicken Fillet' => 1, 'Burger Sauce' => .03, 'Cooking Oil' => .04]]],
            ['Chicken Paratha Roll', 'Wraps & Rolls', 320, 0, '🌯', 5, ['recipe' => ['Tortilla / Paratha' => 1, 'Chicken (boneless)' => .1, 'Burger Sauce' => .02]]],
            ['French Fries', 'Sides', 250, 0, '🍟', 14, ['variants' => [['Regular', 250, 45], ['Large', 380, 70]], 'addons' => [$a['Garlic Mayo Dip']], 'recipe' => ['Potato' => .25, 'Cooking Oil' => .04]]],
            ['Loaded Fries', 'Sides', 550, 0, '🍟', 6, ['addons' => [$a['Jalapeños'], $a['Extra Cheese']], 'recipe' => ['Potato' => .3, 'Cooking Oil' => .05, 'Mozzarella' => .05, 'Chicken (boneless)' => .05, 'Food Box' => 1]]],
            ['Onion Rings', 'Sides', 350, 90, '🧅', 3, ['addons' => $dip]],
            ['Chicken Nuggets (6 pcs)', 'Sides', 420, 150, '🍗', 5, ['addons' => $dip]],
            ['Coleslaw', 'Sides', 150, 40, '🥗', 2, []],
            ['Coca-Cola 345ml', 'Drinks', 150, 85, '🥤', 12, ['track' => 1, 'stock' => 0, 'low' => 48, 'barcode' => '5449000000996', 'sku' => 'DRK-COKE']],
            ['Sprite 345ml', 'Drinks', 150, 85, '🥤', 7, ['track' => 1, 'stock' => 0, 'low' => 48, 'barcode' => '5449000014535', 'sku' => 'DRK-SPRITE']],
            ['Mineral Water 500ml', 'Drinks', 90, 45, '💧', 8, ['track' => 1, 'stock' => 0, 'low' => 48, 'barcode' => '8964000101011', 'sku' => 'DRK-WATER']],
            ['Fresh Lime', 'Drinks', 220, 0, '🍋', 4, ['recipe' => ['Paper Cup' => 1]]],
            ['Mint Margarita', 'Drinks', 350, 0, '🍹', 6, ['recipe' => ['Paper Cup' => 1]]],
            ['Cold Coffee', 'Drinks', 450, 0, '🧋', 4, ['variants' => [['Regular', 450, 0], ['Large', 600, 0]], 'addons' => [$a['Extra Chocolate']], 'recipe' => ['Milk' => .25, 'Ice Cream Mix' => .05, 'Paper Cup' => 1]]],
            ['Chocolate Brownie', 'Desserts', 380, 120, '🍫', 4, ['addons' => [$a['Extra Chocolate']]]],
            ['Molten Lava Cake', 'Desserts', 520, 170, '🍰', 3, []],
            ['Soft Serve Cone', 'Desserts', 150, 0, '🍦', 6, ['recipe' => ['Ice Cream Mix' => .08]]],
            ['Oreo Shake', 'Desserts', 550, 0, '🥤', 5, ['variants' => [['Regular', 550, 0], ['Large', 720, 0]], 'addons' => [$a['Extra Chocolate']], 'recipe' => ['Milk' => .3, 'Ice Cream Mix' => .1, 'Paper Cup' => 1]]],
            ['Zinger Combo', 'Deals', 899, 0, '🍱', 9, ['desc' => 'Zinger burger + regular fries + soft drink', 'recipe' => ['Burger Bun' => 1, 'Chicken Fillet' => 1, 'Potato' => .25, 'Cooking Oil' => .09, 'Flour & Batter Mix' => .05, 'Paper Cup' => 1, 'Food Box' => 1]]],
            ['Student Deal', 'Deals', 599, 0, '🎒', 6, ['desc' => 'Chicken shawarma + fries + drink', 'recipe' => ['Tortilla / Paratha' => 1, 'Chicken (boneless)' => .12, 'Potato' => .2, 'Cooking Oil' => .04, 'Paper Cup' => 1]]],
            ['Family Feast', 'Deals', 3499, 0, '🎉', 2, ['desc' => '2 large pizzas, 6 wings, large fries & 1.5L drink', 'recipe' => ['Pizza Dough Ball' => 2, 'Mozzarella' => .25, 'Chicken Wings' => .35, 'Potato' => .4, 'Cooking Oil' => .12, 'Food Box' => 3]]],
        ];

        $n = 1;
        foreach ($menu as [$name, $cat, $price, $cost, $emoji, $pop, $x]) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
            $id = $this->db->insert('products', [
                'category_id'     => $catIds[$cat],
                'name'            => $name,
                'sku'             => $x['sku'] ?? sprintf('%s-%03d', strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 3)), $n++),
                'barcode'         => $x['barcode'] ?? null,
                'price'           => $price,
                'cost'            => $cost,
                'image'           => $this->image($slug, $emoji, $cats[$cat][0]),
                'description'     => $x['desc'] ?? null,
                'track_stock'     => $x['track'] ?? 0,
                'stock_qty'       => 0,
                'low_stock_level' => $x['low'] ?? 0,
            ]);
            foreach ($x['variants'] ?? [] as $s => [$vn, $vp, $vc]) {
                $this->db->insert('product_variants', ['product_id' => $id, 'name' => $vn, 'price' => $vp, 'cost' => $vc, 'sort_order' => $s]);
            }
            foreach ($x['addons'] ?? [] as $aid) {
                $this->db->insert('product_addons', ['product_id' => $id, 'addon_id' => $aid]);
            }
            foreach ($x['recipe'] ?? [] as $ing => $q) {
                $this->db->insert('product_ingredients', ['product_id' => $id, 'ingredient_id' => $this->ingredients[$ing], 'qty' => $q]);
            }
            $this->products[] = ['id' => $id, 'pop' => $pop, 'track' => (int)($x['track'] ?? 0)];
        }
    }

    private function customers(): void
    {
        $names = ['Ali Raza', 'Ayesha Khan', 'Bilal Ahmed', 'Fatima Noor', 'Hamza Malik', 'Hira Shah', 'Usman Tariq', 'Zainab Iqbal', 'Omar Farooq', 'Sana Javed',
            'Ahmed Hassan', 'Mariam Siddiqui', 'Danish Qureshi', 'Noor Fatima', 'Saad Akhtar', 'Iqra Aslam', 'Faisal Mehmood', 'Rabia Anwar', 'Kamran Yousaf', 'Mehwish Ali',
            'Taha Rehman', 'Areeba Zafar', 'Shahzaib Khan', 'Laiba Hussain', 'Hassan Javaid'];
        $areas = ['DHA Phase 5', 'Gulberg III', 'Model Town', 'Johar Town', 'Bahria Town', 'Garden Town', 'Cantt', 'Faisal Town', 'Wapda Town'];
        foreach ($names as $k => $n) {
            $this->customers[] = $this->db->insert('customers', [
                'name'    => $n,
                'phone'   => sprintf('03%02d%07d', mt_rand(0, 49), 1000000 + $k * 37111),
                'email'   => strtolower(str_replace(' ', '.', $n)) . '@mail.example',
                'address' => sprintf('House %d, Street %d, %s, Lahore', mt_rand(1, 400), mt_rand(1, 40), $areas[$k % count($areas)]),
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(31, 200) . ' days')),
            ]);
        }
    }

    private function purchase(int $supplierId, string $date, array $lines, float $paidRatio): void
    {
        $total = 0;
        foreach ($lines as &$l) {
            $l['line_total'] = round($l['qty'] * $l['unit_cost'], 2);
            $total += $l['line_total'];
        }
        unset($l);
        $id = $this->db->insert('purchases', [
            'supplier_id' => $supplierId, 'user_id' => $this->users['manager'], 'reference' => 'INV-' . mt_rand(10000, 99999),
            'purchase_date' => substr($date, 0, 10), 'total' => $total, 'paid' => round($total * $paidRatio, 2), 'created_at' => $date,
        ]);
        foreach ($lines as $l) {
            $this->db->insert('purchase_items', $l + ['purchase_id' => $id]);
            Stock::move($l['item_type'], $l['item_id'], $l['qty'], 'purchase', 'PUR-' . $id);
        }
        $this->db->query("UPDATE stock_movements SET created_at = ?, user_id = ? WHERE reference = ?", [$date, $this->users['manager'], 'PUR-' . $id]);
    }

    private function openingPurchases(array $s): void
    {
        $ing = fn(string $n, float $q) => ['item_type' => 'ingredient', 'item_id' => $this->ingredients[$n], 'item_name' => $n, 'qty' => $q,
            'unit_cost' => (float)$this->db->value('SELECT cost_per_unit FROM ingredients WHERE id = ?', [$this->ingredients[$n]])];
        $prod = function (string $n, float $q, float $c) {
            $id = (int)$this->db->value('SELECT id FROM products WHERE name = ?', [$n]);
            return ['item_type' => 'product', 'item_id' => $id, 'item_name' => $n, 'qty' => $q, 'unit_cost' => $c];
        };
        foreach ([31, 16] as $ago) {
            $d = date('Y-m-d 09:30:00', strtotime("-$ago days"));
            $this->purchase($s[0], $d, [$ing('Burger Bun', 2200), $ing('Cheese Slice', 1500), $ing('Cooking Oil', 220), $ing('Pizza Dough Ball', 700), $ing('Mozzarella', 120),
                $ing('Tortilla / Paratha', 1100), $ing('Burger Sauce', 70), $ing('Flour & Batter Mix', 170), $ing('Ice Cream Mix', 45)], $ago > 20 ? 1 : 0.6);
            $this->purchase($s[1], date('Y-m-d 10:15:00', strtotime("-$ago days")), [$ing('Beef Patty', 900), $ing('Chicken Fillet', 1500), $ing('Chicken (boneless)', 380), $ing('Chicken Wings', 75)], 1);
            $this->purchase($s[4], date('Y-m-d 08:45:00', strtotime("-$ago days")), [$ing('Lettuce', 40), $ing('Tomato', 35), $ing('Potato', 620), $ing('Milk', 120)], 1);
            $this->purchase($s[3], date('Y-m-d 11:00:00', strtotime("-$ago days")), [$ing('Food Box', 3200), $ing('Paper Cup', 2200)], $ago > 20 ? 1 : 0);
            $this->purchase($s[2], date('Y-m-d 12:00:00', strtotime("-$ago days")), [$prod('Coca-Cola 345ml', 480, 85), $prod('Sprite 345ml', 288, 85), $prod('Mineral Water 500ml', 288, 45)], $ago > 20 ? 1 : 0.5);
        }
    }

    private function pick(array $weighted): mixed
    {
        $total = array_sum(array_column($weighted, 1));
        $r = mt_rand(1, (int)$total);
        foreach ($weighted as [$v, $w]) {
            if (($r -= $w) <= 0) {
                return $v;
            }
        }
        return $weighted[0][0];
    }

    private function trading(): void
    {
        $catalog = Catalog::products();
        $weighted = array_map(fn($p) => [$p['id'], $p['pop']], $this->products);
        $cashiers = [$this->users['cashier'], $this->users['hina']];
        $userRows = [];
        foreach ($this->db->fetchAll('SELECT * FROM users') as $u) {
            $userRows[(int)$u['id']] = $u;
        }
        $hourWeights = [[12, 5], [13, 9], [14, 8], [15, 4], [16, 3], [17, 4], [18, 6], [19, 9], [20, 11], [21, 11], [22, 8], [23, 5]];
        $expCats = [];
        foreach ($this->db->fetchAll('SELECT id, name FROM expense_categories') as $c) {
            $expCats[$c['name']] = (int)$c['id'];
        }

        for ($ago = 29; $ago >= 0; $ago--) {
            $day = date('Y-m-d', strtotime("-$ago days"));
            $isToday = $ago === 0;
            $nowHour = (int)date('G');
            $dow = (int)date('N', strtotime($day));
            $base = mt_rand(38, 58) + ($dow >= 5 ? 18 : 0);
            if ($isToday) {
                $base = (int)round($base * max(0.15, min(1, ($nowHour - 11) / 13)));
            }
            $cashierId = $cashiers[$ago % 2];
            $cashier = $userRows[$cashierId];
            $openAt = "$day 11:45:00";
            $regId = $this->db->insert('cash_registers', ['user_id' => $cashierId, 'opened_at' => $openAt, 'opening_cash' => 5000, 'status' => 'open']);
            $register = ['id' => $regId];

            $times = [];
            for ($k = 0; $k < $base; $k++) {
                $h = $this->pick($hourWeights);
                if ($isToday && $h >= $nowHour) {
                    $h = max(12, $nowHour - 1 - mt_rand(0, 2));
                    if ($h >= $nowHour) {
                        continue;
                    }
                }
                $times[] = sprintf('%s %02d:%02d:%02d', $day, $h, mt_rand(0, 59), mt_rand(0, 59));
            }
            sort($times);

            foreach ($times as $ts) {
                $lines = [];
                $nLines = $this->pick([[1, 35], [2, 35], [3, 20], [4, 10]]);
                for ($l = 0; $l < $nLines; $l++) {
                    $pid = $this->pick($weighted);
                    $p = $catalog[$pid];
                    $line = ['product_id' => $pid, 'qty' => $this->pick([[1, 75], [2, 20], [3, 5]]), 'addon_ids' => []];
                    if ($p['variants']) {
                        $line['variant_id'] = (int)$p['variants'][$this->pick(array_map(fn($i) => [$i, $i === 0 ? 5 : 3], array_keys($p['variants'])))]['id'];
                    }
                    if ($p['addons'] && mt_rand(1, 100) <= 25) {
                        $line['addon_ids'][] = (int)$p['addons'][array_rand($p['addons'])]['id'];
                    }
                    if (mt_rand(1, 100) <= 6) {
                        $line['note'] = ['No onions', 'Extra spicy', 'Less ice', 'Sauce on the side', 'Well done'][mt_rand(0, 4)];
                    }
                    $lines[] = $line;
                }
                $type = $this->pick([['takeaway', 45], ['dine_in', 35], ['delivery', 20]]);
                $customerId = ($type === 'delivery' || mt_rand(1, 100) <= 35) ? $this->customers[array_rand($this->customers)] : null;
                $method = $this->pick([['cash', 58], ['card', 27], ['online', 15]]);
                $payload = [
                    'items' => $lines, 'order_type' => $type, 'customer_id' => $customerId,
                    'table_no' => $type === 'dine_in' ? (string)mt_rand(1, 18) : null,
                    'delivery_fee' => 150, 'payment_method' => $method,
                ];
                if (mt_rand(1, 100) <= 7) {
                    $payload['discount_type'] = 'percent';
                    $payload['discount_value'] = [5, 10, 15][mt_rand(0, 2)];
                }
                if ($customerId && mt_rand(1, 100) <= 15) {
                    $pts = (int)$this->db->value('SELECT loyalty_points FROM customers WHERE id = ?', [$customerId]);
                    if ($pts >= 50) {
                        $payload['redeem_points'] = min($pts, mt_rand(50, 150));
                    }
                }
                try {
                    $priced = OrderService::price($payload, $cashier);
                    if ($method === 'cash') {
                        $t = $priced['order']['total'];
                        $payload['tendered'] = $this->pick([[$t, 40], [ceil($t / 100) * 100, 30], [ceil($t / 500) * 500, 20], [ceil($t / 1000) * 1000, 10]]);
                    }
                    $order = OrderService::create($payload, $cashier, $register);
                } catch (InvalidArgumentException) {
                    continue; // e.g. a drink ran out — skip like a real cashier would
                }
                $this->db->update('orders', ['created_at' => $ts], 'id = ?', [$order['id']]);
                $this->db->update('payments', ['created_at' => $ts], 'order_id = ?', [$order['id']]);
                $this->db->query('UPDATE stock_movements SET created_at = ?, user_id = ? WHERE reference = ?', [$ts, $cashierId, $order['order_no']]);

                if (!$isToday && mt_rand(1, 1000) <= 12) {
                    $full = $this->db->fetch('SELECT * FROM orders WHERE id = ?', [$order['id']]);
                    $item = $this->db->fetch('SELECT id FROM order_items WHERE order_id = ? LIMIT 1', [$order['id']]);
                    $refundAt = date('Y-m-d H:i:s', strtotime($ts) + mt_rand(300, 2400));
                    $manager = $userRows[$this->users['manager']];
                    $r = OrderService::refund($full, [$item['id'] => 1], $full['payment_method'], false, ['Wrong item served', 'Customer complaint – cold food', 'Order cancelled', 'Long wait time'][mt_rand(0, 3)], $manager, $full['payment_method'] === 'cash' ? $register : null);
                    $this->db->update('returns', ['created_at' => $refundAt], 'id = ?', [$r['return_id']]);
                }
            }

            // Cash movements & close
            if (mt_rand(1, 100) <= 40) {
                $this->db->insert('cash_movements', ['register_id' => $regId, 'user_id' => $cashierId, 'type' => 'out', 'amount' => 20000, 'reason' => 'Mid-shift bank deposit', 'created_at' => "$day 18:00:00"]);
            }
            if (!$isToday) {
                $reg = $this->db->fetch('SELECT * FROM cash_registers WHERE id = ?', [$regId]);
                $exp = Register::summary($reg)['expected_cash'];
                $diff = $this->pick([[0, 70], [-50, 10], [-100, 6], [50, 8], [-20, 6]]);
                $this->db->update('cash_registers', [
                    'closed_at' => "$day 23:55:00", 'closing_cash' => $exp + $diff, 'expected_cash' => $exp, 'difference' => $diff,
                    'status' => 'closed', 'notes' => $diff < 0 ? 'Small shortage' : null,
                ], 'id = ?', [$regId]);
            }

            // Expenses
            $exp = [];
            if ((int)date('j', strtotime($day)) === 1) {
                $exp[] = ['Rent', 185000, 'Monthly shop rent', 'bank'];
                $exp[] = ['Salaries & Wages', 312000, 'Staff salaries (8 staff)', 'bank'];
            }
            if ($ago % 10 === 3) {
                $exp[] = ['Utilities', mt_rand(38000, 52000), 'Electricity bill (LESCO)', 'online'];
            }
            if ($ago === 20) {
                $exp[] = ['Utilities', 14500, 'Gas bill (SNGPL)', 'online'];
                $exp[] = ['Utilities', 6500, 'Internet & phone', 'online'];
            }
            if ($ago % 3 === 0) {
                $exp[] = ['Raw Materials', mt_rand(2500, 6500), 'Fresh herbs, lemons & mint (market)', 'cash'];
            }
            if ($ago % 7 === 2) {
                $exp[] = ['Packaging', mt_rand(5000, 9000), 'Carry bags, foil & napkins', 'cash'];
            }
            if ($ago % 9 === 4) {
                $exp[] = ['Maintenance & Repairs', mt_rand(3000, 15000), ['Fryer servicing', 'AC repair', 'Plumbing fix', 'Freezer gas refill'][mt_rand(0, 3)], 'cash'];
            }
            if ($ago % 8 === 1) {
                $exp[] = ['Marketing', mt_rand(8000, 20000), 'Social media ads', 'card'];
            }
            if ($ago % 2 === 0) {
                $exp[] = ['Transport & Fuel', mt_rand(1500, 3500), 'Delivery bike fuel', 'cash'];
            }
            foreach ($exp as [$cat, $amt, $desc, $m]) {
                $this->db->insert('expenses', [
                    'category_id' => $expCats[$cat], 'user_id' => $this->users['manager'], 'expense_date' => $day, 'amount' => $amt,
                    'payment_method' => $m, 'description' => $desc, 'created_at' => "$day 16:00:00",
                ]);
            }
        }
    }

    private function lowStockDemo(): void
    {
        foreach (['Mozzarella' => 4.5, 'Paper Cup' => 140, 'Chicken Wings' => 6] as $name => $target) {
            $id = $this->ingredients[$name];
            $cur = (float)$this->db->value('SELECT stock_qty FROM ingredients WHERE id = ?', [$id]);
            if ($cur > $target) {
                Stock::move('ingredient', $id, $target - $cur, 'wastage', null, 'Spoilage found during stock count');
            }
        }
        $water = (int)$this->db->value("SELECT id FROM products WHERE name = 'Mineral Water 500ml'");
        $cur = (float)$this->db->value('SELECT stock_qty FROM products WHERE id = ?', [$water]);
        if ($cur > 30) {
            Stock::move('product', $water, 30 - $cur, 'adjustment', null, 'Stock count correction');
        }
        $this->db->query('UPDATE stock_movements SET user_id = ? WHERE user_id IS NULL', [$this->users['manager']]);
    }
}
