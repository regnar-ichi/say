<?php

require_once __DIR__ . '/../autoload.php';

use App\Core\Router;

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$router = new Router();

require_once __DIR__ . '/../routes/web.php';

$router->dispatch($path);