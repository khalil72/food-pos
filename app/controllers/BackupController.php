<?php
declare(strict_types=1);

final class BackupController extends Controller
{
    public function index(): void
    {
        $this->view('backup/index', [
            'title' => 'Backup & Restore',
            'subtitle' => 'Protect your sales data',
            'files' => Backup::files(),
            'dbSize' => (float)$this->db->value('SELECT COALESCE(SUM(data_length + index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()'),
            'counts' => $this->db->fetch('SELECT (SELECT COUNT(*) FROM orders) orders, (SELECT COUNT(*) FROM products) products, (SELECT COUNT(*) FROM customers) customers, (SELECT COUNT(*) FROM expenses) expenses'),
        ]);
    }

    public function create(): void
    {
        $this->requirePost();
        @set_time_limit(300);
        $name = Backup::create('manual');
        audit('backup_created', 'backup', null, $name);
        flash('success', "Backup “{$name}” created.");
        redirect('backup');
    }

    public function download(): void
    {
        $path = Backup::path((string)input('file', ''));
        if (!$path) {
            App::abort(404);
        }
        audit('backup_downloaded', 'backup', null, basename($path));
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function delete(): void
    {
        $this->requirePost();
        $path = Backup::path((string)input('file', ''));
        if (!$path) {
            App::abort(404);
        }
        unlink($path);
        audit('backup_deleted', 'backup', null, basename($path));
        flash('success', 'Backup deleted.');
        redirect('backup');
    }

    public function restore(): void
    {
        $this->requirePost();
        @set_time_limit(600);
        if (input('confirm_text') !== 'RESTORE') {
            $this->fail('Type RESTORE to confirm.');
        }
        $sql = null;
        $source = '';
        if (!empty($_FILES['sql_file']['name'])) {
            $f = $_FILES['sql_file'];
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                $this->fail('The file could not be uploaded.');
            }
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') {
                $this->fail('Please upload a .sql backup file.');
            }
            $sql = (string)file_get_contents($f['tmp_name']);
            $source = 'upload ' . $f['name'];
        } elseif ($path = Backup::path((string)input('file', ''))) {
            $sql = (string)file_get_contents($path);
            $source = basename($path);
        }
        if (!$sql || !str_contains($sql, 'CREATE TABLE')) {
            $this->fail('That does not look like a valid backup file.');
        }

        $safety = Backup::create('pre-restore');
        $uid = Auth::id();
        try {
            $count = Backup::restore($sql);
        } catch (Throwable $e) {
            error_log((string)$e);
            try {
                Backup::restore((string)file_get_contents(Backup::dir() . '/' . $safety));
            } catch (Throwable) {
                // leave as is; the safety file remains available for manual restore
            }
            $this->fail('Restore failed and the previous data was put back. Error: ' . $e->getMessage());
        }
        settings_all(true);
        // The restored data may not contain the current user; re-check the session.
        if (!$this->db->value('SELECT id FROM users WHERE id = ? AND is_active = 1', [$uid])) {
            Auth::logout();
            flash('success', "Database restored ($count statements). Please sign in.");
            redirect('login');
        }
        audit('backup_restored', 'backup', null, "$source · $count statements · safety copy $safety");
        flash('success', "Database restored from $source. A safety backup ($safety) was taken first.");
        redirect('backup');
    }
}
