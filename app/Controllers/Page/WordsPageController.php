<?php

namespace App\Controllers\Page;

use App\Core\View;
use App\Models\Word;
use App\Controllers\Controller;
use App\Core\Auth;

class WordsPageController extends Controller
{
    public function index(): void
    {
        Auth::require();    

        $type = trim($_GET['type'] ?? '');
        $level = trim($_GET['level'] ?? '');
        $topic = trim($_GET['topic'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);
        
        $limit = 25;
        
        // Validate page
        if ($page < 1) {
            $page = 1;
        }

        // Get total count
        $totalCount = Word::getCount($type, $level, $topic);
        $totalPages = ceil($totalCount / $limit);

        // If page is beyond range, show first page
        if ($page > $totalPages && $totalPages > 0) {
            $page = 1;
        }

        // Calculate offset
        $offset = ($page - 1) * $limit;

        // Get words for current page
        $words = Word::getAllWithStatsPagination($limit, $offset, $type, $level, $topic);

        $this->view('pages/words/index', [
            'title' => 'Words',
            'words' => $words,
            'currentType' => $type,
            'currentLevel' => $level,
            'currentTopic' => $topic,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'limit' => $limit,
            'levels' => Word::getLevels(),
            'types' => Word::getTypes(),
            'topics' => Word::getTopics()
        ]);
    }
}
