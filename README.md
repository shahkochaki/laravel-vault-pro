echo $VAULT_ADDR

# Laravel Vault

[![Latest Version](https://img.shields.io/packagist/v/shahkochaki/laravel-vault.svg)](https://packagist.org/packages/shahkochaki/laravel-vault)
[![License](https://img.shields.io/packagist/l/shahkochaki/laravel-vault.svg)](https://packagist.org/packages/shahkochaki/laravel-vault)

Laravel Vault is a lightweight, production-minded integration between Laravel and HashiCorp Vault. It focuses on making it easy to fetch KV v2 secrets, cache them, and safely inject runtime configuration (for example, database credentials) without committing secrets to source control.

Key features

- Full KV v2 support (automatically constructs v2 API paths)
- Token-file support for Vault Agent and container environments
- Configurable caching using Laravel's cache repository
- Safe runtime config injection for common keys (DB credentials)
- Graceful error handling and logging
- Compatible with Laravel 9, 10, 11, 12

---

## Installation

Install the package via Composer:

```bash
composer require shahkochaki/laravel-vault
```

The package uses Laravel's package auto-discovery.

Optionally publish the configuration file:

```bash
php artisan vendor:publish --provider="Shahkochaki\\Vault\\VaultServiceProvider" --tag=config
```

This creates `config/vault.php` in your application.

### Environment variables

Edit your `.env`:

```env
VAULT_ADDR=https://vault.example.com:8200
VAULT_TOKEN=your_vault_token_here
VAULT_ENGINE=secret
VAULT_PATH=app/production
VAULT_SECRET=database
```

For production with Vault Agent (recommended):

```env
VAULT_ADDR=https://vault.example.com:8200
VAULT_TOKEN=
VAULT_TOKEN_FILE=/var/run/secrets/vault-token
VAULT_ENGINE=secret
VAULT_PATH=app/production
VAULT_SECRET=database
```

---

## Usage

The package registers a singleton `Shahkochaki\\Vault\\VaultService` that you can inject, resolve from the container, or use in any Laravel context.

### Simple example — read a secret

```php
<?php

namespace App\\Http\\Controllers;

use Shahkochaki\\Vault\\VaultService;

class ExampleController extends Controller
{
    public function index(VaultService $vault)
    {
        // Read secret from `app/production/database`
        $secret = $vault->getSecret('app/production/database');

        if ($secret) {
            echo 'DB user: ' . ($secret['DB_USER'] ?? 'N/A');
            echo 'DB password: ' . ($secret['DB_PASSWORD'] ?? 'N/A');
        } else {
            echo 'Secret not found or Vault unavailable';
        }

        return response()->json($secret);
    }
}
```

### Constructor dependency injection

```php
use Shahkochaki\\Vault\\VaultService;

class PaymentService
{
    protected VaultService $vault;

    public function __construct(VaultService $vault)
    {
        $this->vault = $vault;
    }

    public function getApiCredentials(): array
    {
        $credentials = $this->vault->getSecret('app/payment/stripe') ?? [];
        return [
            'api_key' => $credentials['STRIPE_KEY'] ?? null,
            'secret' => $credentials['STRIPE_SECRET'] ?? null,
        ];
    }
}
```

### Resolve from container

```php
$vault = app(Shahkochaki\\Vault\\VaultService::class);
$secret = $vault->getSecret('my/secret/path');
```

### Clear cache for a secret

```php
$vault->clearCache('app/production/database');
$fresh = $vault->getSecret('app/production/database');
```

---

## Configuration (`config/vault.php`)

```php
<?php

return [
    'addr' => env('VAULT_ADDR', 'http://127.0.0.1:8200'),
    'token' => env('VAULT_TOKEN', ''),
    'token_file' => env('VAULT_TOKEN_FILE', ''),
    'engine' => env('VAULT_ENGINE', 'secret'),
    'path' => env('VAULT_PATH', ''),
    'timeout' => 5,
    'cache_ttl' => 30,
    'test' => env('VAULT_TEST', 'vault_config_test_value'),
];
```

### Common environment settings

Development:

```env
VAULT_ADDR=http://localhost:8200
VAULT_TOKEN=dev-token
VAULT_ENGINE=secret
```

Production (with Vault Agent):

```env
VAULT_ADDR=https://vault.example.com:8200
VAULT_TOKEN=
VAULT_TOKEN_FILE=/var/run/secrets/vault-token
VAULT_ENGINE=secret
VAULT_PATH=app/production
```

---

## Typical scenarios

### 1) Runtime DB config injection

Store a secret in Vault with keys used by your database connection:

```json
{
  "DB_PASSWORD": "super_secret_password",
  "DB_USER": "app_user",
  "DB_HOST": "db.example.com",
  "DB_DATABASE": "production_db"
}
```

Set `.env`:

```env
VAULT_PATH=app/production
VAULT_SECRET=database
```

The service provider will attempt to fetch this secret at boot and patch `config('database.connections.mysql')` accordingly.

### 2) API keys / third-party credentials

Store credentials under a path like `app/services/stripe` and fetch them at runtime:

```php
$stripe = app(\Shahkochaki\\Vault\\VaultService::class)->getSecret('app/services/stripe');
\Stripe\Stripe::setApiKey($stripe['STRIPE_SECRET']);
```

### 3) Jobs / queued workers

Inject the `VaultService` into jobs; secrets will be fetched using the configured caching strategy.

### 4) Artisan commands

Use the service inside commands to fetch credentials for administrative tasks.

---

## Vault paths and KV v2

When you provide a logical path (e.g. `app/production/database`) the package builds the KV v2 API path:

```
Input Path: app/production/database
Vault API Path: /v1/secret/data/app/production/database
```

If you pass a full API path starting with `v1/` or `/v1/` the library will use it as-is.

---

## Token files and Vault Agent

For production, prefer using Vault Agent or short-lived auth methods. Vault Agent can write a token to a filesystem sink which you can reference via `VAULT_TOKEN_FILE`.

Example `vault-agent.hcl` snippet:

```hcl
auto_auth {
  method "approle" {
    config = {
      role_id_file_path = "/etc/vault/role-id"
      secret_id_file_path = "/etc/vault/secret-id"
    }
  }

  sink "file" {
    config = { path = "/var/run/secrets/vault-token" }
  }
}
```

Set `VAULT_TOKEN_FILE=/var/run/secrets/vault-token` in your environment.

---

## Error handling & logging

`getSecret()` returns an associative array on success or `null` on failure. The service logs warnings on errors but does not break application boot.

Example:

```php
$secret = $vault->getSecret('non/existent/path');
if ($secret === null) {
    Log::warning('Vault secret unavailable, falling back to defaults');
}
```

Check `storage/logs/laravel.log` for Vault-related warnings.

---

## Caching & performance

- Default TTL: 30 seconds
- Configure TTL via `config('vault.cache_ttl')`
- Clear cache: `$vault->clearCache($path)`

Example:

```php
$vault->clearCache('app/db');
$secret = $vault->getSecret('app/db');
```

---

## Testing & development

Run a local Vault dev server for testing:

```bash
vault server -dev

# In another shell
export VAULT_ADDR='http://127.0.0.1:8200'
export VAULT_TOKEN='root'

vault kv put secret/app/test DB_PASSWORD=test123 DB_USER=testuser
```

Then in Laravel Tinker:

```php
>>> $vault = app(\Shahkochaki\\Vault\\VaultService::class);
>>> $secret = $vault->getSecret('app/test');
>>> dd($secret);
```

---

## Troubleshooting

- `Connection refused` — check `VAULT_ADDR` and Vault health endpoint: `curl -k $VAULT_ADDR/v1/sys/health`
- `403` — verify token policies and permissions
- Secret returns `null` — enable debug logs and inspect cache

---

## Changelog

See `CHANGELOG.md` for release notes. Current: **1.1.1**

---

## Contributing & Support

- Report bugs and request features on GitHub Issues: https://github.com/shahkochaki/laravel-vault/issues
- PRs welcome; please follow repository contribution guidelines.

---

## License

MIT — see `LICENSE` for details.

---

Made with ❤️ for the Laravel and HashiCorp Vault communities.

Author: shahkochaki (https://github.com/shahkochaki)
