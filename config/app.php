<?php

declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
    'name' => getenv('APP_NAME') ?: 'EdgeCache License API',
    'url' => getenv('APP_URL') ?: 'http://127.0.0.1:8080',
    'database_url' => getenv('DATABASE_URL') ?: '',
    'db_path' => getenv('DB_PATH') ?: 'storage/license.db',
    'master_key' => getenv('EDGECACHE_MASTER_KEY') ?: '',
    'signing_secret' => getenv('SIGNING_SECRET') ?: '',
    'admin_token' => getenv('ADMIN_TOKEN') ?: '',
    'rate_limit_window_seconds' => (int) (getenv('RATE_LIMIT_WINDOW_SECONDS') ?: 60),
    'rate_limit_max_requests' => (int) (getenv('RATE_LIMIT_MAX_REQUESTS') ?: 60),
];
