<?php
declare(strict_types=1);

final class SettingsController extends Controller
{
    /** key => [label, rules] for every editable setting */
    private const FIELDS = [
        'restaurant_name'      => ['Restaurant name', 'required|maxlen:100'],
        'restaurant_address'   => ['Address', 'maxlen:300'],
        'restaurant_phone'     => ['Phone', 'maxlen:50'],
        'restaurant_email'     => ['Email', 'email|maxlen:150'],
        'restaurant_website'   => ['Website', 'maxlen:150'],
        'tax_name'             => ['Tax name', 'required|maxlen:30'],
        'tax_rate'             => ['Tax rate', 'required|numeric|min:0|max:100'],
        'tax_number_label'     => ['Tax number label', 'maxlen:30'],
        'tax_number'           => ['Tax number', 'maxlen:60'],
        'currency_symbol'      => ['Currency symbol', 'required|maxlen:8'],
        'currency_code'        => ['Currency code', 'maxlen:5'],
        'currency_position'    => ['Currency position', 'required|in:before,after'],
        'currency_decimals'    => ['Decimals', 'required|in:0,1,2,3'],
        'timezone'             => ['Timezone', 'required|maxlen:64'],
        'date_format'          => ['Date format', 'required|in:d M Y,d/m/Y,m/d/Y,Y-m-d,M d Y'],
        'receipt_header'       => ['Receipt header', 'maxlen:300'],
        'receipt_footer'       => ['Receipt footer', 'maxlen:300'],
        'receipt_size'         => ['Default receipt', 'required|in:58,80,a4'],
        'order_prefix'         => ['Order prefix', 'maxlen:10'],
        'default_order_type'   => ['Default order type', 'required|in:dine_in,takeaway,delivery'],
        'delivery_fee'         => ['Delivery fee', 'required|numeric|min:0|max:999999'],
        'cashier_max_discount' => ['Cashier discount limit', 'required|numeric|min:0|max:100'],
        'loyalty_earn_amount'  => ['Spend per point', 'required|numeric|min:1|max:9999999'],
        'loyalty_point_value'  => ['Point value', 'required|numeric|min:0.01|max:999999'],
        'loyalty_min_redeem'   => ['Minimum redeem', 'required|int|min:0|max:9999999'],
    ];
    private const TOGGLES = ['tax_inclusive', 'receipt_show_logo', 'auto_print', 'require_register', 'allow_negative_stock', 'loyalty_enabled'];

    public function index(): void
    {
        $this->view('settings/index', [
            'title' => 'Settings',
            'subtitle' => 'Restaurant profile, tax, receipts and POS behaviour',
            's' => settings_all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $rules = self::FIELDS;
        $data = $this->validate($rules);
        if (!in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $this->fail('Choose a valid timezone.');
        }
        $pairs = [];
        foreach (array_keys(self::FIELDS) as $k) {
            $pairs[$k] = trim((string)($data[$k] ?? ''));
        }
        foreach (self::TOGGLES as $k) {
            $pairs[$k] = input($k) === '1' ? '1' : '0';
        }
        try {
            if ($logo = store_image_upload($_FILES['logo'] ?? null, 'logo')) {
                delete_upload(setting('logo'));
                $pairs['logo'] = $logo;
            } elseif (input('remove_logo') === '1') {
                delete_upload(setting('logo'));
                $pairs['logo'] = '';
            }
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        setting_save($pairs);
        audit('settings_updated', 'settings', null, implode(', ', array_keys($pairs)));
        flash('success', 'Settings saved.');
        redirect('settings', ['tab' => input('tab', 'restaurant')]);
    }
}
