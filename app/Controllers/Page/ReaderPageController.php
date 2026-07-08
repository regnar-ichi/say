<?php

namespace App\Controllers\Page;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Models\ReaderItem;
use App\Models\Word;

class ReaderPageController extends Controller
{
    public function index(): void
    {
        Auth::require();

        $itemId = (int) ($_GET['item_id'] ?? 0);
        $pageNumber = max(1, (int) ($_GET['page'] ?? 1));
        $text = '';
        $tokens = [];
        $currentItem = null;
        $error = '';
        $readerTitle = '';

        if ($itemId > 0) {
            $currentItem = ReaderItem::findPageForUser(Auth::id(), $itemId, $pageNumber);

            if ($currentItem === null) {
                $error = 'Saved text not found';
            } else {
                ReaderItem::saveProgressForUser(Auth::id(), $itemId, (int) $currentItem['page_number']);

                $text = $currentItem['content'] ?? '';
                $tokens = $this->buildTokens($text);
                $readerTitle = $currentItem['item_title'] ?? '';
            }
        }

        $this->view('pages/reader/index', [
            'title' => 'Reader',
            'text' => $text,
            'tokens' => $tokens,
            'currentItem' => $currentItem,
            'readerTitle' => $readerTitle,
            'error' => $error
        ]);
    }

    private function buildTokens(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/[A-Za-z]+(?:[\'-][A-Za-z]+)*|[^A-Za-z]+/u', $text, $matches);

        $parts = $matches[0] ?? [];
        $words = [];

        foreach ($parts as $part) {
            if ($this->isWord($part)) {
                $words[] = strtolower($part);
            }
        }

        $dictionary = Word::findReaderWords(array_values(array_unique($words)));
        $tokens = [];

        foreach ($parts as $part) {
            $normalized = $this->isWord($part) ? strtolower($part) : '';

            $tokens[] = [
                'text' => $part,
                'matches' => $normalized !== '' ? ($dictionary[$normalized] ?? []) : []
            ];
        }

        return $tokens;
    }

    private function isWord(string $value): bool
    {
        return preg_match('/^[A-Za-z]+(?:[\'-][A-Za-z]+)*$/u', $value) === 1;
    }

}
