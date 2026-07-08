<?php

namespace App\Controllers\Page;

use ZipArchive;
use App\Core\Database;

class BackupController
{
    public function index(): void
    {
        $root = realpath(__DIR__ . '/../../../');

        $backupDir = $root . '/storage/backups';

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $date = date('Y-m-d_H-i-s');

        $zipPath = $backupDir . "/foxSay_backup_{$date}.zip";

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            exit('Cannot create backup');
        }

        $exclude = [
            '.git',
            'storage/backups'
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {

            $filePath = $file->getRealPath();

            $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);

            foreach ($exclude as $excluded) {
                if (str_starts_with($relativePath, $excluded)) {
                    continue 2;
                }
            }

            $zip->addFile($filePath, $relativePath);
        }

        $db = Database::connect();

        $sql = '';
        $tables = [];

        $result = $db->query("SHOW TABLES");

        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {

            $createResult = $db->query("SHOW CREATE TABLE `$table`");
            $createRow = $createResult->fetch_assoc();

            $sql .= "\n\n";
            $sql .= $createRow['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$table`");

            while ($data = $rows->fetch_assoc()) {

                $values = [];

                foreach ($data as $value) {

                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $db->real_escape_string($value) . "'";
                    }
                }

                $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
        }

        $zip->addFromString(
            'database/foxSay_database_' . $date . '.sql',
            $sql
        );        

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');

        readfile($zipPath);
        exit;
    }
}