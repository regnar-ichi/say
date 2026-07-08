<?php

namespace App\Models;

use App\Core\Database;

class AccountStats
{
    public static function getForUser(int $userId): array
    {
        $totalWords = self::getTotalWords();
        $progressWords = self::getUniqueProgressWords($userId);
        $testWords = self::getTestWordsCount($userId);
        $cardStats = self::getCardStats($userId);
        $testStats = self::getTestStats($userId);

        return [
            'total_words' => $totalWords,
            'progress_words' => $progressWords,
            'progress_percent' => $totalWords > 0 ? round(($progressWords / $totalWords) * 100, 1) : 0,
            'test_words' => $testWords,
            'known_words' => $cardStats['known_words'],
            'learning_words' => $cardStats['learning_words'],
            'card_seen_words' => $cardStats['card_seen_words'],
            'test_accuracy' => $testStats['total_answers'] > 0
                ? round(($testStats['total_correct'] / $testStats['total_answers']) * 100, 1)
                : 0,
            'directions' => [
                'en_ru' => [
                    'right' => $testStats['right'],
                    'wrong' => $testStats['wrong'],
                    'accuracy' => self::accuracy($testStats['right'], $testStats['wrong']),
                ],
                'ru_en' => [
                    'right' => $testStats['right_ru'],
                    'wrong' => $testStats['wrong_ru'],
                    'accuracy' => self::accuracy($testStats['right_ru'], $testStats['wrong_ru']),
                ],
            ],
        ];
    }

    public static function getProblemWords(int $userId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                t.text,
                t.translate,
                SUM(COALESCE(e.`wrong`, 0) + COALESCE(e.wrong_ru, 0)) AS errors,
                SUM(COALESCE(e.`right`, 0) + COALESCE(e.right_ru, 0)) AS correct
            FROM examination e
            INNER JOIN translate t ON t.id = e.word_id
            WHERE e.user_id = ?
            GROUP BY t.id, t.text, t.translate
            HAVING errors > 0
            ORDER BY errors DESC, (correct / (correct + errors)) ASC
            LIMIT 20
        ");

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $words = [];

        while ($row = $result->fetch_assoc()) {
            $errors = (int)($row['errors'] ?? 0);
            $correct = (int)($row['correct'] ?? 0);
            $total = $correct + $errors;

            $words[] = [
                'text' => (string)($row['text'] ?? ''),
                'translate' => (string)($row['translate'] ?? ''),
                'errors' => $errors,
                'correct' => $correct,
                'accuracy' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            ];
        }

        $stmt->close();

        return $words;
    }

    private static function getTotalWords(): int
    {
        $db = Database::connect();

        $result = $db->query("
            SELECT COUNT(*) AS total
            FROM translate
            WHERE visible = 1 OR visible_ru = 1
        ");

        $row = $result ? $result->fetch_assoc() : null;

        return (int)($row['total'] ?? 0);
    }

    private static function getUniqueProgressWords(int $userId): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT word_id) AS total
            FROM (
                SELECT word_id
                FROM examination
                WHERE user_id = ?
                UNION
                SELECT word_id
                FROM word_card_progress
                WHERE user_id = ?
                  AND status = 'known'
            ) progress_source
        ");

        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }

    private static function getTestWordsCount(int $userId): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT word_id) AS total
            FROM examination
            WHERE user_id = ?
        ");

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }

    private static function getCardStats(int $userId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT CASE WHEN status = 'known' THEN word_id END) AS known_words,
                COUNT(DISTINCT CASE WHEN status = 'learning' THEN word_id END) AS learning_words,
                COUNT(DISTINCT word_id) AS card_seen_words
            FROM word_card_progress
            WHERE user_id = ?
        ");

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return [
            'known_words' => (int)($row['known_words'] ?? 0),
            'learning_words' => (int)($row['learning_words'] ?? 0),
            'card_seen_words' => (int)($row['card_seen_words'] ?? 0),
        ];
    }

    private static function getTestStats(int $userId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(`right`), 0) AS right_count,
                COALESCE(SUM(`wrong`), 0) AS wrong_count,
                COALESCE(SUM(right_ru), 0) AS right_ru_count,
                COALESCE(SUM(wrong_ru), 0) AS wrong_ru_count
            FROM examination
            WHERE user_id = ?
        ");

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $right = (int)($row['right_count'] ?? 0);
        $wrong = (int)($row['wrong_count'] ?? 0);
        $rightRu = (int)($row['right_ru_count'] ?? 0);
        $wrongRu = (int)($row['wrong_ru_count'] ?? 0);

        return [
            'right' => $right,
            'wrong' => $wrong,
            'right_ru' => $rightRu,
            'wrong_ru' => $wrongRu,
            'total_correct' => $right + $rightRu,
            'total_answers' => $right + $wrong + $rightRu + $wrongRu,
        ];
    }

    private static function accuracy(int $right, int $wrong): float
    {
        $total = $right + $wrong;

        return $total > 0 ? round(($right / $total) * 100, 1) : 0;
    }
}
