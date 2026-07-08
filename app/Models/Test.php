<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

class Test
{
    public static function getExaminedWords(): array
    {
        $db = Database::connect();
        $userId = Auth::id();

        $stmt = $db->prepare("
            SELECT
                t.id,
                t.text,
                t.translate,
                t.type,
                t.example,
                t.example_ru,
                t.visible,
                t.visible_ru,
                COALESCE(e.right, 0) AS right_count,
                COALESCE(e.wrong, 0) AS wrong_count,
                COALESCE(e.right_ru, 0) AS right_ru_count,
                COALESCE(e.wrong_ru, 0) AS wrong_ru_count
            FROM translate t
            JOIN examination e ON e.word_id = t.id AND e.user_id = ?
            WHERE t.user_id = ?
            ORDER BY (COALESCE(e.wrong, 0) + COALESCE(e.wrong_ru, 0)) DESC, t.id DESC
        ");

        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    /**
     * Get random test words based on direction
     * direction: 'translate' (english -> russian) or 'text' (russian -> english)
     */
    public static function getTestWords(string $direction, int $limit, array $levels = [], array $types = [], array $topics = []): array
    {
        $db = Database::connect();
        $where = [];
        $params = [];
        $paramTypes = '';

        if ($direction === 'translate') {
            $wordColumn = 'text';
            $where[] = 'visible = 1';
        } else {
            $wordColumn = 'translate';
            $where[] = 'visible_ru = 1';
        }

        if (!empty($levels)) {
            $where[] = 'level IN (' . implode(', ', array_fill(0, count($levels), '?')) . ')';
            foreach ($levels as $level) {
                $params[] = $level;
                $paramTypes .= 's';
            }
        }

        if (!empty($types)) {
            $where[] = 'type IN (' . implode(', ', array_fill(0, count($types), '?')) . ')';
            foreach ($types as $type) {
                $params[] = $type;
                $paramTypes .= 's';
            }
        }

        if (!empty($topics)) {
            $where[] = '
                EXISTS (
                    SELECT 1
                    FROM word_topics wt
                    JOIN topics tp ON tp.id = wt.topic_id
                    WHERE wt.word_id = translate.id
                      AND tp.slug IN (' . implode(', ', array_fill(0, count($topics), '?')) . ')
                )
            ';

            foreach ($topics as $topic) {
                $params[] = $topic;
                $paramTypes .= 's';
            }
        }

        $sql = "
            SELECT id, {$wordColumn} AS word, type, example
            FROM translate
            WHERE " . implode(' AND ', $where) . "
            ORDER BY RAND()
            LIMIT ?
        ";

        $params[] = $limit;
        $paramTypes .= 'i';

        $stmt = $db->prepare($sql);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    /**
     * Get full word data for answer checking
     */
    public static function getWordForChecking(int $id): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT id, text, translate
            FROM translate
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $word = $result->fetch_assoc();

        $stmt->close();

        return $word ?: null;
    }

    /**
     * Fuzzy match answer with expected values
     * Supports comma-separated answers, case insensitive, removes special chars
     */
    public static function fuzzyMatch(string $answer, string $expected, int $threshold = 70): bool
    {
        if (self::matchesExpectedVariant($answer, $expected)) {
            return true;
        }

        // Normalize both strings
        $answer = self::normalizeString($answer);
        $expectedParts = self::splitExpectedVariants($expected);

        if (empty($answer) || empty($expectedParts)) {
            return false;
        }

        // Use similar_text for fuzzy matching as a fallback for existing behavior.
        foreach (self::splitExpectedVariants($answer) as $part) {
            foreach ($expectedParts as $exp) {
                $similarity = 0;
                similar_text($part, $exp, $similarity);
                if ($similarity >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize string for comparison
     * - lowercase
     * - remove special characters
     * - trim whitespace
     */
    private static function normalizeString(string $str): string
    {
        // Convert to lowercase
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace('ё', 'е', $str);

        // Remove punctuation but keep letters, numbers and spaces.
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $str);

        // Trim and compress spaces
        $str = trim(preg_replace('/\s+/', ' ', $str));

        return $str;
    }

    private static function matchesExpectedVariant(string $answer, string $expected): bool
    {
        $answerVariants = self::splitExpectedVariants($answer);
        $expectedVariants = self::splitExpectedVariants($expected);

        if (empty($answerVariants) || empty($expectedVariants)) {
            return false;
        }

        foreach ($answerVariants as $answerVariant) {
            foreach ($expectedVariants as $expectedVariant) {
                if ($answerVariant === $expectedVariant) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function splitExpectedVariants(string $value): array
    {
        $parts = preg_split('/[;,\/|]+/u', $value) ?: [];
        $variants = [];

        foreach ($parts as $part) {
            $normalized = self::normalizeString($part);

            if ($normalized !== '') {
                $variants[] = $normalized;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Check test answers and calculate results
     * $answers = [{"id": 12, "value": "точность"}, ...]
     * $direction = 'translate' or 'text'
     */
    public static function checkAnswers(array $answers, string $direction): array
    {
        $userId = Auth::id();
        $correct = 0;
        $wrong = 0;
        $details = [];

        foreach ($answers as $answer) {
            $id = (int) ($answer['id'] ?? 0);
            $value = trim($answer['value'] ?? '');

            if ($id <= 0) {
                continue;
            }

            $word = self::getWordForChecking($id);

            if (!$word) {
                $wrong++;
                $details[] = [
                    'id' => $id,
                    'status' => 'wrong',
                    'reason' => 'word_not_found'
                ];
                continue;
            }

            // Determine expected answer based on direction
            if ($direction === 'translate') {
                // english -> russian: check against translate field
                $expected = $word['translate'];
                $shown = $word['text'];
            } else {
                // russian -> english: check against text field
                $expected = $word['text'];
                $shown = $word['translate'];
            }

            // Perform fuzzy match
            if (self::fuzzyMatch($value, $expected)) {
                $correct++;
                $details[] = [
                    'id' => $id,
                    'status' => 'correct',
                    'shown' => $shown,
                    'answer' => $value,
                    'expected' => $expected
                ];
            } else {
                $wrong++;
                $details[] = [
                    'id' => $id,
                    'status' => 'wrong',
                    'shown' => $shown,
                    'answer' => $value,
                    'expected' => $expected
                ];
            }
        }

        return [
            'correct' => $correct,
            'wrong' => $wrong,
            'total' => count($answers),
            'details' => $details
        ];
    }

    /**
     * Update examination table
     * Structure: word_id, met, right, wrong, met_ru, right_ru, wrong_ru, user_id
     * direction 'translate' (eng->ru): use met, right, wrong
     * direction 'text' (ru->eng): use met_ru, right_ru, wrong_ru
     */
    public static function updateExamination(array $result, string $direction): void
    {
        $db = Database::connect();
        $userId = Auth::id();

        // Select field names based on direction (whitelist)
        if ($direction === 'text') {
            $metField = 'met_ru';
            $rightField = 'right_ru';
            $wrongField = 'wrong_ru';
        } else {
            $metField = 'met';
            $rightField = 'right';
            $wrongField = 'wrong';
        }

        foreach ($result['details'] as $detail) {
            $wordId = $detail['id'];
            $isCorrect = $detail['status'] === 'correct' ? 1 : 0;
            $isWrong = $detail['status'] === 'correct' ? 0 : 1;

            // Check if record exists
            $checkStmt = $db->prepare("
                SELECT id FROM examination
                WHERE word_id = ? AND user_id = ?
                LIMIT 1
            ");

            $checkStmt->bind_param('ii', $wordId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $exists = $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                // Update existing record with backtick-escaped field names
                $sql = "
                    UPDATE examination
                    SET `{$metField}` = `{$metField}` + 1,
                        `{$rightField}` = `{$rightField}` + ?,
                        `{$wrongField}` = `{$wrongField}` + ?
                    WHERE word_id = ? AND user_id = ?
                ";
                $updateStmt = $db->prepare($sql);
                $updateStmt->bind_param('iiii', $isCorrect, $isWrong, $wordId, $userId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Create new record - initialize with 0s and set appropriate counter
                if ($direction === 'text') {
                    // Russian -> English: initialize met_ru=1, met=0
                    $insertStmt = $db->prepare("
                        INSERT INTO examination (word_id, user_id, `met`, `right`, `wrong`, `met_ru`, `right_ru`, `wrong_ru`)
                        VALUES (?, ?, 0, 0, 0, 1, ?, ?)
                    ");
                    $insertStmt->bind_param('iiii', $wordId, $userId, $isCorrect, $isWrong);
                } else {
                    // English -> Russian: initialize met=1, met_ru=0
                    $insertStmt = $db->prepare("
                        INSERT INTO examination (word_id, user_id, `met`, `right`, `wrong`, `met_ru`, `right_ru`, `wrong_ru`)
                        VALUES (?, ?, 1, ?, ?, 0, 0, 0)
                    ");
                    $insertStmt->bind_param('iiii', $wordId, $userId, $isCorrect, $isWrong);
                }

                $insertStmt->execute();
                $insertStmt->close();
            }
        }
    }

    /**
     * Update statistic table (for tracking overall progress)
     */
    public static function updateStatistic(array $result): void
    {
        $db = Database::connect();
        $userId = Auth::id();

        $count = (int)($result['total'] ?? 0);
        $right = (int)($result['correct'] ?? 0);
        $wrong = (int)($result['wrong'] ?? 0);
        $type = (($result['direction'] ?? 'translate') === 'text') ? 1 : 0;

        $stmt = $db->prepare("
            INSERT INTO `statistic` (`count`, `right`, `wrong`, `type`, `user_id`)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param('iiiii', $count, $right, $wrong, $type, $userId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Update tests table (overall test history)
     * Structure: id, test, test_ru, date, user_id
     * direction 'translate' (eng->ru): increment `test`
     * direction 'text' (ru->eng): increment `test_ru`
     */
    public static function updateTests(array $result, string $direction): void
    {
        $db = Database::connect();
        $userId = Auth::id();

        // Select field name based on direction (whitelist)
        $testField = ($direction === 'text') ? 'test_ru' : 'test';

        // Check if record exists for today
        $checkStmt = $db->prepare("
            SELECT id FROM tests
            WHERE user_id = ?
            AND DATE(`date`) = CURDATE()
            LIMIT 1
        ");

        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $exists = $checkResult->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            // Update existing record - increment appropriate test counter
            $sql = "
                UPDATE tests
                SET `{$testField}` = `{$testField}` + 1
                WHERE user_id = ? AND DATE(`date`) = CURDATE()
            ";
            $updateStmt = $db->prepare($sql);
            $updateStmt->bind_param('i', $userId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Create new record for today
            if ($direction === 'text') {
                // Russian -> English: set test_ru=1, test=0
                $insertStmt = $db->prepare("
                    INSERT INTO tests (user_id, `test`, `test_ru`, `date`)
                    VALUES (?, 0, 1, NOW())
                ");
                $insertStmt->bind_param('i', $userId);
            } else {
                // English -> Russian: set test=1, test_ru=0
                $insertStmt = $db->prepare("
                    INSERT INTO tests (user_id, `test`, `test_ru`, `date`)
                    VALUES (?, 1, 0, NOW())
                ");
                $insertStmt->bind_param('i', $userId);
            }

            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}
