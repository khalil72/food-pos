<?php
declare(strict_types=1);

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    /**
     * Validates input; on failure responds with JSON (AJAX) or flashes the error
     * and redirects back with old input.
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data ??= $_POST;
        $v = Validator::make($data, $rules);
        if ($v->fails()) {
            $this->fail($v->first(), $data, $v->errors());
        }
        return $data;
    }

    protected function fail(string $message, ?array $data = null, array $errors = []): never
    {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => $message, 'errors' => $errors], 422);
        }
        set_old($data ?? $_POST);
        flash('danger', $message);
        redirect_back();
    }

    protected function requirePost(): void
    {
        if (!is_post()) {
            http_response_code(405);
            exit('Method not allowed');
        }
    }

    protected function findOr404(string $table, int $id): array
    {
        $row = $this->db->fetch("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if (!$row) {
            App::abort(404);
        }
        return $row;
    }
}
