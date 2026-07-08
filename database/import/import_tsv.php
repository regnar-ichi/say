<?php

require_once __DIR__ . '/../../config/db.php';

const IMPORT_MODES = ['dry-run', 'create_new_only', 'update_selected_fields'];
const REQUIRED_COLUMNS = ['source', 'source_key', 'text', 'translate'];
const KNOWN_COLUMNS = [
    'source',
    'source_key',
    'text',
    'translate',
    'type',
    'level',
    'transcription',
    'example',
    'example_ru',
    'memory_hint',
    'topics',
    'forms',
];

function usage(): void
{
    echo "Usage:\n";
    echo "  php database/import/import_tsv.php --file=database/import/words.tsv --mode=dry-run --batch=batch_id\n";
    echo "  php database/import/import_tsv.php --file=database/import/words.tsv --mode=create_new_only --batch=batch_id\n";
    echo "  php database/import/import_tsv.php --file=database/import/words.tsv --mode=update_selected_fields --dry-run=1\n";
    echo "  php database/import/import_tsv.php --file=database/import/words.tsv --mode=update_selected_fields\n";
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function optionValue(array $options, string $name, ?string $default = null): ?string
{
    $value = $options[$name] ?? $default;

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function optionEnabled(array $options, string $name): bool
{
    if (!array_key_exists($name, $options)) {
        return false;
    }

    $value = $options[$name];

    if ($value === false) {
        return true;
    }

    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function normalizeText(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value));
}

function normalizeNullable(string $value): ?string
{
    $value = normalizeText($value);

    return $value === '' ? null : $value;
}

function slugifyTopic(string $topic): string
{
    $slug = strtolower(trim($topic));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-');
}

function splitList(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $items = [];

    foreach (explode(',', $value) as $item) {
        $item = normalizeText($item);

        if ($item !== '') {
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}

function importHash(array $row): string
{
    $parts = [];

    foreach (KNOWN_COLUMNS as $column) {
        $parts[$column] = $row[$column] ?? '';
    }

    return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$options = getopt('', ['file:', 'mode::', 'batch::', 'dry-run::', 'help']);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$file = optionValue($options, 'file');
$mode = optionValue($options, 'mode', 'dry-run');
$batch = optionValue($options, 'batch', 'manual_' . date('Ymd_His'));
$isUpdateDryRun = $mode === 'update_selected_fields' && optionEnabled($options, 'dry-run');

if ($file === null) {
    usage();
    fail('Missing required --file option');
}

if (!in_array($mode, IMPORT_MODES, true)) {
    fail('Unsupported mode: ' . $mode);
}

$path = $file;

if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path;
}

if (!is_file($path)) {
    fail('TSV file not found: ' . $path);
}

$handle = fopen($path, 'rb');

if (!$handle) {
    fail('Cannot open TSV file: ' . $path);
}

$headers = fgetcsv($handle, 0, "\t");

if ($headers === false) {
    fail('TSV file is empty');
}

$headers = array_map(static function ($header): string {
    $header = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header);

    return trim($header, " \t\n\r\0\x0B\"");
}, $headers);

$headerMap = array_flip($headers);

if ($mode === 'update_selected_fields') {
    if (!array_key_exists('memory_hint', $headerMap)) {
        fail('Missing required column: memory_hint');
    }

    $hasSourceLookup = array_key_exists('source', $headerMap) && array_key_exists('source_key', $headerMap);
    $hasFallbackLookup = array_key_exists('text', $headerMap)
        && array_key_exists('type', $headerMap)
        && array_key_exists('translate', $headerMap);

    if (!$hasSourceLookup && !$hasFallbackLookup) {
        fail('Missing lookup columns: provide source + source_key or text + type + translate');
    }
} else {
    foreach (REQUIRED_COLUMNS as $column) {
        if (!array_key_exists($column, $headerMap)) {
            fail('Missing required column: ' . $column);
        }
    }
}

$config = require __DIR__ . '/../../config/db.php';
$db = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

if ($db->connect_errno) {
    fail('Database connection error');
}

$db->set_charset('utf8mb4');

$findBySource = $db->prepare('
    SELECT id
    FROM translate
    WHERE source = ?
      AND source_key = ?
    LIMIT 1
');

$findByFallback = $db->prepare('
    SELECT id
    FROM translate
    WHERE LOWER(text) = LOWER(?)
      AND LOWER(COALESCE(type, "")) = LOWER(?)
      AND LOWER(translate) = LOWER(?)
    LIMIT 1
');

$insertWord = $db->prepare('
    INSERT INTO translate
        (
            text,
            translate,
            type,
            level,
            transcription,
            example,
            example_ru,
            memory_hint,
            visible,
            visible_ru,
            date,
            user_id,
            source,
            source_key,
            import_batch,
            import_hash,
            imported_at,
            import_updated_at
        )
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NULL, ?, ?, ?, ?, NOW(), NOW())
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

$insertWordForm = $db->prepare('
    INSERT IGNORE INTO word_forms (word_id, form)
    VALUES (?, ?)
');

$updateMemoryHint = $db->prepare('
    UPDATE translate
    SET memory_hint = ?,
        import_updated_at = NOW()
    WHERE id = ?
');

$stats = [
    'mode' => $mode,
    'batch' => $batch,
    'rows' => 0,
    'valid_rows' => 0,
    'would_insert_words' => 0,
    'inserted_words' => 0,
    'skipped_existing_words' => 0,
    'would_create_topics' => 0,
    'created_topics' => 0,
    'would_link_topics' => 0,
    'linked_topics' => 0,
    'would_add_forms' => 0,
    'added_forms' => 0,
    'would_update_memory_hint' => 0,
    'updated_memory_hint' => 0,
    'skipped_not_found' => 0,
    'skipped_empty_memory_hint' => 0,
    'errors' => 0,
];

$existingTopicSlugs = [];
$topicResult = $db->query('SELECT slug FROM topics');

while ($topic = $topicResult->fetch_assoc()) {
    $existingTopicSlugs[$topic['slug']] = true;
}

if ($mode === 'create_new_only' || ($mode === 'update_selected_fields' && !$isUpdateDryRun)) {
    $db->begin_transaction();
}

try {
    while (($values = fgetcsv($handle, 0, "\t")) !== false) {
        $stats['rows']++;

        $row = [];

        foreach ($headers as $index => $column) {
            $row[$column] = isset($values[$index]) ? trim((string)$values[$index]) : '';
        }

        if (function_exists('mb_check_encoding')) {
            foreach ($row as $column => $value) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $stats['errors']++;
                    fwrite(STDERR, 'Invalid row ' . $stats['rows'] . ': column ' . $column . ' is not valid UTF-8' . PHP_EOL);
                    continue 2;
                }
            }
        }

        $source = normalizeText($row['source'] ?? '');
        $sourceKey = normalizeText($row['source_key'] ?? '');
        $text = normalizeText($row['text'] ?? '');
        $translate = normalizeText($row['translate'] ?? '');
        $type = normalizeNullable($row['type'] ?? '');
        $level = normalizeNullable($row['level'] ?? '');
        $transcription = normalizeNullable($row['transcription'] ?? '');
        $example = normalizeNullable($row['example'] ?? '');
        $exampleRu = normalizeNullable($row['example_ru'] ?? '');
        $memoryHint = normalizeNullable($row['memory_hint'] ?? '');
        $topics = splitList($row['topics'] ?? '');
        $forms = splitList($row['forms'] ?? '');

        if ($mode === 'update_selected_fields') {
            $hasSourceLookup = $source !== '' && $sourceKey !== '';
            $hasFallbackLookup = $text !== '' && $type !== null && $translate !== '';

            if (!$hasSourceLookup && !$hasFallbackLookup) {
                $stats['errors']++;
                fwrite(STDERR, 'Invalid row ' . $stats['rows'] . ': provide source + source_key or text + type + translate' . PHP_EOL);
                continue;
            }

            $stats['valid_rows']++;

            if ($memoryHint === null) {
                $stats['skipped_empty_memory_hint']++;
                continue;
            }

            $existingWord = null;

            if ($hasSourceLookup) {
                $findBySource->bind_param('ss', $source, $sourceKey);
                $findBySource->execute();
                $existingWord = $findBySource->get_result()->fetch_assoc();
            }

            if (!$existingWord && $hasFallbackLookup) {
                $fallbackType = $type ?? '';
                $findByFallback->bind_param('sss', $text, $fallbackType, $translate);
                $findByFallback->execute();
                $existingWord = $findByFallback->get_result()->fetch_assoc();
            }

            if (!$existingWord) {
                $stats['skipped_not_found']++;
                continue;
            }

            if ($isUpdateDryRun) {
                $stats['would_update_memory_hint']++;
                continue;
            }

            $wordId = (int)$existingWord['id'];
            $updateMemoryHint->bind_param('si', $memoryHint, $wordId);
            $updateMemoryHint->execute();
            $stats['updated_memory_hint']++;
            continue;
        }

        if ($source === '' || $sourceKey === '' || $text === '' || $translate === '') {
            $stats['errors']++;
            fwrite(STDERR, 'Invalid row ' . $stats['rows'] . ': source, source_key, text and translate are required' . PHP_EOL);
            continue;
        }

        $stats['valid_rows']++;

        $findBySource->bind_param('ss', $source, $sourceKey);
        $findBySource->execute();
        $existingWord = $findBySource->get_result()->fetch_assoc();

        if (!$existingWord) {
            $fallbackType = $type ?? '';
            $findByFallback->bind_param('sss', $text, $fallbackType, $translate);
            $findByFallback->execute();
            $existingWord = $findByFallback->get_result()->fetch_assoc();
        }

        if ($existingWord) {
            $stats['skipped_existing_words']++;
            continue;
        }

        foreach ($topics as $topicName) {
            $slug = slugifyTopic($topicName);

            if ($slug === '') {
                continue;
            }

            if (!isset($existingTopicSlugs[$slug])) {
                $stats['would_create_topics']++;
                $existingTopicSlugs[$slug] = true;
            }

            $stats['would_link_topics']++;
        }

        foreach ($forms as $form) {
            if (mb_strtolower($form, 'UTF-8') === mb_strtolower($text, 'UTF-8')) {
                continue;
            }

            $stats['would_add_forms']++;
        }

        if ($mode === 'dry-run') {
            $stats['would_insert_words']++;
            continue;
        }

        $hash = importHash([
            'source' => $source,
            'source_key' => $sourceKey,
            'text' => $text,
            'translate' => $translate,
            'type' => $type ?? '',
            'level' => $level ?? '',
            'transcription' => $transcription ?? '',
            'example' => $example ?? '',
            'example_ru' => $exampleRu ?? '',
            'memory_hint' => $memoryHint ?? '',
            'topics' => implode(',', $topics),
            'forms' => implode(',', $forms),
        ]);

        $insertWord->bind_param(
            'ssssssssssss',
            $text,
            $translate,
            $type,
            $level,
            $transcription,
            $example,
            $exampleRu,
            $memoryHint,
            $source,
            $sourceKey,
            $batch,
            $hash
        );
        $insertWord->execute();
        $wordId = (int)$insertWord->insert_id;
        $stats['inserted_words']++;

        foreach ($topics as $topicName) {
            $slug = slugifyTopic($topicName);

            if ($slug === '') {
                continue;
            }

            $insertTopic->bind_param('ss', $topicName, $slug);
            $insertTopic->execute();

            if ($insertTopic->affected_rows > 0) {
                $stats['created_topics']++;
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
                $stats['linked_topics']++;
            }
        }

        foreach ($forms as $form) {
            if (mb_strtolower($form, 'UTF-8') === mb_strtolower($text, 'UTF-8')) {
                continue;
            }

            $insertWordForm->bind_param('is', $wordId, $form);
            $insertWordForm->execute();

            if ($insertWordForm->affected_rows > 0) {
                $stats['added_forms']++;
            }
        }
    }

    if ($mode === 'create_new_only' || ($mode === 'update_selected_fields' && !$isUpdateDryRun)) {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($mode === 'create_new_only' || ($mode === 'update_selected_fields' && !$isUpdateDryRun)) {
        $db->rollback();
    }

    fail($e->getMessage());
} finally {
    fclose($handle);
}

echo 'file=' . $path . PHP_EOL;

if ($mode === 'update_selected_fields') {
    $reportFields = [
        'mode',
        'batch',
        'rows',
        'valid_rows',
        'would_update_memory_hint',
        'updated_memory_hint',
        'skipped_not_found',
        'skipped_empty_memory_hint',
        'errors',
    ];
} else {
    $reportFields = array_keys($stats);
}

foreach ($reportFields as $name) {
    $value = $stats[$name];
    echo $name . '=' . $value . PHP_EOL;
}
