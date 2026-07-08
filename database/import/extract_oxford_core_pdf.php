<?php

const SOURCE_DIR = __DIR__ . '/sources';
const SOURCE_CONFIG = [
    'oxford3000' => [
        'pdf' => SOURCE_DIR . '/The_Oxford_3000_by_CEFR_level.pdf',
        'raw' => SOURCE_DIR . '/oxford3000_raw.tsv',
        'levels' => ['A1', 'A2', 'B1', 'B2'],
    ],
    'oxford5000' => [
        'pdf' => SOURCE_DIR . '/The_Oxford_5000_by_CEFR_level.pdf',
        'raw' => SOURCE_DIR . '/oxford5000_raw.tsv',
        'levels' => ['B2', 'C1'],
    ],
];

const CORE_RAW_PATH = SOURCE_DIR . '/oxford_core_raw.tsv';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function findPdftotext(): ?string
{
    $options = getopt('', ['pdftotext:']);

    if (isset($options['pdftotext']) && is_file($options['pdftotext'])) {
        return $options['pdftotext'];
    }

    $candidates = ['pdftotext', 'pdftotext.exe'];

    foreach ($candidates as $candidate) {
        $command = stripos(PHP_OS_FAMILY, 'Windows') !== false
            ? 'where ' . escapeshellarg($candidate) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($candidate);
        $output = [];
        $code = 1;
        exec($command, $output, $code);

        if ($code === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
    }

    return null;
}

function normalizePos(string $pos): ?string
{
    $pos = strtolower(trim($pos));
    $pos = trim($pos, " \t\n\r,;");
    $pos = preg_replace('/\s+/', ' ', $pos);

    $map = [
        'n.' => 'noun',
        'n' => 'noun',
        'noun' => 'noun',
        'v.' => 'verb',
        'v' => 'verb',
        'verb' => 'verb',
        'modal v.' => 'verb',
        'modal v' => 'verb',
        'auxiliary v.' => 'verb',
        'auxiliary v' => 'verb',
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
        'indefinite article' => 'determiner',
        'definite article' => 'determiner',
    ];

    return $map[$pos] ?? null;
}

function splitPos(string $rawPos): array
{
    $rawPos = str_replace(['/', '&'], ',', $rawPos);
    $parts = preg_split('/\s*,\s*/', $rawPos) ?: [];
    $normalized = [];

    foreach ($parts as $part) {
        $pos = normalizePos($part);

        if ($pos !== null) {
            $normalized[] = $pos;
        }
    }

    return array_values(array_unique($normalized));
}

function extractText(string $pdftotext, string $pdfPath): string
{
    $tmpDir = SOURCE_DIR . '/.tmp';

    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true)) {
        fail('Cannot create temporary directory: ' . $tmpDir);
    }

    $tmp = tempnam($tmpDir, 'oxford_pdf_');

    if ($tmp === false) {
        fail('Cannot create temporary file for pdftotext output');
    }

    $command = escapeshellarg($pdftotext)
        . ' -layout -enc UTF-8 '
        . escapeshellarg($pdfPath) . ' '
        . escapeshellarg($tmp);
    $output = [];
    $code = 1;
    exec($command, $output, $code);

    if ($code !== 0) {
        @unlink($tmp);
        fail('pdftotext failed for: ' . $pdfPath);
    }

    $text = file_get_contents($tmp);
    @unlink($tmp);

    if ($text === false) {
        fail('Cannot read pdftotext output for: ' . $pdfPath);
    }

    return $text;
}

