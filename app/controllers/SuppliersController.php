<?php
declare(strict_types=1);

final class SuppliersController extends Controller
{
    public function index(): void
    {
        $this->view('suppliers/index', [
            'title' => 'Suppliers',
            'subtitle' => 'Vendors you buy ingredients and stock from',
            'suppliers' => $this->db->fetchAll(
                'SELECT s.*, COUNT(p.id) purchase_count, COALESCE(SUM(p.total),0) purchased, COALESCE(SUM(p.total - p.paid),0) due
                   FROM suppliers s LEFT JOIN purchases p ON p.supplier_id = s.id GROUP BY s.id ORDER BY s.name'
            ),
        ]);
    }

    public function show(): void
    {
        $s = $this->findOr404('suppliers', input_int('id'));
        $purchases = $this->db->fetchAll('SELECT * FROM purchases WHERE supplier_id = ? ORDER BY purchase_date DESC, id DESC', [$s['id']]);
        $this->view('suppliers/view', [
            'title' => $s['name'],
            'subtitle' => 'Supplier profile & purchase history',
            'supplier' => $s,
            'purchases' => $purchases,
            'ingredients' => $this->db->fetchAll('SELECT name, unit, stock_qty FROM ingredients WHERE supplier_id = ? ORDER BY name', [$s['id']]),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'           => ['Name', 'required|maxlen:120'],
            'contact_person' => ['Contact person', 'maxlen:100'],
            'phone'          => ['Phone', 'maxlen:30'],
            'email'          => ['Email', 'email|maxlen:150'],
            'address'        => ['Address', 'maxlen:255'],
            'notes'          => ['Notes', 'maxlen:1000'],
        ]);
        $row = [];
        foreach (['name', 'contact_person', 'phone', 'email', 'address', 'notes'] as $k) {
            $row[$k] = trim((string)($data[$k] ?? '')) ?: null;
        }
        if ($id = input_int('id')) {
            $this->findOr404('suppliers', $id);
            $this->db->update('suppliers', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('suppliers', $row);
        }
        audit('supplier_saved', 'supplier', $id, $row['name']);
        flash('success', 'Supplier “' . $row['name'] . '” saved.');
        redirect_back('suppliers');
    }

    public function delete(): void
    {
        $this->requirePost();
        $s = $this->findOr404('suppliers', input_int('id'));
        $this->db->delete('suppliers', 'id = ?', [$s['id']]);
        audit('supplier_deleted', 'supplier', (int)$s['id'], $s['name']);
        flash('success', 'Supplier deleted. Past purchases are kept.');
        redirect('suppliers');
    }
}
