<?php

declare(strict_types=1);

use EdgeCache\Controllers\LicenseController;
use EdgeCache\Repositories\LicenseRepository;
use EdgeCache\Services\LicenseService;
use EdgeCache\Support\Database;
use EdgeCache\Support\Request;
use EdgeCache\Support\Response;

$projectRoot = dirname(__DIR__);

if (file_exists($projectRoot . '/.env')) {
    $lines = file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"");
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'EdgeCache\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $projectRoot . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$config = require $projectRoot . '/config/app.php';
$dbFile = $projectRoot . '/' . ltrim((string) $config['db_path'], '/');
$databaseUrl = (string) ($config['database_url'] ?? '');

$db = new Database($databaseUrl, $dbFile);
$db->migrate();

$repository = new LicenseRepository($db->pdo());
$service = new LicenseService(
    $repository,
    (string) $config['master_key'],
    (int) $config['rate_limit_window_seconds'],
    (int) $config['rate_limit_max_requests']
);
$controller = new LicenseController(
    $service,
    (string) $config['signing_secret'],
    (string) $config['admin_token']
);

$method = Request::method();
$path = Request::path();

$routes = [
    'GET /v1/health' => [$controller, 'health'],
    'POST /v1/license/activate' => [$controller, 'activate'],
    'POST /v1/license/verify' => [$controller, 'verify'],
    'POST /v1/license/deactivate' => [$controller, 'deactivate'],
    'GET /v1/internal/licenses' => [$controller, 'listLicenses'],
    'POST /v1/internal/licenses' => [$controller, 'issueLicense'],
];

$routeKey = $method . ' ' . $path;
$handler = $routes[$routeKey] ?? null;

if (!is_array($handler) || !is_callable($handler)) {
    Response::json(['message' => 'not found'], 404);
    exit;
}

try {
    call_user_func($handler);
} catch (Throwable $e) {
    Response::json([
        'message' => 'internal error',
        'error' => ($config['debug'] ?? false) ? $e->getMessage() : null,
    ], 500);
}
