<?php

// Ensure Page controller directory exists
$pageControllerDir = __DIR__ . '/app/Controllers/Page';
if (!is_dir($pageControllerDir)) {
    @mkdir($pageControllerDir, 0755, true);
}

spl_autoload_register(function ($class) {

    $prefix = 'App\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $class = str_replace($prefix, '', $class);

    $path = __DIR__ . '/app/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/app/Helpers/helpers.php';
require_once __DIR__ . '/app/Helpers/env.php';