# Saffron POS — Fast Food POS & Restaurant Management

A complete point-of-sale and back-office system for fast-food restaurants, built with PHP 8, MySQL/MariaDB, Bootstrap 5, vanilla JavaScript and AJAX. All front-end libraries and fonts are bundled locally, so it works without an internet connection.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `gd`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `.htaccess` support (XAMPP / WAMP / LAMP work out of the box)

## Installation

### Option A: web installer
1. Copy the `food-pos` folder into your web root (e.g. `C:\xampp\htdocs\food-pos`).
2. Start Apache and MySQL.
3. Open `http://localhost/food-pos/install/`.
4. Enter the database details and admin account, and choose whether to load sample data. Installing with sample data takes about a minute.
5. Sign in at `http://localhost/food-pos/`.
6. On a live server, delete or rename the `install/` folder afterwards.

### Option B: command line
```bash
php install/cli.php --db-name=food_pos --db-user=root --db-pass= --admin-pass=YourStrongPass1
# add --no-sample for an empty system, --force to reinstall over an existing config
```

### Demo accounts (sample data)
| Username | Role | Password |
|---|---|---|
| `admin` | Admin | the password you set (`password123` for the CLI default) |
| `manager` | Manager | `password123` |
| `cashier`, `hina` | Cashier | `password123` |

Change these passwords (Users & Roles) before going live.

## Features

**Point of sale**: category tabs, product images, instant search, barcode scanning (USB/Bluetooth scanners work with or without focus), sizes and add-ons, item notes, quantity controls, dine-in / takeaway / delivery, table numbers, delivery address and fee, customer lookup and quick-add, percentage or fixed discounts (with a cashier limit), loyalty point redemption, order notes, cash / card / online payment with quick-cash buttons and change calculation, hold and resume orders, reprinting today's orders, and a cart that survives a page reload.
Keyboard shortcuts: **F2** search, **F9/F4** charge, **F8** hold, **Enter** confirm.

**Receipts**: 58mm and 80mm thermal receipts and A4 invoices with logo, contact details, tax number and payment summary. Each can be printed or downloaded as a PDF, and receipts can print automatically after a sale.

**Menu**: products (SKU, barcode, price, cost, image, availability), categories with icon and colour, variants with their own price and cost, and add-ons.

**Inventory**: ingredients with units and cost, product recipes that deduct ingredients automatically on each sale, stock tracking for ready-made items, adjustments and wastage, full movement history, low-stock alerts and stock valuation.

**Purchasing**: suppliers, purchase bills that add stock and update costs, partial payments and balances due.

**Customers**: profiles, order history, favourites, loyalty points (earn and redeem, with manual adjustments).

**Sales**: order history with filters, order detail, partial or full refunds and returns (with optional restocking), and CSV export.

**Expenses**: categorised expenses (salaries, rent, utilities, raw materials, packaging and more), optionally paid from the cash drawer.

**Cash register**: open and close shifts, cash in/out, expected vs counted cash with over/short, and X/Z reports.

**Dashboard & reports**: KPI cards compared against the previous period, sales/expense/profit trend, category mix, busiest hours, order channels, top sellers, low stock and recent transactions. Reports cover sales, product sales, profit & loss, expenses, payment methods, inventory valuation and staff performance, each exportable to CSV, PDF or print.

**Administration**: Admin / Manager / Cashier roles, restaurant / tax / currency / receipt / POS / loyalty settings, audit log, and one-click database backup, download and restore.

## How the numbers work

- **Net sales** = order totals − refunds.
- **Net revenue** = sales excluding tax, after refunds.
- **COGS** = item cost at the time of sale. This is the recipe cost when a recipe exists, otherwise the product or variant cost. Costs are reversed for restocked returns.
- **Gross profit** = net revenue − COGS. **Net profit** = gross profit − expenses.
- Stock purchases are not expenses: their cost reaches the P&L through COGS when items sell. Use the *Raw Materials* expense category only for items you don't track in inventory.
- **Expected drawer cash** = opening float + cash sales − cash refunds + cash in − cash out − expenses paid from the drawer.

## Architecture

```
index.php                front controller (?r=module/action)
app/bootstrap.php        config, autoload, secure session, error handling
app/routes.php           route → controller, method, allowed roles
app/core/                App (router), Controller, View, Database (PDO), Auth, Csrf, Validator, helpers
app/models/              OrderService, Catalog, Stock, Register, Metrics, Backup
app/controllers/         one controller per module
app/views/               layouts, partials and module views
assets/                  css, js, bundled vendor libraries and fonts
install/                 web + CLI installer, schema.sql, sample data seeder
uploads/  backups/       user files (script execution and web access blocked)
```

## Security

- PDO prepared statements everywhere, with emulation disabled.
- `password_hash` / `password_verify`, with rehash on login.
- CSRF token on every POST, including AJAX via the `X-CSRF-Token` header.
- Session regenerated on login, HttpOnly and SameSite cookies, strict mode, idle timeout.
- Login throttling: 5 failed attempts per IP lock that IP out for 15 minutes.
- Role checks on every route, enforced server-side. Cashiers only see their own orders.
- Prices, discounts, tax and stock are recalculated on the server for every sale; the browser cannot set prices.
- Image uploads are checked by content type; `app/`, `config/`, `backups/` and the installer internals are blocked from the web.
- CSV exports are protected against spreadsheet formula injection.

## Configuration

`config/config.php` (written by the installer) holds the database credentials. Set `'debug' => true` there to see detailed errors while developing. Everything else is under **Settings** in the app.

Timezone, currency symbol and decimals, tax rate (inclusive or exclusive), receipt size, auto-print, delivery fee, cashier discount limit and the loyalty rules are all in **Settings**.
"# food-pos" 
