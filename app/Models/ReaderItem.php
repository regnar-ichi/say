<?php

namespace App\Models;

use App\Core\Database;

class ReaderItem
{
    public static function getAllForUser(int $userId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                ri.id,
                ri.source_type,
                ri.title,
                ri.source_url,
                ri.source_filename,
                ri.cover_path,
                ri.created_at,
                ri.updated_at,
                rip.page_number AS progress_page_number,
                COUNT(rp.id) AS pages_count
            FROM reader_items ri
            LEFT JOIN reader_pages rp ON rp.item_id = ri.id
            LEFT JOIN reader_item_progress rip ON rip.item_id = ri.id
                AND rip.user_id = ?
            WHERE ri.user_id = ?
            GROUP BY
                ri.id,
                ri.source_type,
                ri.title,
                ri.source_url,
                ri.source_filename,
                ri.cover_path,
                ri.created_at,
                ri.updated_at,
                rip.page_number
            ORDER BY ri.updated_at DESC, ri.id DESC
        ");

        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $stmt->close();

        return $items;
    }

    public static function createManualText(int $userId, string $title, string $content): int
    {
        $db = Database::connect();
        $db->begin_transaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO reader_items (user_id, source_type, title, created_at, updated_at)
                VALUES (?, 'manual_text', ?, NOW(), NOW())
            ");

            $stmt->bind_param('is', $userId, $title);
            $stmt->execute();

            $itemId = $stmt->insert_id;
            $stmt->close();

            $pageTitle = $title;
            $pageNumber = 1;

            $stmt = $db->prepare("
                INSERT INTO reader_pages (item_id, page_number, title, content, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->bind_param('iiss', $itemId, $pageNumber, $pageTitle, $content);
            $stmt->execute();
            $stmt->close();

            $db->commit();

            return $itemId;
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    public static function createFb2Book(int $userId, string $title, string $filename, array $pages, ?string $coverPath = null): int
    {
        $pages = array_values(array_filter($pages, static function (array $page): bool {
            return trim((string) ($page['content'] ?? '')) !== '';
        }));

        if (empty($pages)) {
            throw new \InvalidArgumentException('Reader item must contain at least one page.');
        }

        $db = Database::connect();
        $db->begin_transaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO reader_items (user_id, source_type, title, source_filename, cover_path, created_at, updated_at)
                VALUES (?, 'fb2', ?, ?, ?, NOW(), NOW())
            ");

            $stmt->bind_param('isss', $userId, $title, $filename, $coverPath);
            $stmt->execute();

            $itemId = $stmt->insert_id;
            $stmt->close();

            $stmt = $db->prepare("
                INSERT INTO reader_pages (item_id, page_number, title, content, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            foreach ($pages as $index => $page) {
                $pageNumber = $index + 1;
                $pageTitle = trim((string) ($page['title'] ?? ''));
                $content = trim((string) ($page['content'] ?? ''));

                if ($content === '') {
                    continue;
                }

                if ($pageTitle === '') {
                    $pageTitle = $title;
                }

                $stmt->bind_param('iiss', $itemId, $pageNumber, $pageTitle, $content);
                $stmt->execute();
            }

            $stmt->close();

            $db->commit();

            return $itemId;
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    public static function findPageForUser(int $userId, int $itemId, int $pageNumber = 1): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                ri.id AS item_id,
                ri.title AS item_title,
                ri.source_type,
                ri.source_filename,
                rp.id AS page_id,
                rp.page_number,
                rp.title AS page_title,
                rp.content,
                (
                    SELECT COUNT(*)
                    FROM reader_pages rp_count
                    WHERE rp_count.item_id = ri.id
                ) AS pages_count
            FROM reader_items ri
            INNER JOIN reader_pages rp ON rp.item_id = ri.id
            WHERE ri.user_id = ?
              AND ri.id = ?
              AND rp.page_number = ?
            LIMIT 1
        ");

        $stmt->bind_param('iii', $userId, $itemId, $pageNumber);
        $stmt->execute();

        $result = $stmt->get_result();
        $page = $result->fetch_assoc();

        $stmt->close();

        return $page ?: null;
    }

    public static function saveProgressForUser(int $userId, int $itemId, int $pageNumber): void
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO reader_item_progress (user_id, item_id, page_number, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                page_number = VALUES(page_number),
                updated_at = NOW()
        ");

        $stmt->bind_param('iii', $userId, $itemId, $pageNumber);
        $stmt->execute();
        $stmt->close();
    }

    public static function deleteForUser(int $userId, int $itemId): bool
    {
        $db = Database::connect();
        $db->begin_transaction();

        try {
            $stmt = $db->prepare("
                SELECT id, cover_path
                FROM reader_items
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param('ii', $itemId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();

            if (!$item) {
                $stmt->close();
                $db->rollback();
                return false;
            }

            $stmt->close();
            $coverPath = (string) ($item['cover_path'] ?? '');

            $stmt = $db->prepare("
                DELETE FROM reader_item_progress
                WHERE item_id = ?
            ");

            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("
                DELETE FROM reader_pages
                WHERE item_id = ?
            ");

            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("
                DELETE FROM reader_items
                WHERE id = ?
                  AND user_id = ?
            ");

            $stmt->bind_param('ii', $itemId, $userId);
            $stmt->execute();

            $deleted = $stmt->affected_rows > 0;
            $stmt->close();

            $db->commit();

            if ($deleted) {
                self::deleteCoverFile($coverPath);
            }

            return $deleted;
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    private static function deleteCoverFile(string $coverPath): void
    {
        $prefix = '/uploads/reader-covers/';

        if (!str_starts_with($coverPath, $prefix) || str_contains($coverPath, '..')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/public' . $coverPath;

        if (is_file($path)) {
            unlink($path);
        }
    }
}
