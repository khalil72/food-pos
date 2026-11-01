<?php
declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $content = self::fetch($view, $data);
        if ($layout === null) {
            echo $content;
        } else {
            echo self::fetch('layouts/' . $layout, $data + ['content' => $content]);
        }
        clear_old();
    }

    public static function fetch(string $view, array $data = []): string
    {
        $file = APP_PATH . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View not found: $view");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }

    public static function partial(string $view, array $data = []): void
    {
        echo self::fetch('partials/' . $view, $data);
    }
}
