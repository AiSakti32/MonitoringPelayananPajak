<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $name, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = base_path('app/Views/' . str_replace('.', '/', $name) . '.php');
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$name}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = base_path('app/Views/' . str_replace('.', '/', $layout) . '.php');
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        require $layoutFile;
    }

    public static function partial(string $name, array $data = []): void
    {
        $file = base_path('app/Views/' . str_replace('.', '/', $name) . '.php');
        if (!is_file($file)) {
            throw new \RuntimeException("Partial not found: {$name}");
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}
