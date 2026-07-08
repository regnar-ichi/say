<?php

namespace App\Controllers\Page;

class AiMapController
{
    private string $root;
    private array $config = [];

    public function raw(): void
    {
        $_GET['file'] = $_GET['_route_wildcard'] ?? '';
        $_GET['raw'] = '1';

        $this->index();
    }

    public function bundle(): void
    {
        $path = $_GET['_route_wildcard'] ?? '';

        if ($path !== '') {
            $_GET['path'] = $path;
        }

        $_GET['mode'] = 'bundle';
        $_GET['plain'] = '1';

        $this->index();
    }

    public function index(): void
    {
        $this->root = realpath(__DIR__ . '/../../../');
        $this->config = $this->loadConfig();
        $this->sendSecurityHeaders();

        $token = $_GET['token'] ?? '';
        $file = $_GET['file'] ?? '';
        $mode = $_GET['mode'] ?? '';
        $path = trim($_GET['path'] ?? '');
        $target = $file !== '' ? $file : (($mode === 'bundle' || ($_GET['all'] ?? '') === '1') ? 'bundle' : 'map');

        if (!$this->isAllowed($token)) {
            $this->logAccess('ai-map', $target, false);
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $files = $this->getFiles();

        header('Content-Type: text/plain; charset=utf-8');

        if ($file !== '') {
            $allowedPaths = array_column($files, 'path');
            $this->logAccess('ai-map', $file, in_array($file, $allowedPaths, true));
            $this->showFile($file, $files, ($_GET['raw'] ?? '') === '1');
            return;
        }

        if ($mode === 'bundle' || (isset($_GET['all']) && $_GET['all'] === '1')) {
            $bundleFiles = $this->filterFilesByPath($files, $path);
            $this->logAccess('ai-map', $path !== '' ? 'bundle:' . $path : 'bundle', true);
            $this->showBundle($bundleFiles, $path, ($_GET['plain'] ?? '') === '1');
            return;
        }

        $this->logAccess('ai-map', 'map', true);
        $this->showMap($files);
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

    private function getFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $relative = str_replace($this->root . DIRECTORY_SEPARATOR, '', $fullPath);
            $relative = str_replace('\\', '/', $relative);

            if ($this->isExcluded($relative)) {
                continue;
            }

            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if (basename($relative) === '.htaccess') {
                $extension = 'htaccess';
            }

            $allowedExtensions = $this->config['allowed_extensions'] ?? [];
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $files[] = [
                'path' => $relative,
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'hash' => hash_file('sha256', $fullPath),
            ];
        }

        usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    private function isExcluded(string $relative): bool
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        $baseName = basename($relative);

        foreach ($this->config['exclude_paths'] ?? [] as $path) {
            $path = str_replace('\\', '/', trim($path, '/'));

            if ($path === '') {
                continue;
            }

            if ($relative === $path || str_starts_with($relative, $path . '/')) {
                return true;
            }
        }

        foreach ($this->config['exclude_files'] ?? [] as $file) {
            if ($relative === $file || $baseName === $file) {
                return true;
            }
        }

        return false;
    }

    private function showMap(array $files): void
    {
        $token = $_GET['token'] ?? '';
        $baseUrl = $this->getBaseUrl();
        $project = $this->config['project'] ?? 'foxSay';

        echo "AI-MAP-VERSION: 1\n";
        echo "PROJECT: {$project}\n";
        echo "BASE-URL: {$baseUrl}/ai-map?token=" . rawurlencode($token) . "\n";
        echo "BUNDLE-URL: " . $this->buildBundleUrl($baseUrl, $token) . "\n";
        echo "GENERATED: " . date('Y-m-d H:i:s') . "\n";
        echo "FILES: " . count($files) . "\n\n";

        foreach ($files as $file) {
            echo "FILE: {$file['path']}\n";
            echo "URL: " . $this->buildFileUrl($baseUrl, $token, $file['path']) . "\n";
            echo "RAW-URL: " . $this->buildRawUrl($baseUrl, $token, $file['path']) . "\n";
            echo "SIZE: {$file['size']}\n";
            echo "MODIFIED: {$file['modified']}\n";
            echo "SHA256: {$file['hash']}\n\n";
        }
    }

    private function showFile(string $requestedFile, array $files, bool $raw = false): void
    {
        $requestedFile = str_replace('\\', '/', $requestedFile);
        $requestedFile = ltrim($requestedFile, '/');

        $allowedPaths = array_column($files, 'path');

        if (!in_array($requestedFile, $allowedPaths, true)) {
            http_response_code(404);
            echo "File not found or forbidden\n";
            return;
        }

        $fullPath = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedFile);

