<?php

require_once __DIR__ . '/../../config/db.php';

$config = require __DIR__ . '/../../config/db.php';
$tsvPath = __DIR__ . '/words_100_test.tsv';

$db = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

if ($db->connect_errno) {
    fwrite(STDERR, "Database connection error\n");
    exit(1);
}

$db->set_charset('utf8mb4');

$userResult = $db->query('SELECT id FROM users ORDER BY role_id ASC, id ASC LIMIT 1');
$user = $userResult ? $userResult->fetch_assoc() : null;

if (!$user) {
    fwrite(STDERR, "No user found for import user_id\n");
    exit(1);
}

$userId = (int)$user['id'];

$handle = fopen($tsvPath, 'rb');

if (!$handle) {
    fwrite(STDERR, "Cannot open TSV file\n");
    exit(1);
}

function slugifyTopic(string $topic): string
{
    $slug = strtolower(trim($topic));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

$headers = fgetcsv($handle, 0, "\t");

if ($headers === false) {
    fwrite(STDERR, "TSV file is empty\n");
    exit(1);
}

$findWord = $db->prepare('
    SELECT id
    FROM translate
    WHERE text = ?
      AND type = ?
      AND translate = ?
    LIMIT 1
');

$insertWord = $db->prepare('
    INSERT INTO translate
        (text, translate, type, level, transcription, example, example_ru, memory_hint, visible, visible_ru, date, user_id)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), ?)
');

$insertTopic = $db->prepare('
    INSERT IGNORE INTO topics (name, slug)
    VALUES (?, ?)
');

$findTopic = $db->prepare('
    SELECT id
    FROM topics
    WHERE slug = ?
    LIMIT 1
');

$insertWordTopic = $db->prepare('
    INSERT IGNORE INTO word_topics (word_id, topic_id)
    VALUES (?, ?)
');

$rows = 0;
$insertedWords = 0;
$skippedWords = 0;
$insertedTopics = 0;
$insertedLinks = 0;

$db->begin_transaction();

try {
    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    $db->query('TRUNCATE TABLE word_topics');
    $db->query('TRUNCATE TABLE topics');
    $db->query('TRUNCATE TABLE translate');
    $db->query('SET FOREIGN_KEY_CHECKS = 1');

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if (count($row) < 9) {
            continue;
        }

        $rows++;

        $text = trim($row[0]);
        $translate = trim($row[1]);
        $type = trim($row[2]);
        $level = trim($row[3]);
        $transcription = trim($row[4]);
        $example = trim($row[5]);
        $exampleRu = trim($row[6]);
        $memoryHint = trim($row[7]);
        $topicsRaw = trim($row[8]);

        $findWord->bind_param('sss', $text, $type, $translate);
        $findWord->execute();
        $existingWord = $findWord->get_result()->fetch_assoc();

        if ($existingWord) {
            $wordId = (int)$existingWord['id'];
            $skippedWords++;
        } else {
            $insertWord->bind_param(
                'ssssssssi',
                $text,
                $translate,
                $type,
                $level,
                $transcription,
                $example,
                $exampleRu,
                $memoryHint,
                $userId
            );
            $insertWord->execute();
            $wordId = (int)$insertWord->insert_id;
            $insertedWords++;
        }

        foreach (explode(',', $topicsRaw) as $topicName) {
            $topicName = trim($topicName);

            if ($topicName === '') {
                continue;
            }

            $slug = slugifyTopic($topicName);

            if ($slug === '') {
                continue;
            }

            $insertTopic->bind_param('ss', $topicName, $slug);
            $insertTopic->execute();

            if ($insertTopic->affected_rows > 0) {
                $insertedTopics++;
            }

            $findTopic->bind_param('s', $slug);
            $findTopic->execute();
            $topic = $findTopic->get_result()->fetch_assoc();

            if (!$topic) {
                throw new RuntimeException('Topic not found after insert: ' . $slug);
            }

            $topicId = (int)$topic['id'];

            $insertWordTopic->bind_param('ii', $wordId, $topicId);
            $insertWordTopic->execute();

            if ($insertWordTopic->affected_rows > 0) {
                $insertedLinks++;
            }
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    fclose($handle);
}

echo "user_id={$userId}\n";
echo "rows={$rows}\n";
echo "inserted_words={$insertedWords}\n";
echo "skipped_existing_words={$skippedWords}\n";
echo "inserted_topics={$insertedTopics}\n";
echo "inserted_word_topic_links={$insertedLinks}\n";
