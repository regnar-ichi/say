<?php

namespace App\Controllers\Api;

use App\Core\Response;

class PingController
{
    public function index(): void
    {
        Response::json([
            'status' => 'ok',
            'message' => 'Ping works!'
        ]);
    }
}