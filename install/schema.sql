-- Saffron POS — database schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs, stock_movements, return_items, returns, payments, order_items, orders,
  cash_movements, cash_registers, expenses, expense_categories, purchase_items, purchases,
  product_ingredients, product_addons, product_variants, addons, ingredients, products, categories,
  customers, suppliers, settings, users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'bi-grid',
  color VARCHAR(9) NOT NULL DEFAULT '#E8572A',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  contact_person VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  sku VARCHAR(50) NULL UNIQUE,
  barcode VARCHAR(64) NULL UNIQUE,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  description VARCHAR(500) NULL,
  track_stock TINYINT(1) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  low_stock_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_addons (
  product_id INT UNSIGNED NOT NULL,
  addon_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, addon_id),
  CONSTRAINT fk_pa_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_addon FOREIGN KEY (addon_id) REFERENCES addons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingredients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  low_stock_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  cost_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
  supplier_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ing_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_ingredients (
  product_id INT UNSIGNED NOT NULL,
  ingredient_id INT UNSIGNED NOT NULL,
  qty DECIMAL(12,4) NOT NULL,
  PRIMARY KEY (product_id, ingredient_id),
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL UNIQUE,
  email VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  loyalty_points INT NOT NULL DEFAULT 0,
  total_spent DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cash_registers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
  closed_at DATETIME NULL,
  closing_cash DECIMAL(12,2) NULL,
  expected_cash DECIMAL(12,2) NULL,
  difference DECIMAL(12,2) NULL,
  notes VARCHAR(255) NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  KEY idx_reg_status (status, user_id),
  CONSTRAINT fk_reg_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cash_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  register_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('in','out') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cm_register FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NULL,
  order_type ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'takeaway',
  table_no VARCHAR(20) NULL,
  delivery_address VARCHAR(255) NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_type ENUM('percent','fixed') NULL,
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  loyalty_points_used INT NOT NULL DEFAULT 0,
  loyalty_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  loyalty_points_earned INT NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  tax_inclusive TINYINT(1) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tendered DECIMAL(12,2) NOT NULL DEFAULT 0,
  change_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','card','online') NOT NULL DEFAULT 'cash',
  refunded_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('completed','partially_refunded','refunded') NOT NULL DEFAULT 'completed',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_orders_created (created_at),
  KEY idx_orders_register (register_id),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_register FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(120) NOT NULL,
  variant_name VARCHAR(60) NULL,
  addons_text VARCHAR(255) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  returned_qty INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NULL,
  method ENUM('cash','card','online') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reference VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE returns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_reversed DECIMAL(12,2) NOT NULL DEFAULT 0,
  refund_method ENUM('cash','card','online') NOT NULL DEFAULT 'cash',
  restocked TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_returns_created (created_at),
  CONSTRAINT fk_ret_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE return_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_ri_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  reference VARCHAR(60) NULL,
  purchase_date DATE NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pur_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  item_type ENUM('product','ingredient') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(120) NOT NULL,
  qty DECIMAL(12,3) NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_pi_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  color VARCHAR(9) NOT NULL DEFAULT '#6B7280'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NULL,
  expense_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cash','card','online','bank') NOT NULL DEFAULT 'cash',
  reference VARCHAR(60) NULL,
  description VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_date (expense_date),
  CONSTRAINT fk_exp_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_register FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('product','ingredient') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  change_qty DECIMAL(12,3) NOT NULL,
  balance_after DECIMAL(12,3) NOT NULL,
  reason ENUM('sale','purchase','return','adjustment','wastage','purchase_delete') NOT NULL,
  reference VARCHAR(60) NULL,
  note VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_sm_item (item_type, item_id),
  KEY idx_sm_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  entity VARCHAR(50) NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
