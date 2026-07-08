<?php

namespace App\Models;

use App\Core\Database;

class HomeDashboard
{
    public static function getTotalTestWords(): int
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

    public static function getUserProgressRows(): array
    {
        $db = Database::connect();
        $totalWords = self::getTotalTestWords();

        $result = $db->query("
            SELECT
                u.id,
                u.login,
                COALESCE(progress.progress_words, 0) AS progress_words,
                COALESCE(accuracy.correct_answers, 0) AS correct_answers,
                COALESCE(accuracy.total_answers, 0) AS total_answers
            FROM users u
            LEFT JOIN (
                SELECT user_id, COUNT(DISTINCT word_id) AS progress_words
                FROM (
                    SELECT user_id, word_id
                    FROM examination
                    UNION
                    SELECT user_id, word_id
                    FROM word_card_progress
                    WHERE status = 'known'
                ) progress_source
                GROUP BY user_id
            ) progress ON progress.user_id = u.id
            LEFT JOIN (
                SELECT
                    user_id,
                    SUM(COALESCE(`right`, 0) + COALESCE(right_ru, 0)) AS correct_answers,
                    SUM(COALESCE(`right`, 0) + COALESCE(`wrong`, 0) + COALESCE(right_ru, 0) + COALESCE(wrong_ru, 0)) AS total_answers
                FROM examination
                GROUP BY user_id
            ) accuracy ON accuracy.user_id = u.id
            ORDER BY progress_words DESC, u.login ASC
        ");

        $rows = [];

        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $progressWords = (int)$row['progress_words'];
            $totalAnswers = (int)$row['total_answers'];
            $correctAnswers = (int)$row['correct_answers'];

            $rows[] = [
                'id' => (int)$row['id'],
                'login' => (string)$row['login'],
                'progress_words' => $progressWords,
                'total_words' => $totalWords,
                'progress_percent' => $totalWords > 0 ? round(($progressWords / $totalWords) * 100, 1) : 0,
                'accuracy_percent' => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0,
            ];
        }

        return $rows;
    }
}
