<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

class Word
{

    public static function getAll(): array
    {
        $db = Database::connect();

        $userId = Auth::id();

        $stmt = $db->prepare("
            SELECT id, text, translate, type, example, example_ru
            FROM translate
            WHERE user_id = ?
            ORDER BY id DESC
        ");

        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $result = $stmt->get_result();

        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    public static function getAllByType(string $type = ''): array
    {
        $db = Database::connect();

        $userId = Auth::id();

        if (empty($type)) {
            return self::getAll();
        }

        $stmt = $db->prepare("
            SELECT id, text, translate, type, example, example_ru
            FROM translate
            WHERE user_id = ?
            AND type = ?
            ORDER BY id DESC
        ");

        $stmt->bind_param('is', $userId, $type);
        $stmt->execute();

        $result = $stmt->get_result();

        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    public static function getCount(string $type = '', string $level = '', string $topic = ''): int
    {
        $db = Database::connect();

        if (empty($type) && empty($level) && empty($topic)) {
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM translate
            ");

            $row = $result->fetch_assoc();

            return $row['count'] ?? 0;
        }

        $conditions = [];
        $types = '';
        $params = [];

        if (!empty($type)) {
            $conditions[] = 'type = ?';
            $types .= 's';
            $params[] = $type;
        }

        if (!empty($level)) {
            $conditions[] = 'level = ?';
            $types .= 's';
            $params[] = $level;
        }

        if (!empty($topic)) {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM word_topics wt_filter
                INNER JOIN topics topics_filter ON topics_filter.id = wt_filter.topic_id
                WHERE wt_filter.word_id = translate.id
                  AND topics_filter.slug = ?
            )";
            $types .= 's';
            $params[] = $topic;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM translate
            WHERE " . implode(' AND ', $conditions) . "
        ");
        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return $row['count'] ?? 0;
    }

    public static function getAllWithPagination(int $limit = 25, int $offset = 0, string $type = ''): array
    {
        $db = Database::connect();

        $userId = Auth::id();

        if (empty($type)) {
            $stmt = $db->prepare("
                SELECT id, text, translate, type, example, example_ru
                FROM translate
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('iii', $userId, $limit, $offset);
        } else {
            $stmt = $db->prepare("
                SELECT id, text, translate, type, example, example_ru
                FROM translate
                WHERE user_id = ?
                AND type = ?
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('isii', $userId, $type, $limit, $offset);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    public static function getAllWithStatsPagination(int $limit = 25, int $offset = 0, string $type = '', string $level = '', string $topic = ''): array
    {
        $db = Database::connect();

        $userId = Auth::id();
        $conditions = [];
        $bindTypes = 'i';
        $bindParams = [$userId];

        if (!empty($type)) {
            $conditions[] = 't.type = ?';
            $bindTypes .= 's';
            $bindParams[] = $type;
        }

        if (!empty($level)) {
            $conditions[] = 't.level = ?';
            $bindTypes .= 's';
            $bindParams[] = $level;
        }

        if (!empty($topic)) {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM word_topics wt_filter
                INNER JOIN topics topics_filter ON topics_filter.id = wt_filter.topic_id
                WHERE wt_filter.word_id = t.id
                  AND topics_filter.slug = ?
            )";
            $bindTypes .= 's';
            $bindParams[] = $topic;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $bindTypes .= 'ii';
        $bindParams[] = $limit;
        $bindParams[] = $offset;

        $stmt = $db->prepare("
            SELECT
                t.id,
                t.text,
                t.translate,
                t.type,
                t.level,
                t.transcription,
                t.example,
                t.example_ru,
                t.memory_hint,
                t.visible,
                t.visible_ru,
                COALESCE(e.right, 0) AS right_count,
                COALESCE(e.wrong, 0) AS wrong_count,
                COALESCE(e.right_ru, 0) AS right_ru_count,
                COALESCE(e.wrong_ru, 0) AS wrong_ru_count,
                GROUP_CONCAT(DISTINCT topics.name ORDER BY topics.name SEPARATOR ', ') AS topics
            FROM translate t
            LEFT JOIN examination e ON e.word_id = t.id AND e.user_id = ?
            LEFT JOIN word_topics wt ON wt.word_id = t.id
            LEFT JOIN topics ON topics.id = wt.topic_id
            {$where}
            GROUP BY
                t.id,
                t.text,
                t.translate,
                t.type,
                t.level,
                t.transcription,
                t.example,
                t.example_ru,
                t.memory_hint,
                t.visible,
                t.visible_ru,
                e.right,
                e.wrong,
                e.right_ru,
                e.wrong_ru
            ORDER BY t.text ASC, t.id ASC
            LIMIT ? OFFSET ?
        ");

        $stmt->bind_param($bindTypes, ...$bindParams);

        $stmt->execute();

        $result = $stmt->get_result();

        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();

        $userId = Auth::id();

        $stmt = $db->prepare("
            SELECT id, text, translate, type, example, example_ru, date
            FROM translate
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $word = $result->fetch_assoc();

        $stmt->close();

        return $word ?: null;
    }  

    public static function create(string $text, string $translate, int $userId, string $type = '', string $example = '', string $example_ru = ''): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO translate (text, translate, type, example, example_ru, user_id, date)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param('sssssi', $text, $translate, $type, $example, $example_ru, $userId);
        $stmt->execute();

        $id = $stmt->insert_id;

        $stmt->close();

        return $id;
    }

    public static function existsByText(string $text, int $userId): bool
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT id
            FROM translate
            WHERE text = ?
            AND user_id = ?
            LIMIT 1
        ");

        $stmt->bind_param('si', $text, $userId);
        $stmt->execute();
        $stmt->store_result();

        $exists = $stmt->num_rows > 0;

        $stmt->close();

        return $exists;
    }
    
    public static function delete(int $id): bool
    {
        $db = Database::connect();

        $userId = Auth::id();

        $stmt = $db->prepare("
            DELETE FROM translate
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();

        $deleted = $stmt->affected_rows > 0;

        $stmt->close();

        return $deleted;
    }  

    public static function update(int $id, string $text, string $translate, string $type = '', string $example = '', string $example_ru = ''): bool
    {
        $db = Database::connect();

        $userId = Auth::id();

        $stmt = $db->prepare("
            UPDATE translate
            SET text = ?, translate = ?, type = ?, example = ?, example_ru = ?, date = NOW()
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param('sssssii', $text, $translate, $type, $example, $example_ru, $id, $userId);
        $stmt->execute();

        $updated = $stmt->affected_rows >= 0;

        $stmt->close();

        return $updated;
    }

    public static function toggleVisibility(int $id): bool
    {
        $db = Database::connect();

        $userId = Auth::id();

        $stmt = $db->prepare("
            UPDATE translate
            SET visible = IF(visible = 1, 0, 1),
                visible_ru = IF(visible_ru = 1, 0, 1)
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();

        $updated = $stmt->affected_rows > 0;

        $stmt->close();

        return $updated;
    }

    public static function search(string $query): array
    {
        $db = Database::connect();

        $searchTerm = '%' . $query . '%';

        $stmt = $db->prepare("
            SELECT id, text, translate, type
            FROM translate
            WHERE text LIKE ? OR translate LIKE ?
            ORDER BY text ASC
            LIMIT 20
        ");

        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();

        $result = $stmt->get_result();

        $words = [];

        while ($row = $result->fetch_assoc()) {
            $words[] = $row;
        }

        $stmt->close();

        return $words;
    }

    public static function getLevels(): array
    {
        $db = Database::connect();

        $result = $db->query("
            SELECT DISTINCT level
            FROM translate
            WHERE level IS NOT NULL
              AND level != ''
            ORDER BY level ASC
        ");

        $levels = [];

        while ($row = $result->fetch_assoc()) {
            $levels[] = $row['level'];
        }

        return $levels;
    }

    public static function getTypes(): array
    {
        $db = Database::connect();

        $result = $db->query("
            SELECT DISTINCT type
            FROM translate
            WHERE type IS NOT NULL
              AND type != ''
            ORDER BY type ASC
        ");

        $types = [];

        while ($row = $result->fetch_assoc()) {
            $types[] = $row['type'];
        }

        return $types;
    }

    public static function getTopics(): array
    {
        $db = Database::connect();

        $result = $db->query("
            SELECT id, name, slug
            FROM topics
            ORDER BY name ASC
        ");

        $topics = [];

        while ($row = $result->fetch_assoc()) {
            $topics[] = $row;
        }

        return $topics;
    }

    public static function findReaderWords(array $texts): array
    {
        $texts = array_values(array_unique(array_filter(array_map(
            fn($text) => strtolower(trim((string)$text)),
            $texts
        ))));

        if (empty($texts)) {
            return [];
        }

        $db = Database::connect();
        $placeholders = implode(', ', array_fill(0, count($texts), '?'));

        $stmt = $db->prepare("
            SELECT text, transcription, translate, type, level, example, example_ru, memory_hint
            FROM translate
            WHERE LOWER(text) IN ({$placeholders})
            ORDER BY text ASC, id ASC
        ");

        $types = str_repeat('s', count($texts));
        $stmt->bind_param($types, ...$texts);
        $stmt->execute();

        $result = $stmt->get_result();
        $words = [];

        while ($row = $result->fetch_assoc()) {
            $key = strtolower($row['text']);
            $words[$key][] = $row;
        }

        $stmt->close();

        $missingTexts = array_values(array_filter($texts, fn(string $text) => empty($words[$text])));

        if (empty($missingTexts)) {
            return $words;
        }

        $formPlaceholders = implode(', ', array_fill(0, count($missingTexts), '?'));

        $stmt = $db->prepare("
            SELECT
                LOWER(wf.form) AS form,
                t.text,
                t.transcription,
                t.translate,
                t.type,
                t.level,
                t.example,
                t.example_ru,
                t.memory_hint
            FROM word_forms wf
            JOIN translate t ON t.id = wf.word_id
            WHERE LOWER(wf.form) IN ({$formPlaceholders})
            ORDER BY wf.form ASC, t.text ASC, t.id ASC
        ");

        $formTypes = str_repeat('s', count($missingTexts));
        $stmt->bind_param($formTypes, ...$missingTexts);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $key = $row['form'];
            unset($row['form']);
            $words[$key][] = $row;
        }

        $stmt->close();

        return $words;
    }
    
}

