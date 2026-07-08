<?php

require_once __DIR__ . '/../../config/db.php';

const CORE_RAW_PATH = __DIR__ . '/sources/oxford_core_raw.tsv';
const MISSING_PATH = __DIR__ . '/sources/oxford_core_missing.tsv';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function normalizePos(string $pos): ?string
{
    $pos = strtolower(trim($pos));
    $pos = trim($pos, " \t\n\r,;");

    $map = [
        'n.' => 'noun',
        'n' => 'noun',
        'noun' => 'noun',
        'v.' => 'verb',
        'v' => 'verb',
        'verb' => 'verb',
        'adj.' => 'adjective',
        'adj' => 'adjective',
        'adjective' => 'adjective',
        'adv.' => 'adverb',
        'adv' => 'adverb',
        'adverb' => 'adverb',
        'prep.' => 'preposition',
        'prep' => 'preposition',
        'preposition' => 'preposition',
        'pron.' => 'pronoun',
        'pron' => 'pronoun',
        'pronoun' => 'pronoun',
        'det.' => 'determiner',
        'det' => 'determiner',
        'determiner' => 'determiner',
        'conj.' => 'conjunction',
        'conj' => 'conjunction',
        'conjunction' => 'conjunction',
        'exclam.' => 'exclamation',
        'exclam' => 'exclamation',
        'exclamation' => 'exclamation',
        'number' => 'number',
        'num.' => 'number',
        'num' => 'number',
    ];

    return $map[$pos] ?? $pos;
}

function loadOxfordRows(string $path): array
{
    if (!is_file($path)) {
        fail('Oxford core raw TSV not found: ' . $path);
    }

    $handle = fopen($path, 'rb');

    if (!$handle) {
        fail('Cannot open TSV: ' . $path);
    }

    $headers = fgetcsv($handle, 0, "\t");

    if ($headers === false) {
        fail('TSV is empty: ' . $path);
    }

    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $rows = [];

    while (($values = fgetcsv($handle, 0, "\t")) !== false) {
        $row = [];

        foreach ($headers as $index => $column) {
            $row[$column] = isset($values[$index]) ? trim((string)$values[$index]) : '';
        }

        if (($row['word'] ?? '') === '' || ($row['pos'] ?? '') === '') {
            continue;
        }

        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

function writeTsv(string $path, array $rows): void
{
    $handle = fopen($path, 'wb');

    if (!$handle) {
        fail('Cannot write TSV: ' . $path);
    }

    fputcsv($handle, ['word', 'pos', 'level', 'source_order', 'source'], "\t", chr(0));

    foreach ($rows as $row) {
        fputcsv($handle, [
            $row['word'],
            $row['pos'],
            $row['level'],
            $row['source_order'],
            $row['source'],
        ], "\t", chr(0));
    }

    fclose($handle);
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
$known = [];
$result = $db->query('SELECT text, type FROM translate');

while ($row = $result->fetch_assoc()) {
    $pos = normalizePos((string)$row['type']);
    $key = mb_strtolower(trim((string)$row['text']), 'UTF-8') . '|' . $pos;
    $known[$key] = true;
}

$oxfordRows = loadOxfordRows(CORE_RAW_PATH);
$missing = [];
$existing = 0;

foreach ($oxfordRows as $row) {
    $pos = normalizePos((string)$row['pos']);
    $key = mb_strtolower(trim((string)$row['word']), 'UTF-8') . '|' . $pos;

    if (isset($known[$key])) {
        $existing++;
        continue;
    }

    $missing[] = $row;
}

writeTsv(MISSING_PATH, $missing);

$firstRows = array_slice($missing, 0, 30);

echo json_encode([
    'core_rows' => count($oxfordRows),
    'existing_in_translate' => $existing,
    'missing' => count($missing),
    'missing_path' => MISSING_PATH,
    'first_30_missing' => $firstRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
