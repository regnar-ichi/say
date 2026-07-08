<?php

namespace App\Controllers\Page;

use App\Core\Database;

class AiDbMapController
{
    private string $root;
    private string $databaseName;
    private array $config = [];

    public function table(): void
    {
        $_GET['table'] = $_GET['_route_wildcard'] ?? '';

        $this->index();
    }

    public function index(): void
    {
        $this->root = realpath(__DIR__ . '/../../../');
        $this->config = $this->loadConfig();
        $this->sendSecurityHeaders();

        $token = $_GET['token'] ?? '';
        $requestedTable = trim($_GET['table'] ?? '');

        if (!$this->isAllowed($token)) {
            $this->logAccess('ai-db-map', $requestedTable ?: 'all', false);
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $config = require $this->root . '/config/db.php';
        $this->databaseName = (string)$config['database'];

        header('Content-Type: text/plain; charset=utf-8');

        $tables = $this->getTables();

        if ($requestedTable !== '') {
            if (!in_array($requestedTable, array_column($tables, 'name'), true)) {
                $this->logAccess('ai-db-map', $requestedTable, false);
                http_response_code(404);
                echo "Table not found: {$requestedTable}\n";
                return;
            }

            $tables = array_values(array_filter(
                $tables,
                fn(array $table) => $table['name'] === $requestedTable
            ));
        }

        $this->logAccess('ai-db-map', $requestedTable ?: 'all', true);
        $this->showMap($tables, $requestedTable !== '');
    }

    private function loadConfig(): array
    {
        $configPath = $this->root . '/config/ai-map.php';

        if (!is_file($configPath)) {
            return [];
        }

        return require $configPath;
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }

    private function isAllowed(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $tokens = array_filter(array_merge(
            [(string)($this->config['token'] ?? '')],
            array_map('strval', $this->config['legacy_tokens'] ?? [])
        ));

        foreach ($tokens as $expected) {
            if ($expected !== '' && hash_equals($expected, $token)) {
                return true;
            }
        }

        return false;
    }

    private function getTables(): array
    {
        $db = Database::connect();
        $result = $db->query('SHOW FULL TABLES');
        $allowedTables = $this->config['allowed_db_tables'] ?? [];
        $tables = [];

        while ($row = $result->fetch_array()) {
            if (!in_array($row[0], $allowedTables, true)) {
                continue;
            }

            $tables[] = [
                'name' => $row[0],
                'type' => $row[1] ?? 'BASE TABLE',
            ];
        }

        usort($tables, fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        return $tables;
    }

    private function showMap(array $tables, bool $singleTable): void
    {
        echo "foxSay / foxfamily.fun AI DB MAP\n";
        echo "Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "Mode: schema only, no table data\n";
        echo "DATABASE: {$this->databaseName}\n";
        echo "Tables: " . count($tables) . "\n\n";

        echo "=== NOTES FOR AI ===\n";
        echo "- This endpoint is read-only and prints database structure only.\n";
        echo "- No user data, words, examples, passwords, or content rows are selected or printed.\n";
        echo "- Do not assume missing fields, tables, indexes, or relations.\n";
        echo "- Use existing table structure only.\n";
        echo "- Many application tables are user scoped through user_id when that column exists.\n\n";

        echo "USAGE:\n";
        echo "/ai-db-map?token=TOKEN\n";
        echo "/ai-db-map?token=TOKEN&table=translate\n\n";

        if (!$singleTable) {
            echo "TABLE LIST:\n";
            foreach ($tables as $table) {
                echo "- {$table['name']} ({$table['type']})\n";
            }
            echo "\n";
        }

        foreach ($tables as $table) {
            $this->showTable($table);
        }
    }

    private function showTable(array $table): void
    {
        $name = $table['name'];
        $columns = $this->getColumns($name);
        $indexes = $this->getIndexes($name);
        $foreignKeys = $this->getForeignKeys($name);

        echo "==================================================\n";
        echo "TABLE: {$name}\n";
        echo "TYPE: {$table['type']}\n";
        echo "==================================================\n\n";

        echo "COLUMNS:\n\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}\n";
            echo "  type: {$column['Type']}\n";
            echo "  null: " . strtolower($column['Null']) . "\n";

            if ($column['Key'] !== '') {
                echo "  key: {$column['Key']}\n";
            }

            echo "  default: " . $this->formatDefault($column['Default']) . "\n";

            if ($column['Extra'] !== '') {
                echo "  extra: {$column['Extra']}\n";
            }

            if ($column['Comment'] !== '') {
                echo "  comment: {$column['Comment']}\n";
            }

            $columnIndexes = $this->indexesForColumn($indexes, $column['Field']);
            if (!empty($columnIndexes)) {
                echo "  indexes: " . implode(', ', $columnIndexes) . "\n";
            }

            echo "\n";
        }

        echo "INDEXES:\n";
        if (empty($indexes)) {
            echo "- none\n";
        } else {
            foreach ($indexes as $indexName => $index) {
                echo "- {$indexName}\n";
                echo "  unique: " . ($index['unique'] ? 'yes' : 'no') . "\n";
                echo "  type: {$index['type']}\n";
                echo "  columns: " . implode(', ', $index['columns']) . "\n";
            }
        }
        echo "\n";

        echo "FOREIGN KEYS / RELATIONS:\n";
        if (empty($foreignKeys)) {
            echo "- no explicit foreign keys found\n";
        } else {
            foreach ($foreignKeys as $fk) {
                echo "- {$fk['column']} -> {$fk['referenced_table']}.{$fk['referenced_column']}";
                echo " ({$fk['constraint']})\n";
            }
        }

        $hints = $this->relationHints($columns, $foreignKeys);
        if (!empty($hints)) {
            echo "\nRELATION HINTS:\n";
            foreach ($hints as $hint) {
                echo "- {$hint}\n";
            }
        }

        echo "\n";
    }

    private function getColumns(string $table): array
    {
        $db = Database::connect();
        $result = $db->query('SHOW FULL COLUMNS FROM ' . $this->quoteIdentifier($table));
        $columns = [];

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }

        return $columns;
    }