function parseOxfordText(string $text, string $source, array $allowedLevels, array &$unparsed): array
{
    $entries = [];
    $sourceOrder = 0;
    $currentLevel = $allowedLevels[0] ?? null;
    $levelPattern = implode('|', array_map('preg_quote', $allowedLevels));
    $posTokenPattern = '(?:auxiliary\s+v\.|modal\s+v\.|indefinite\s+article|definite\s+article|n\.|v\.|adj\.|adv\.|prep\.|pron\.|det\.|conj\.|exclam\.|number)';
    $posPattern = $posTokenPattern . '(?:\s*[,\/]\s*' . $posTokenPattern . ')*';
    $lines = preg_split('/\R/u', $text) ?: [];

    foreach ($lines as $lineNumber => $line) {
        $line = trim(preg_replace('/\s+/u', ' ', $line));

        if ($line === '') {
            continue;
        }

        if (preg_match('/^(' . $levelPattern . ')\b/u', $line, $levelMatch)) {
            $currentLevel = $levelMatch[1];
            $line = trim(substr($line, strlen($levelMatch[0])));

            if ($line === '') {
                continue;
            }
        }

        if (preg_match('/\b(' . $levelPattern . ')\b/u', $line, $levelMatch)) {
            $currentLevel = $levelMatch[1];
            $line = trim(preg_replace('/\b' . preg_quote($currentLevel, '/') . '\b/u', ' ', $line, 1));
        }

        if ($currentLevel === null) {
            continue;
        }

        if (!preg_match('/[a-z]/i', $line) || preg_match('/Oxford|CEFR|american english|british english|^[0-9]+$/i', $line)) {
            continue;
        }

        preg_match_all(
            '/([A-Za-z][A-Za-z0-9 \'\-()]*?)\s+(' . $posPattern . ')(?=\s+[A-Za-z]|\s*$)/iu',
            $line,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            if (preg_match('/[a-z]/i', $line) && !preg_match('/level|american english|british english/i', $line)) {
                $unparsed[] = [
                    'source' => $source,
                    'level' => $currentLevel,
                    'line' => $lineNumber + 1,
                    'text' => $line,
                ];
            }
            continue;
        }

        foreach ($matches as $match) {
            $word = strtolower(trim($match[1]));
            $word = preg_replace('/\([^)]*\)/u', '', $word);
            $word = preg_replace('/\d+$/u', '', $word);
            $word = preg_replace('/\s+/u', ' ', $word);
            $word = trim($word, " \t\n\r-");

            if ($word === '' || preg_match('/^(the|and|or)$/i', $word)) {
                continue;
            }

            $positions = splitPos($match[2]);

            if (empty($positions)) {
                $unparsed[] = [
                    'source' => $source,
                    'level' => $currentLevel,
                    'line' => $lineNumber + 1,
                    'text' => $line,
                ];
                continue;
            }

            $sourceOrder++;

            foreach ($positions as $pos) {
                $entries[] = [
                    'word' => $word,
                    'pos' => $pos,
                    'level' => $currentLevel,
                    'source_order' => $sourceOrder,
                    'source' => $source,
                ];
            }
        }
    }

    return $entries;
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

function levelDistribution(array $rows): array
{
    $distribution = [];

    foreach ($rows as $row) {
        $distribution[$row['level']] = ($distribution[$row['level']] ?? 0) + 1;
    }

    ksort($distribution);

    return $distribution;
}

foreach (SOURCE_CONFIG as $config) {
    if (!is_file($config['pdf'])) {
        fail('PDF not found: ' . $config['pdf']);
    }
}

$pdftotext = findPdftotext();

if ($pdftotext === null) {
    fail('pdftotext not found. Install poppler-utils or add pdftotext to PATH.');
}

$allRows = [];
$unparsed = [];
$stats = [
    'pdftotext' => $pdftotext,
    'unparsed' => &$unparsed,
];

foreach (SOURCE_CONFIG as $source => $config) {
    $text = extractText($pdftotext, $config['pdf']);
    $rows = parseOxfordText($text, $source, $config['levels'], $unparsed);
    writeTsv($config['raw'], $rows);
    $allRows[$source] = $rows;
    $stats[$source] = [
        'path' => $config['raw'],
        'rows' => count($rows),
        'levels' => levelDistribution($rows),
    ];
}

$coreRows = [];
$seen = [];

foreach (['oxford3000', 'oxford5000'] as $source) {
    foreach ($allRows[$source] as $row) {
        $key = strtolower($row['word']) . '|' . $row['pos'];

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $coreRows[] = $row;
    }
}

writeTsv(CORE_RAW_PATH, $coreRows);
$stats['core'] = [
    'path' => CORE_RAW_PATH,
    'rows' => count($coreRows),
];

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
