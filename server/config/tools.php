<?php

return [
    'token_secret' => env('TOOL_TOKEN_SECRET') ?: env('APP_KEY'),
    'token_ttl' => (int) env('TOOL_TOKEN_TTL', 300),
    'runtime_origin' => env('TOOL_RUNTIME_ORIGIN', 'http://127.0.0.1:8000'),
    'platform_origin' => env('FRONTEND_URL', 'http://localhost:3000'),
    'max_archive_bytes' => 25 * 1024 * 1024,
    'max_unpacked_bytes' => 100 * 1024 * 1024,
    'max_file_bytes' => 20 * 1024 * 1024,
    'max_package_files' => 2000,
];
