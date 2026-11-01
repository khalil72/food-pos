<?php
declare(strict_types=1);

final class CategoriesController extends Controller
{
    public const ICONS = ['bi-egg-fried', 'bi-cup-straw', 'bi-cup-hot', 'bi-cake2', 'bi-basket', 'bi-fire', 'bi-star', 'bi-heart',
        'bi-gift', 'bi-box2-heart', 'bi-droplet', 'bi-snow', 'bi-sun', 'bi-moon-stars', 'bi-flower1', 'bi-tree',
        'bi-apple', 'bi-lightning-charge', 'bi-trophy', 'bi-bag', 'bi-grid', 'bi-tag', 'bi-award', 'bi-emoji-smile'];

    public function index(): void
    {
        $this->view('categories/index', [
            'title' => 'Categories',
            'subtitle' => 'Group your menu for faster ordering',
            'categories' => $this->db->fetchAll(
                'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) product_count FROM categories c ORDER BY c.sort_order, c.name'
            ),
            'icons' => self::ICONS,
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $data = $this->validate([
            'name'       => ['Name', 'required|maxlen:80'],
            'color'      => ['Colour', 'required|color'],
            'icon'       => ['Icon', 'required|in:' . implode(',', self::ICONS)],
            'sort_order' => ['Sort order', 'int|min:0|max:9999'],
        ]);
        $row = [
            'name'       => trim($data['name']),
            'color'      => strtoupper($data['color']),
            'icon'       => $data['icon'],
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active'  => input('is_active') === '1' ? 1 : 0,
        ];
        if ($id = input_int('id')) {
            $this->findOr404('categories', $id);
            $this->db->update('categories', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('categories', $row);
        }
        audit('category_saved', 'category', $id, $row['name']);
        flash('success', 'Category “' . $row['name'] . '” saved.');
        redirect('categories');
    }

    public function delete(): void
    {
        $this->requirePost();
        $c = $this->findOr404('categories', input_int('id'));
        $this->db->delete('categories', 'id = ?', [$c['id']]);
        audit('category_deleted', 'category', (int)$c['id'], $c['name']);
        flash('success', 'Category deleted. Its products are now uncategorised.');
        redirect('categories');
    }
}
