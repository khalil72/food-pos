<?php
declare(strict_types=1);

final class App
{
    public static function run(): void
    {
        $routes = require APP_PATH . '/routes.php';
        $route = trim((string)($_GET['r'] ?? ''), '/');

        if ($route === '') {
            redirect(Auth::check() ? Auth::homeRoute() : 'login');
        }
        if (!isset($routes[$route])) {
            self::abort(404);
        }
        [$controller, $method, $access] = $routes[$route];

        if ($access === 'guest') {
            if (Auth::check()) {
                redirect(Auth::homeRoute());
            }
        } else {
            if (!Auth::check()) {
                if (is_ajax()) {
                    json_out(['ok' => false, 'message' => 'Your session has expired. Please sign in again.'], 401);
                }
                redirect('login');
            }
            if (is_array($access) && !has_role(...$access)) {
                self::abort(403);
            }
        }

        if (is_post() && !Csrf::verify()) {
            if (is_ajax()) {
                json_out(['ok' => false, 'message' => 'Security token expired. Please reload the page.'], 403);
            }
            flash('danger', 'Your form expired. Please try again.');
            redirect_back($access === 'guest' ? 'login' : Auth::homeRoute());
        }

        (new $controller())->$method();
    }

    public static function abort(int $code): never
    {
        http_response_code($code);
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => $code === 403 ? 'You do not have permission to do that.' : 'Not found.'], $code);
        }
        View::render('errors/error', [
            'title' => $code === 403 ? 'Access denied' : 'Page not found',
            'code'  => $code,
        ], Auth::check() ? 'main' : 'auth');
        exit;
    }
}