    private function getIndexes(string $table): array
    {
        $db = Database::connect();
        $result = $db->query('SHOW INDEX FROM ' . $this->quoteIdentifier($table));
        $indexes = [];

        while ($row = $result->fetch_assoc()) {
            $name = $row['Key_name'];

            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'unique' => (int)$row['Non_unique'] === 0,
                    'type' => $row['Index_type'] ?? '',
                    'columns' => [],
                ];
            }

            $column = $row['Column_name'];
            if (!empty($row['Sub_part'])) {
                $column .= '(' . $row['Sub_part'] . ')';
            }

            $indexes[$name]['columns'][(int)$row['Seq_in_index']] = $column;
        }

        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        unset($index);

        return $indexes;
    }

    private function getForeignKeys(string $table): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        ");

        $stmt->bind_param('ss', $this->databaseName, $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $foreignKeys = [];

        while ($row = $result->fetch_assoc()) {
            $foreignKeys[] = [
                'constraint' => $row['CONSTRAINT_NAME'],
                'column' => $row['COLUMN_NAME'],
                'referenced_table' => $row['REFERENCED_TABLE_NAME'],
                'referenced_column' => $row['REFERENCED_COLUMN_NAME'],
            ];
        }

        $stmt->close();

        return $foreignKeys;
    }

    private function relationHints(array $columns, array $foreignKeys): array
    {
        $fkColumns = array_column($foreignKeys, 'column');
        $hints = [];

        foreach ($columns as $column) {
            $name = $column['Field'];

            if (in_array($name, $fkColumns, true)) {
                continue;
            }

            if ($name === 'user_id') {
                $hints[] = 'user_id likely scopes rows to users.id in application code';
                continue;
            }

            if (str_ends_with($name, '_id')) {
                $base = substr($name, 0, -3);
                $hints[] = "{$name} may reference a related {$base} table; no explicit FK found";
            }
        }

        return $hints;
    }

    private function indexesForColumn(array $indexes, string $column): array
    {
        $matches = [];

        foreach ($indexes as $name => $index) {
            foreach ($index['columns'] as $indexedColumn) {
                $plainColumn = preg_replace('/\(.+\)$/', '', $indexedColumn);
                if ($plainColumn === $column) {
                    $matches[] = $name . ($index['unique'] ? ' unique' : '');
                    break;
                }
            }
        }

        return $matches;
    }

    private function formatDefault($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value === '') {
            return "''";
        }

        return (string)$value;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function logAccess(string $endpoint, string $target, bool $success): void
    {
        $relativeLog = $this->config['log_file'] ?? '';

        if ($relativeLog === '') {
            return;
        }

        $logPath = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeLog);
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = implode("\t", [
            date('Y-m-d H:i:s'),
            $endpoint,
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $target,
            $success ? 'success' : 'fail',
        ]) . "\n";

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
