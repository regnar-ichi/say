<?php

namespace App\Controllers\Api;

use App\Core\Response;
use App\Models\Test;
use App\Core\Auth;
use App\Controllers\Controller;

class TestController extends Controller
{
    /**
     * Get random test words
     * Parameters: direction, limit
     */
    public function getTestWords(): void
    {
        $direction = trim($_GET['direction'] ?? $_POST['direction'] ?? '');
        $limit = (int) ($_GET['limit'] ?? $_POST['limit'] ?? 10);
        $levels = $this->inputArray('levels');
        $topics = $this->inputArray('topics');
        $types = $this->inputArray('types');

        // Validate direction
        if ($direction !== 'translate' && $direction !== 'text') {
            Response::json([
                'status' => 'error',
                'message' => 'Invalid direction'
            ]);
            return;
        }

        // Validate limit
        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }

        $words = Test::getTestWords($direction, $limit, $levels, $types, $topics);

        if (empty($words)) {
            Response::json([
                'status' => 'error',
                'message' => 'No words available'
            ]);
            return;
        }

        Response::json([
            'status' => 'ok',
            'data' => $words
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

    /**
     * Check test answers
     * Parameters: answers (JSON), direction
     * answers = [{"id": 12, "value": "точность"}, ...]
     */
    public function checkTestWords(): void
    {
        $answersJson = $_POST['answers'] ?? '[]';
        $direction = trim($_POST['direction'] ?? '');

        // Decode answers
        $answers = json_decode($answersJson, true);

        if (!is_array($answers)) {
            Response::json([
                'status' => 'error',
                'message' => 'Invalid answers format'
            ]);
            return;
        }

        // Validate direction
        if ($direction !== 'translate' && $direction !== 'text') {
            Response::json([
                'status' => 'error',
                'message' => 'Invalid direction'
            ]);
            return;
        }

        if (empty($answers)) {
            Response::json([
                'status' => 'error',
                'message' => 'No answers provided'
            ]);
            return;
        }

        // Check answers
        $result = Test::checkAnswers($answers, $direction);

        // Update examination
        Test::updateExamination($result, $direction);

        // Update statistic
        Test::updateStatistic($result);

        // Update tests
        Test::updateTests($result, $direction);

        Response::json([
            'status' => 'ok',
            'data' => [
                'correct' => $result['correct'],
                'wrong' => $result['wrong'],
                'total' => $result['total'],
                'percentage' => $result['total'] > 0 ? round(($result['correct'] / $result['total']) * 100, 2) : 0,
                'details' => $result['details']
            ]
        ]);
    }
}
