<?php

require_once __DIR__ . '/../autoload.php';

use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$router = new Router();

require_once __DIR__ . '/../routes/ajax.php';

// ClickUp bridge doesn't require auth (uses secret token instead)
if ($action !== 'clickup') {
    Auth::require();
}

$router->dispatch($action);