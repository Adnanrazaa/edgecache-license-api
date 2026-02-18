<?php

declare(strict_types=1);

namespace EdgeCache\Support;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(private readonly string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
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
}
