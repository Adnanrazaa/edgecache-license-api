# EdgeCache License API (Local)

Local-first licensing backend for EdgeCache Optimizer.

## Stack

- PHP 8.0+
- SQLite (file DB)
- No external dependencies

## Quick Start

1. Copy env:

```bash
cp .env.example .env
```

2. Set your keys in `.env`:

- `EDGECACHE_MASTER_KEY`
- `ADMIN_TOKEN`
- optional `SIGNING_SECRET`

3. Run local server:

```bash
php -S 127.0.0.1:8080 -t public
```

## Public Endpoints

- `GET /v1/health`
- `POST /v1/license/activate`
- `POST /v1/license/verify`
- `POST /v1/license/deactivate`

Request body for activate/verify/deactivate:

```json
{
  "license_key": "your-key",
  "site_url": "https://example.com"
}
```

Response shape (`activate` and `verify`):

```json
{
  "status": "active|inactive|expired|invalid",
  "plan": "free|pro|enterprise",
  "features": ["prefetch", "analytics"],
  "expires_at": null,
  "message": "..."
}
```

## Internal Admin Endpoints

Header required: `X-EdgeCache-Admin-Token: <ADMIN_TOKEN>`

- `GET /v1/internal/licenses`
- `POST /v1/internal/licenses`

Create/update request body:

```json
{
  "license_key": "customer-license-123",
  "plan": "pro",
  "status": "active",
  "features": ["prefetch", "analytics"],
  "expires_at": null
}
```

## Security Notes

- If `SIGNING_SECRET` is set, public POST endpoints require:
  - `X-EdgeCache-Signature: <hex hmac sha256 of raw body>`
- Rate limiting is enforced per `license_key + IP`.
- License keys are stored as SHA-256 hashes only.
