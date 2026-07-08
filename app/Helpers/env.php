<?php

function env(string $key, $default = null)
{
    static $env = null;

    if ($env === null) {
        $env = [];

        $path = __DIR__ . '/../../.env';

        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');

                $env[trim($k)] = trim($v);
            }
        }
    }

    return $env[$key] ?? $default;
}