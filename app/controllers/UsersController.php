<?php
declare(strict_types=1);

final class UsersController extends Controller
{
    public function index(): void
    {
        $this->view('users/index', [
            'title' => 'Users & Roles',
            'subtitle' => 'Staff accounts and their permissions',
            'users' => $this->db->fetchAll(
                "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) orders,
                        (SELECT COUNT(*) FROM cash_registers r WHERE r.user_id = u.id AND r.status = 'open') open_reg
                   FROM users u ORDER BY FIELD(u.role,'admin','manager','cashier'), u.name"
            ),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $id = input_int('id');
        $data = $this->validate([
            'name'     => ['Name', 'required|maxlen:100'],
            'username' => ['Username', 'required|minlen:3|maxlen:50|alnum'],
            'email'    => ['Email', 'email|maxlen:150'],
            'phone'    => ['Phone', 'maxlen:30'],
            'role'     => ['Role', 'required|in:admin,manager,cashier'],
            'password' => ['Password', ($id ? '' : 'required|') . 'minlen:8|maxlen:72'],
        ]);
        if ($this->db->value('SELECT id FROM users WHERE username = ? AND id <> ?', [$data['username'], $id])) {
            $this->fail('That username is already taken.');
        }
        if (($data['password'] ?? '') !== '' && $data['password'] !== ($_POST['password_confirm'] ?? '')) {
            $this->fail('The password confirmation does not match.');
        }
        $row = [
            'name'      => trim($data['name']),
            'username'  => $data['username'],
            'email'     => trim((string)($data['email'] ?? '')) ?: null,
            'phone'     => trim((string)($data['phone'] ?? '')) ?: null,
            'role'      => $data['role'],
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];
        if (($data['password'] ?? '') !== '') {
            $row['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($id) {
            $existing = $this->findOr404('users', $id);
            if ($id === Auth::id() && ($row['role'] !== 'admin' || !$row['is_active'])) {
                $this->fail('You cannot remove your own admin access or disable your own account.');
            }
            if ($existing['role'] === 'admin' && ($row['role'] !== 'admin' || !$row['is_active']) && $this->activeAdmins() <= 1) {
                $this->fail('At least one active administrator is required.');
            }
            $this->db->update('users', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('users', $row);
        }
        audit('user_saved', 'user', $id, $row['username'] . ' (' . $row['role'] . ')' . (isset($row['password_hash']) ? ' · password set' : ''));
        flash('success', 'User “' . $row['name'] . '” saved.');
        redirect('users');
    }

    public function delete(): void
    {
        $this->requirePost();
        $u = $this->findOr404('users', input_int('id'));
        if ((int)$u['id'] === Auth::id()) {
            $this->fail('You cannot delete your own account.');
        }
        if ($u['role'] === 'admin' && $u['is_active'] && $this->activeAdmins() <= 1) {
            $this->fail('At least one active administrator is required.');
        }
        $hasHistory = $this->db->value('SELECT 1 FROM orders WHERE user_id = ? UNION SELECT 1 FROM cash_registers WHERE user_id = ? UNION SELECT 1 FROM expenses WHERE user_id = ? UNION SELECT 1 FROM purchases WHERE user_id = ? LIMIT 1', [$u['id'], $u['id'], $u['id'], $u['id']]);
        if ($hasHistory) {
            $this->db->update('users', ['is_active' => 0], 'id = ?', [$u['id']]);
            audit('user_disabled', 'user', (int)$u['id'], $u['username']);
            flash('warning', $u['name'] . ' has transaction history, so the account was disabled instead of deleted.');
        } else {
            $this->db->delete('users', 'id = ?', [$u['id']]);
            audit('user_deleted', 'user', (int)$u['id'], $u['username']);
            flash('success', 'User deleted.');
        }
        redirect('users');
    }

    private function activeAdmins(): int
    {
        return (int)$this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }
}
