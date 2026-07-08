<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\AccountStats;

class AccountPageController extends Controller
{
    public function index(): void
    {
        Auth::require();

        Auth::start();

        $this->view('pages/account/index', [
            'title' => 'Account',
            'login' => $_SESSION['user_login'] ?? '',
            'stats' => AccountStats::getForUser(Auth::id()),
            'problemWords' => AccountStats::getProblemWords(Auth::id()),
        ]);
    }
}
