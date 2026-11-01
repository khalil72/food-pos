<?php
// route => [Controller, method, access]   access: 'guest' | 'auth' | [roles]
$AM  = ['admin', 'manager'];
$ALL = ['admin', 'manager', 'cashier'];
$A   = ['admin'];

return [
    'login'  => ['AuthController', 'login', 'guest'],
    'logout' => ['AuthController', 'logout', 'auth'],

    'dashboard' => ['DashboardController', 'index', $AM],

    'pos'               => ['PosController', 'index', $ALL],
    'pos/catalog'       => ['PosController', 'catalog', $ALL],
    'pos/checkout'      => ['PosController', 'checkout', $ALL],
    'pos/customers'     => ['PosController', 'customers', $ALL],
    'pos/customer-save' => ['PosController', 'customerSave', $ALL],
    'pos/recent'        => ['PosController', 'recent', $ALL],

    'orders'         => ['OrdersController', 'index', $ALL],
    'orders/view'    => ['OrdersController', 'show', $ALL],
    'orders/receipt' => ['OrdersController', 'receipt', $ALL],
    'orders/refund'  => ['OrdersController', 'refund', $AM],
    'orders/export'  => ['OrdersController', 'export', $AM],

    'products'        => ['ProductsController', 'index', $AM],
    'products/create' => ['ProductsController', 'form', $AM],
    'products/edit'   => ['ProductsController', 'form', $AM],
    'products/save'   => ['ProductsController', 'save', $AM],
    'products/delete' => ['ProductsController', 'delete', $AM],
    'products/toggle' => ['ProductsController', 'toggle', $AM],
    'products/export' => ['ProductsController', 'export', $AM],

    'categories'        => ['CategoriesController', 'index', $AM],
    'categories/save'   => ['CategoriesController', 'save', $AM],
    'categories/delete' => ['CategoriesController', 'delete', $AM],

    'addons'        => ['AddonsController', 'index', $AM],
    'addons/save'   => ['AddonsController', 'save', $AM],
    'addons/delete' => ['AddonsController', 'delete', $AM],

    'inventory'           => ['InventoryController', 'index', $AM],
    'inventory/save'      => ['InventoryController', 'save', $AM],
    'inventory/delete'    => ['InventoryController', 'delete', $AM],
    'inventory/adjust'    => ['InventoryController', 'adjust', $AM],
    'inventory/movements' => ['InventoryController', 'movements', $AM],
    'inventory/stock'     => ['InventoryController', 'stock', $AM],

    'suppliers'        => ['SuppliersController', 'index', $AM],
    'suppliers/view'   => ['SuppliersController', 'show', $AM],
    'suppliers/save'   => ['SuppliersController', 'save', $AM],
    'suppliers/delete' => ['SuppliersController', 'delete', $AM],

    'purchases'         => ['PurchasesController', 'index', $AM],
    'purchases/create'  => ['PurchasesController', 'create', $AM],
    'purchases/save'    => ['PurchasesController', 'save', $AM],
    'purchases/view'    => ['PurchasesController', 'show', $AM],
    'purchases/payment' => ['PurchasesController', 'payment', $AM],
    'purchases/delete'  => ['PurchasesController', 'delete', $A],

    'customers'        => ['CustomersController', 'index', $ALL],
    'customers/view'   => ['CustomersController', 'show', $ALL],
    'customers/save'   => ['CustomersController', 'save', $ALL],
    'customers/delete' => ['CustomersController', 'delete', $AM],
    'customers/points' => ['CustomersController', 'points', $AM],
    'customers/export' => ['CustomersController', 'export', $AM],

    'expenses'                 => ['ExpensesController', 'index', $AM],
    'expenses/save'            => ['ExpensesController', 'save', $AM],
    'expenses/delete'          => ['ExpensesController', 'delete', $AM],
    'expenses/export'          => ['ExpensesController', 'export', $AM],
    'expenses/category-save'   => ['ExpensesController', 'categorySave', $AM],
    'expenses/category-delete' => ['ExpensesController', 'categoryDelete', $AM],

    'register'          => ['RegisterController', 'index', $ALL],
    'register/open'     => ['RegisterController', 'open', $ALL],
    'register/close'    => ['RegisterController', 'close', $ALL],
    'register/movement' => ['RegisterController', 'movement', $ALL],
    'register/history'  => ['RegisterController', 'history', $AM],
    'register/view'     => ['RegisterController', 'show', $ALL],

    'reports'           => ['ReportsController', 'index', $AM],
    'reports/sales'     => ['ReportsController', 'sales', $AM],
    'reports/products'  => ['ReportsController', 'products', $AM],
    'reports/profit'    => ['ReportsController', 'profit', $AM],
    'reports/expenses'  => ['ReportsController', 'expenses', $AM],
    'reports/payments'  => ['ReportsController', 'payments', $AM],
    'reports/inventory' => ['ReportsController', 'inventory', $AM],
    'reports/staff'     => ['ReportsController', 'staff', $AM],

    'users'        => ['UsersController', 'index', $A],
    'users/save'   => ['UsersController', 'save', $A],
    'users/delete' => ['UsersController', 'delete', $A],

    'settings'      => ['SettingsController', 'index', $A],
    'settings/save' => ['SettingsController', 'save', $A],

    'audit' => ['AuditController', 'index', $A],

    'backup'          => ['BackupController', 'index', $A],
    'backup/create'   => ['BackupController', 'create', $A],
    'backup/download' => ['BackupController', 'download', $A],
    'backup/restore'  => ['BackupController', 'restore', $A],
    'backup/delete'   => ['BackupController', 'delete', $A],

    'profile'      => ['ProfileController', 'index', $ALL],
    'profile/save' => ['ProfileController', 'save', $ALL],
];
