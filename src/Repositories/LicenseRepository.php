<?php

declare(strict_types=1);

namespace EdgeCache\Repositories;

use PDO;

final class LicenseRepository
{
    private string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->driver = is_string($driver) ? $driver : 'sqlite';
    }

    public function findLicense(string $keyHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE key_hash = :key_hash LIMIT 1');
        $stmt->execute(['key_hash' => $keyHash]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function upsertLicense(string $keyHash, string $plan, string $status, array $features, ?int $expiresAt): void
    {
        $now = time();
        $featuresJsonExpr = $this->driver === 'pgsql' ? 'CAST(:features_json AS jsonb)' : ':features_json';
        $stmt = $this->pdo->prepare(
            'INSERT INTO licenses (key_hash, plan, status, features_json, expires_at, created_at, updated_at)
             VALUES (:key_hash, :plan, :status, ' . $featuresJsonExpr . ', :expires_at, :created_at, :updated_at)
             ON CONFLICT(key_hash) DO UPDATE SET
                plan = excluded.plan,
                status = excluded.status,
                features_json = excluded.features_json,
                expires_at = excluded.expires_at,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([
            'key_hash' => $keyHash,
            'plan' => $plan,
            'status' => $status,
            'features_json' => json_encode(array_values($features), JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function upsertActivation(string $keyHash, string $siteUrl, string $status): void
    {
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO activations (license_hash, site_url, status, last_verified_at, created_at, updated_at)
             VALUES (:license_hash, :site_url, :status, :last_verified_at, :created_at, :updated_at)
             ON CONFLICT(license_hash, site_url) DO UPDATE SET
                status = excluded.status,
                last_verified_at = excluded.last_verified_at,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            'license_hash' => $keyHash,
            'site_url' => $siteUrl,
            'status' => $status,
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function deactivateActivation(string $keyHash, string $siteUrl): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE activations
             SET status = :inactive_status, updated_at = :updated_at
             WHERE license_hash = :license_hash AND site_url = :site_url'
        );
        $stmt->execute([
            'inactive_status' => 'inactive',
            'updated_at' => time(),
            'license_hash' => $keyHash,
            'site_url' => $siteUrl,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hitRateLimit(string $limiterKey, int $windowSeconds, int $maxRequests): bool
    {
        $now = time();
        $stmt = $this->pdo->prepare('SELECT * FROM rate_limits WHERE limiter_key = :limiter_key LIMIT 1');
        $stmt->execute(['limiter_key' => $limiterKey]);
        $existing = $stmt->fetch();

        if (!is_array($existing)) {
            $insert = $this->pdo->prepare(
                'INSERT INTO rate_limits (limiter_key, window_start, count) VALUES (:limiter_key, :window_start, 1)'
            );
            $insert->execute(['limiter_key' => $limiterKey, 'window_start' => $now]);
            return false;
        }

        $windowStart = (int) $existing['window_start'];
        $count = (int) $existing['count'];

        if (($now - $windowStart) >= $windowSeconds) {
            $reset = $this->pdo->prepare(
                'UPDATE rate_limits SET window_start = :window_start, count = 1 WHERE limiter_key = :limiter_key'
            );
            $reset->execute(['window_start' => $now, 'limiter_key' => $limiterKey]);
            return false;
        }

        if ($count >= $maxRequests) {
            return true;
        }

        $inc = $this->pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE limiter_key = :limiter_key');
        $inc->execute(['limiter_key' => $limiterKey]);

        return false;
    }

    public function logEvent(string $event, array $details): void
    {
        $detailsExpr = $this->driver === 'pgsql' ? 'CAST(:details_json AS jsonb)' : ':details_json';
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (event, details_json, created_at) VALUES (:event, ' . $detailsExpr . ', :created_at)'
        );
        $stmt->execute([
            'event' => $event,
            'details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'created_at' => time(),
        ]);
    }

    public function listLicenses(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT plan, status, expires_at, created_at, updated_at FROM licenses ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
