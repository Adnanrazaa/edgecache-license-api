<?php

declare(strict_types=1);

namespace EdgeCache\Controllers;

use EdgeCache\Services\LicenseService;
use EdgeCache\Support\Request;
use EdgeCache\Support\Response;

final class LicenseController
{
    public function __construct(
        private readonly LicenseService $service,
        private readonly string $signingSecret,
        private readonly string $adminToken
    ) {
    }

    public function health(): void
    {
        Response::json(['ok' => true, 'service' => 'edgecache-license-api', 'time' => time()]);
    }

    public function activate(): void
    {
        if (!$this->authorizeSignature()) {
            return;
        }

        $payload = Request::json();
        $result = $this->service->activate(
            (string) ($payload['license_key'] ?? ''),
            (string) ($payload['site_url'] ?? ''),
            Request::ip()
        );

        $statusCode = ($result['status'] ?? 'invalid') === 'active' ? 200 : 422;
        Response::json($result, $statusCode);
    }

    public function verify(): void
    {
        if (!$this->authorizeSignature()) {
            return;
        }

        $payload = Request::json();
        $result = $this->service->verify(
            (string) ($payload['license_key'] ?? ''),
            (string) ($payload['site_url'] ?? ''),
            Request::ip()
        );

        $statusCode = ($result['status'] ?? 'invalid') === 'active' ? 200 : 422;
        Response::json($result, $statusCode);
    }

    public function deactivate(): void
    {
        if (!$this->authorizeSignature()) {
            return;
        }

        $payload = Request::json();
        $result = $this->service->deactivate(
            (string) ($payload['license_key'] ?? ''),
            (string) ($payload['site_url'] ?? '')
        );

        Response::json($result, $result['ok'] ? 200 : 422);
    }

    public function issueLicense(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $payload = Request::json();
        $features = $payload['features'] ?? [];

        $result = $this->service->issueOrUpdateLicense(
            (string) ($payload['license_key'] ?? ''),
            (string) ($payload['plan'] ?? 'pro'),
            (string) ($payload['status'] ?? 'active'),
            is_array($features) ? $features : [],
            isset($payload['expires_at']) ? (int) $payload['expires_at'] : null
        );

        Response::json($result, $result['ok'] ? 200 : 422);
    }

    public function listLicenses(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $rows = $this->service->listLicenses(100);
        Response::json(['items' => $rows]);
    }

    private function authorizeSignature(): bool
    {
        if ($this->signingSecret === '') {
            return true;
        }

        $header = Request::header('X-EdgeCache-Signature') ?? '';
        $valid = \EdgeCache\Support\Signature::verify(Request::bodyRaw(), $header, $this->signingSecret);

        if (!$valid) {
            Response::json(['message' => 'invalid signature'], 401);
            return false;
        }

        return true;
    }

    private function authorizeAdmin(): bool
    {
        if ($this->adminToken === '') {
            Response::json(['message' => 'admin token not configured'], 500);
            return false;
        }

        $header = Request::header('X-EdgeCache-Admin-Token') ?? '';
        if (!hash_equals($this->adminToken, trim($header))) {
            Response::json(['message' => 'unauthorized'], 401);
            return false;
        }

        return true;
    }
}
