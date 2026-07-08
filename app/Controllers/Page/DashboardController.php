<?php

namespace App\Controllers\Page;

use App\Core\Auth;
use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        Auth::require();

        View::render('pages/dashboard', [
            'title' => 'Dashboard'
        ]);
    }
}