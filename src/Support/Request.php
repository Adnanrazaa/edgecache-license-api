<?php

declare(strict_types=1);

namespace EdgeCache\Support;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? rtrim($path, '/') ?: '/' : '/';
    }

    public static function bodyRaw(): string
    {
        $raw = file_get_contents('php://input');
        return is_string($raw) ? $raw : '';
    }

    public static function json(): array
    {
        $raw = self::bodyRaw();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return is_string($ip) ? $ip : '127.0.0.1';
    }
}
