<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Models\Word;
use App\Core\Auth;

class WordEditPageController extends Controller
{
    public function index(): void
    {
        Auth::require();    
    
        $id = (int) ($_GET['id'] ?? 0);

        $word = $id > 0 ? Word::find($id) : null;

        $this->view('pages/words/edit', [
            'title' => 'Edit word',
            'word' => $word
        ]);
    }
}