        if ($raw) {
            echo file_get_contents($fullPath);
            return;
        }

        echo "foxSay / foxfamily.fun AI FILE\n";
        echo "Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "File: {$requestedFile}\n";
        echo "Size: " . $this->formatBytes(filesize($fullPath)) . "\n";
        echo "Modified: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "\n";
        echo "SHA256: " . hash_file('sha256', $fullPath) . "\n";
        echo "\n";
        echo "============================================================\n";
        echo $this->normalizeContent(file_get_contents($fullPath));
    }

    private function showBundle(array $files, string $path = '', bool $plain = false): void
    {
        $project = $this->config['project'] ?? 'foxSay';
        $maxBytes = (int)($this->config['bundle_max_bytes'] ?? 1200000);
        $totalBytes = array_sum(array_column($files, 'size'));

        echo "AI-BUNDLE-VERSION: 1\n";
        echo "PROJECT: {$project}\n";
        echo "FORMAT: " . ($plain ? 'plain' : 'markdown-fenced') . "\n";
        echo "GENERATED: " . date('Y-m-d H:i:s') . "\n";
        echo "PATH-FILTER: " . ($path !== '' ? $path : 'none') . "\n";
        echo "FILES: " . count($files) . "\n";
        echo "TOTAL-BYTES: {$totalBytes}\n";
        echo "MAX-BYTES: {$maxBytes}\n\n";

        if ($totalBytes > $maxBytes) {
            echo "BUNDLE_TOO_LARGE\n";
            echo "Use /ai-raw/{file}?token=... or /ai-bundle/{path}?token=...\n\n";
            $this->showMap($files);
            return;
        }

        foreach ($files as $file) {
            $fullPath = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);

            if ($plain) {
                echo "\n";
                echo "<<<AI_FILE_START:path={$file['path']};sha256={$file['hash']};size={$file['size']}>>>\n";
                echo file_get_contents($fullPath);
                echo "\n<<<AI_FILE_END:path={$file['path']}>>>\n";
            } else {
                $language = $this->languageForFile($file['path']);

                echo "\n\n";
                echo "===== FILE: {$file['path']} =====\n";
                echo "SIZE: {$file['size']}\n";
                echo "MODIFIED: {$file['modified']}\n";
                echo "SHA256: {$file['hash']}\n";
                echo "```{$language}\n";
                echo $this->normalizeContent(file_get_contents($fullPath));
                echo "\n```\n";
            }
        }
    }

    private function filterFilesByPath(array $files, string $path): array
    {
        if ($path === '') {
            return $files;
        }

        $path = str_replace('\\', '/', trim($path, '/'));

        if ($path === '' || $this->isExcluded($path)) {
            return [];
        }

        return array_values(array_filter($files, function (array $file) use ($path) {
            return $file['path'] === $path || str_starts_with($file['path'], $path . '/');
        }));
    }

   private function normalizeContent(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (substr_count($content, "\n") === 0) {
            $content = preg_replace('/(<\?php|namespace |use |class |public |private |protected |function |return |if \(|foreach \(|while \(|\})/', "\n$1", $content);
            $content = trim($content);
        }

        return $content;
    } 

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    private function languageForFile(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => 'php',
            'js' => 'javascript',
            'css' => 'css',
            'md' => 'markdown',
            'json' => 'json',
            'sql' => 'sql',
            default => '',
        };
    }

    private function getBaseUrl(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';

        if ($forwardedProto !== '') {
            $scheme = strtolower(explode(',', $forwardedProto)[0]);
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'www.foxfamily.fun';

        return $scheme . '://' . $host;
    }

    private function buildFileUrl(string $baseUrl, string $token, string $path, bool $raw = false): string
    {
        $url = $baseUrl
            . '/ai-map?token=' . rawurlencode($token)
            . '&file=' . rawurlencode($path);

        if ($raw) {
            $url .= '&raw=1';
        }

        return $url;
    }

    private function buildRawUrl(string $baseUrl, string $token, string $path): string
    {
        return $baseUrl . '/ai-raw/' . str_replace('%2F', '/', rawurlencode($path))
            . '?token=' . rawurlencode($token);
    }

    private function buildBundleUrl(string $baseUrl, string $token, string $path = ''): string
    {
        $url = $baseUrl . '/ai-bundle';

        if ($path !== '') {
            $url .= '/' . str_replace('%2F', '/', rawurlencode($path));
        }

        return $url . '?token=' . rawurlencode($token);
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
