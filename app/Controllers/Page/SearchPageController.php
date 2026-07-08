<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;

class SearchPageController extends Controller
{
    public function index(): void
    {
        Auth::require();

        $this->view('pages/search/index', [
            'title' => 'Search'
        ]);
    }
}