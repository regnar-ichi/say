<?php

return [
    'project' => 'foxSay',
    'token' => env('FOX_AI_TOKEN', ''),
    'bundle_max_bytes' => 1200000,

    // Keep old links working while the new FOX_AI_TOKEN is rolled out.
    // Remove this after all external tools use the new token.
    'legacy_tokens' => [
        env('FOX_AI_LEGACY_TOKEN', 'fox_say_WRAWR'),
    ],

    'allowed_extensions' => [
        'php',
        'js',
        'css',
        'md',
        'json',
        'sql',
        'htaccess',
    ],

    'exclude_paths' => [
        '.git',
        '.github',
        '.vscode',
        'vendor',
        'node_modules',
        '.env',
        '.ftp-deploy-sync-state.json',
        'composer.lock',
        'package-lock.json',
        'config/db.php',
        'config/clickup.php',
        'config/ai-map.php',
        'storage/logs',
        'storage/backups',
        'database/backups',
        'bootstrap/cache',
        'cache',
        'logs',
        'uploads',
        'public/uploads',
    ],

    'exclude_files' => [
        '.env',
        'db.php',
        'clickup.php',
        'ai-map.php',
        'composer.lock',
        'package-lock.json',
    ],

    'allowed_db_tables' => [
        'translate',
        'users',
        'tests',
        'statistic',
        'examination',
    ],

    'log_file' => 'storage/logs/ai-map.log',
];
