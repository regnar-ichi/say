<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Response;
use App\Models\WordCard;

class WordCardController extends Controller
{
    public function getCards(): void
    {
        $direction = trim($_GET['direction'] ?? $_POST['direction'] ?? 'all');
        $limit = (int) ($_GET['limit'] ?? $_POST['limit'] ?? 10);
        $levels = $this->inputArray('levels');
        $topics = $this->inputArray('topics');
        $types = $this->inputArray('types');

        if (!in_array($direction, ['all', 'translate', 'text'], true)) {
            $direction = 'all';
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }

        $cards = WordCard::getCards(Auth::id(), $direction, $limit, $levels, $types, $topics);

        if (empty($cards)) {
            Response::json([
                'status' => 'error',
                'message' => 'No words available'
            ]);
            return;
        }

        Response::json([
            'status' => 'ok',
            'data' => $cards
        ]);
    }

    public function mark(): void
    {
        $wordId = (int) ($_POST['word_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($wordId <= 0 || !in_array($status, ['known', 'learning'], true)) {
            Response::json([
                'status' => 'error',
                'message' => 'Invalid word card status'
            ]);
            return;
        }

        if (!WordCard::mark(Auth::id(), $wordId, $status)) {
            Response::json([
                'status' => 'error',
                'message' => 'Could not save word card status'
            ]);
            return;
        }

        Response::json([
            'status' => 'ok'
        ]);
    }

    private function inputArray(string $key): array
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? [];

        if (!is_array($value)) {
            $value = [$value];
        }

        $value = array_map('trim', $value);
        $value = array_filter($value, fn(string $item) => $item !== '');

        return array_values(array_unique($value));
    }
}
