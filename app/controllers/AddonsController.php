<?php
declare(strict_types=1);

final class AddonsController extends Controller
{
    public function index(): void
    {
        $this->view('addons/index', [
            'title' => 'Add-ons',
            'subtitle' => 'Extras customers can add to products',
            'addons' => $this->db->fetchAll(
                'SELECT a.*, (SELECT COUNT(*) FROM product_addons pa WHERE pa.addon_id = a.id) product_count FROM addons a ORDER BY a.name'
            ),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'  => ['Name', 'required|maxlen:80'],
            'price' => ['Price', 'required|numeric|min:0|max:9999999'],
            'cost'  => ['Cost', 'numeric|min:0|max:9999999'],
        ]);
        $row = [
            'name'      => trim($data['name']),
            'price'     => round((float)$data['price'], 2),
            'cost'      => round((float)($data['cost'] ?? 0), 2),
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];
        if ($id = input_int('id')) {
            $this->findOr404('addons', $id);
            $this->db->update('addons', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('addons', $row);
            if (input('attach_all') === '1') {
                $this->db->query('INSERT IGNORE INTO product_addons (product_id, addon_id) SELECT id, ? FROM products', [$id]);
            }
        }
        audit('addon_saved', 'addon', $id, $row['name'] . ' · ' . money($row['price']));
        flash('success', 'Add-on “' . $row['name'] . '” saved.');
        redirect('addons');
    }

    public function delete(): void
    {
        $this->requirePost();
        $a = $this->findOr404('addons', input_int('id'));
        $this->db->delete('addons', 'id = ?', [$a['id']]);
        audit('addon_deleted', 'addon', (int)$a['id'], $a['name']);
        flash('success', 'Add-on deleted.');
        redirect('addons');
    }
}
