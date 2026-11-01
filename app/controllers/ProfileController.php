<?php
declare(strict_types=1);

final class ProfileController extends Controller
{
    public function index(): void
    {
        $uid = (int)Auth::id();
        $this->view('profile/index', [
            'title' => 'My profile',
            'subtitle' => 'Your account details and password',
            'u' => $this->db->fetch('SELECT id, name, username, email, phone, role, last_login, created_at FROM users WHERE id = ?', [$uid]),
            'stats' => $this->db->fetch(
                'SELECT COUNT(*) orders, COALESCE(SUM(total),0) sales, COALESCE(AVG(total),0) avg,
                        COALESCE(SUM(created_at >= CURDATE()),0) today,
                        COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN total END),0) today_sales
                   FROM orders WHERE user_id = ?',
                [$uid]
            ),
            'shifts' => (int)$this->db->value('SELECT COUNT(*) FROM cash_registers WHERE user_id = ?', [$uid]),
            'activity' => $this->db->fetchAll('SELECT * FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12', [$uid]),
        ]);
    }

    public function save(): void
    {
        $this->requirePost();
        $u = user();

        if (input('form') === 'password') {
            $hash = (string)$this->db->value('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
            $new = (string)($_POST['new_password'] ?? '');
            if (!password_verify((string)($_POST['current_password'] ?? ''), $hash)) {
                $this->fail('Your current password is incorrect.');
            }
            if (strlen($new) < 8 || strlen($new) > 72) {
                $this->fail('The new password must be between 8 and 72 characters.');
            }
            if ($new !== ($_POST['new_password_confirm'] ?? '')) {
                $this->fail('The new passwords do not match.');
            }
            $this->db->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
            session_regenerate_id(true);
            audit('password_changed', 'user', (int)$u['id']);
            flash('success', 'Your password has been changed.');
            redirect('profile');
        }

        $data = $this->validate([
            'name'  => ['Name', 'required|maxlen:100'],
            'email' => ['Email', 'email|maxlen:150'],
            'phone' => ['Phone', 'maxlen:30'],
        ]);
        $this->db->update('users', [
            'name'  => trim($data['name']),
            'email' => trim((string)($data['email'] ?? '')) ?: null,
            'phone' => trim((string)($data['phone'] ?? '')) ?: null,
        ], 'id = ?', [$u['id']]);
        audit('profile_updated', 'user', (int)$u['id'], 'Details updated');
        flash('success', 'Profile updated.');
        redirect('profile');
    }
}
