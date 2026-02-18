<?php

declare(strict_types=1);

namespace EdgeCache\Services;

use EdgeCache\Repositories\LicenseRepository;

final class LicenseService
{
    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly string $masterKey,
        private readonly int $rateLimitWindow,
        private readonly int $rateLimitMax
    ) {
    }

    public function activate(string $licenseKey, string $siteUrl, string $ip): array
    {
        $licenseKey = trim($licenseKey);
        $siteUrl = trim($siteUrl);

        if ($licenseKey === '' || $siteUrl === '') {
            return $this->result('invalid', 'free', [], null, 'license_key and site_url are required');
        }

        if ($this->repository->hitRateLimit($this->limiterKey($licenseKey, $ip), $this->rateLimitWindow, $this->rateLimitMax)) {
            return $this->result('inactive', 'free', [], null, 'rate limit exceeded');
        }

        $keyHash = $this->keyHash($licenseKey);
        $stored = $this->repository->findLicense($keyHash);

        if ($stored !== null) {
            if ((string) $stored['status'] !== 'active') {
                return $this->result('invalid', 'free', [], null, 'license is not active');
            }

            $expiresAt = isset($stored['expires_at']) ? (int) $stored['expires_at'] : null;
            if ($expiresAt !== null && $expiresAt > 0 && $expiresAt < time()) {
                return $this->result('expired', 'free', [], $expiresAt, 'license expired');
            }

            $features = json_decode((string) $stored['features_json'], true);
            $featureList = is_array($features) ? $features : [];

            $this->repository->upsertActivation($keyHash, $siteUrl, 'active');
            $this->repository->logEvent('license.activate', ['site_url' => $siteUrl, 'via' => 'stored']);

            return $this->result('active', (string) $stored['plan'], $featureList, $expiresAt, 'license activated');
        }

        if ($this->masterKey !== '' && hash_equals(trim($this->masterKey), $licenseKey)) {
            $features = ['prefetch', 'analytics'];
            $this->repository->upsertLicense($keyHash, 'pro', 'active', $features, null);
            $this->repository->upsertActivation($keyHash, $siteUrl, 'active');
            $this->repository->logEvent('license.activate', ['site_url' => $siteUrl, 'via' => 'master_key']);

            return $this->result('active', 'pro', $features, null, 'license activated');
        }

        $this->repository->logEvent('license.activate_invalid', ['site_url' => $siteUrl]);
        return $this->result('invalid', 'free', [], null, 'invalid license key');
    }

    public function verify(string $licenseKey, string $siteUrl, string $ip): array
    {
        $licenseKey = trim($licenseKey);
        $siteUrl = trim($siteUrl);

        if ($licenseKey === '' || $siteUrl === '') {
            return $this->result('invalid', 'free', [], null, 'license_key and site_url are required');
        }

        if ($this->repository->hitRateLimit($this->limiterKey($licenseKey, $ip), $this->rateLimitWindow, $this->rateLimitMax)) {
            return $this->result('inactive', 'free', [], null, 'rate limit exceeded');
        }

        $keyHash = $this->keyHash($licenseKey);
        $stored = $this->repository->findLicense($keyHash);

        if ($stored === null) {
            return $this->result('invalid', 'free', [], null, 'invalid license key');
        }

        $status = (string) $stored['status'];
        if ($status !== 'active') {
            return $this->result('inactive', 'free', [], null, 'license inactive');
        }

        $expiresAt = isset($stored['expires_at']) ? (int) $stored['expires_at'] : null;
        if ($expiresAt !== null && $expiresAt > 0 && $expiresAt < time()) {
            return $this->result('expired', 'free', [], $expiresAt, 'license expired');
        }

        $features = json_decode((string) $stored['features_json'], true);
        $featureList = is_array($features) ? $features : [];

        $this->repository->upsertActivation($keyHash, $siteUrl, 'active');
        $this->repository->logEvent('license.verify', ['site_url' => $siteUrl]);

        return $this->result('active', (string) $stored['plan'], $featureList, $expiresAt, 'license valid');
    }

    public function deactivate(string $licenseKey, string $siteUrl): array
    {
        $licenseKey = trim($licenseKey);
        $siteUrl = trim($siteUrl);

        if ($licenseKey === '' || $siteUrl === '') {
            return ['ok' => false, 'message' => 'license_key and site_url are required'];
        }

        $didDeactivate = $this->repository->deactivateActivation($this->keyHash($licenseKey), $siteUrl);
        $this->repository->logEvent('license.deactivate', ['site_url' => $siteUrl, 'ok' => $didDeactivate]);

        return ['ok' => $didDeactivate, 'message' => $didDeactivate ? 'deactivated' : 'activation not found'];
    }

    public function issueOrUpdateLicense(string $licenseKey, string $plan, string $status, array $features, ?int $expiresAt): array
    {
        $licenseKey = trim($licenseKey);
        if ($licenseKey === '') {
            return ['ok' => false, 'message' => 'license_key is required'];
        }

        $normalizedPlan = in_array($plan, ['free', 'pro', 'enterprise'], true) ? $plan : 'pro';
        $normalizedStatus = in_array($status, ['active', 'inactive', 'expired', 'invalid'], true) ? $status : 'active';

        $featureList = array_values(array_filter(array_map('trim', $features), static fn (string $f): bool => $f !== ''));

        $this->repository->upsertLicense($this->keyHash($licenseKey), $normalizedPlan, $normalizedStatus, $featureList, $expiresAt);
        $this->repository->logEvent('license.issue', ['plan' => $normalizedPlan, 'status' => $normalizedStatus]);

        return ['ok' => true, 'message' => 'license upserted'];
    }

    public function listLicenses(int $limit = 100): array
    {
        return $this->repository->listLicenses($limit);
    }

    private function keyHash(string $licenseKey): string
    {
        return hash('sha256', $licenseKey);
    }

    private function limiterKey(string $licenseKey, string $ip): string
    {
        return hash('sha256', trim($licenseKey) . '|' . trim($ip));
    }

    private function result(string $status, string $plan, array $features, ?int $expiresAt, string $message): array
    {
        return [
            'status' => $status,
            'plan' => $plan,
            'features' => array_values($features),
            'expires_at' => $expiresAt,
            'message' => $message,
        ];
    }
}
