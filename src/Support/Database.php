<?php

declare(strict_types=1);

namespace EdgeCache\Support;

use PDO;
use RuntimeException;

final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(private readonly string $databaseUrl, private readonly string $dbPath)
    {
        if ($this->databaseUrl !== '') {
            $this->pdo = $this->connectPostgres($this->databaseUrl);
            $this->driver = 'pgsql';
            return;
        }

        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->driver = 'sqlite';
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function migrate(): void
    {
        if ($this->driver === 'pgsql') {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS licenses (
                    id BIGSERIAL PRIMARY KEY,
                    key_hash TEXT UNIQUE NOT NULL,
                    plan TEXT NOT NULL DEFAULT 'pro',
                    status TEXT NOT NULL DEFAULT 'active',
                    features_json JSONB NOT NULL DEFAULT '[]'::jsonb,
                    expires_at BIGINT NULL,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL
                )"
            );

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS activations (
                    id BIGSERIAL PRIMARY KEY,
                    license_hash TEXT NOT NULL,
                    site_url TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    last_verified_at BIGINT NOT NULL,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL,
                    UNIQUE(license_hash, site_url)
                )"
            );

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS rate_limits (
                    id BIGSERIAL PRIMARY KEY,
                    limiter_key TEXT UNIQUE NOT NULL,
                    window_start BIGINT NOT NULL,
                    count INTEGER NOT NULL
                )"
            );

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGSERIAL PRIMARY KEY,
                    event TEXT NOT NULL,
                    details_json JSONB NOT NULL DEFAULT '{}'::jsonb,
                    created_at BIGINT NOT NULL
                )"
            );

            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS licenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_hash TEXT UNIQUE NOT NULL,
                plan TEXT NOT NULL DEFAULT "pro",
                status TEXT NOT NULL DEFAULT "active",
                features_json TEXT NOT NULL,
                expires_at INTEGER NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS activations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                license_hash TEXT NOT NULL,
                site_url TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "active",
                last_verified_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                UNIQUE(license_hash, site_url)
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                limiter_key TEXT UNIQUE NOT NULL,
                window_start INTEGER NOT NULL,
                count INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event TEXT NOT NULL,
                details_json TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );
    }

    private function connectPostgres(string $databaseUrl): PDO
    {
        $parts = parse_url($databaseUrl);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid DATABASE_URL');
        }

        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 5432);
        $dbName = ltrim((string) ($parts['path'] ?? ''), '/');
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $sslmode = (string) ($query['sslmode'] ?? 'require');

        if ($host === '' || $dbName === '' || $user === '') {
            throw new RuntimeException('DATABASE_URL is missing required PostgreSQL parts');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $host,
            $port,
            $dbName,
            $sslmode
        );

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}
