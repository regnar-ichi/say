<?php

namespace App\Models;

use App\Core\Database;

class WordCard
{
    public static function getCards(
        int $userId,
        string $direction,
        int $limit,
        array $levels = [],
        array $types = [],
        array $topics = []
    ): array {
        $db = Database::connect();
        $where = [
            "NOT EXISTS (
                SELECT 1
                FROM word_card_progress wcp_known
                WHERE wcp_known.word_id = t.id
                  AND wcp_known.user_id = ?
                  AND wcp_known.status = 'known'
            )",
        ];
        $params = [$userId];
        $paramTypes = 'i';

        if ($direction === 'translate') {
            $where[] = 't.visible = 1';
        } elseif ($direction === 'text') {
            $where[] = 't.visible_ru = 1';
        } else {
            $where[] = '(t.visible = 1 OR t.visible_ru = 1)';
        }

        if (!empty($levels)) {
            $where[] = 't.level IN (' . implode(', ', array_fill(0, count($levels), '?')) . ')';
            foreach ($levels as $level) {
                $params[] = $level;
                $paramTypes .= 's';
            }
        }

        if (!empty($types)) {
            $where[] = 't.type IN (' . implode(', ', array_fill(0, count($types), '?')) . ')';
            foreach ($types as $type) {
                $params[] = $type;
                $paramTypes .= 's';
            }
        }

        if (!empty($topics)) {
            $where[] = "
                EXISTS (
                    SELECT 1
                    FROM word_topics wt_filter
                    INNER JOIN topics topics_filter ON topics_filter.id = wt_filter.topic_id
                    WHERE wt_filter.word_id = t.id
                      AND topics_filter.slug IN (" . implode(', ', array_fill(0, count($topics), '?')) . ")
                )
            ";

            foreach ($topics as $topic) {
                $params[] = $topic;
                $paramTypes .= 's';
            }
        }

        $params[] = $limit;
        $paramTypes .= 'i';

        $stmt = $db->prepare("
            SELECT
                t.id,
                t.text,
                t.translate,
                t.transcription,
                t.type,
                t.level,
                t.example,
                t.example_ru,
                t.memory_hint,
                GROUP_CONCAT(DISTINCT topics.name ORDER BY topics.name SEPARATOR ', ') AS topics
            FROM translate t
            LEFT JOIN word_topics wt ON wt.word_id = t.id
            LEFT JOIN topics ON topics.id = wt.topic_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY
                t.id,
                t.text,
                t.translate,
                t.transcription,
                t.type,
                t.level,
                t.example,
                t.example_ru,
                t.memory_hint
            ORDER BY RAND()
            LIMIT ?
        ");

        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $cards = [];

        while ($row = $result->fetch_assoc()) {
            $cards[] = $row;
        }

        $stmt->close();

        return $cards;
    }

    public static function mark(int $userId, int $wordId, string $status): bool
    {
        if (!in_array($status, ['known', 'learning'], true)) {
            return false;
        }

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT id
            FROM translate
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $wordId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            return false;
        }

        $stmt->close();

        $stmt = $db->prepare("
            INSERT INTO word_card_progress (user_id, word_id, status, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                updated_at = NOW()
        ");

        $stmt->bind_param('iis', $userId, $wordId, $status);
        $stmt->execute();

        $saved = $stmt->affected_rows >= 0;
        $stmt->close();

        return $saved;
    }
}
