<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = __DIR__ . '/../Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            exit('View not found');
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout.php';
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data);

        $path = __DIR__ . '/../Views/partials/' . $view . '.php';

        if (!file_exists($path)) {
            exit('Partial not found');
        }

        require $path;
    }    
}