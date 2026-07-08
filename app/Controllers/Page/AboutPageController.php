<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;

class AboutPageController extends Controller
{
    public function index(): void
    {
        Auth::require();

        $this->view('pages/about/index', [
            'title' => 'About'
        ]);
    }
}