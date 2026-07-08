<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\Word;

class TestsPageController extends Controller
{
    public function index(): void
    {
        Auth::require();       
    
        $this->view('pages/tests/index', [
            'title' => 'Tests',
            'levels' => Word::getLevels(),
            'types' => Word::getTypes(),
            'topics' => Word::getTopics(),
        ]);
    }
}
