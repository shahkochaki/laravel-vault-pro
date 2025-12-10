# 🔐 Laravel Vault - Complete HashiCorp Vault Integration

[![Latest Version](https://img.shields.io/packagist/v/shahkochaki/laravel-vault.svg)](https://packagist.org/packages/shahkochaki/laravel-vault)
[![Total Downloads](https://img.shields.io/packagist/dt/shahkochaki/laravel-vault.svg)](https://packagist.org/packages/shahkochaki/laravel-vault)
[![License](https://img.shields.io/packagist/l/shahkochaki/laravel-vault.svg)](https://packagist.org/packages/shahkochaki/laravel-vault)

**The most powerful and developer-friendly HashiCorp Vault integration for Laravel.** Automatically sync secrets, manage credentials securely, and deploy with confidence - all without committing sensitive data to your repository.

## ✨ Why Laravel Vault?

Stop managing secrets manually. Let Laravel Vault handle it automatically:

- 🔄 **Auto-sync secrets** from Vault directly to your `.env` file
- 🎯 **Zero-config setup** - works out of the box with sensible defaults
- 🚀 **Production-ready** - built for Kubernetes, Docker, and cloud deployments
- 🔒 **Secure by default** - never commit credentials again
- ⚡ **Smart caching** - fast performance without compromising security
- 🛠️ **Flexible configuration** - adapt to any workflow

## 🎯 Key Features

### Core Features

- **🔄 Automatic .env Sync**: Reads your `.env`, finds empty keys, and fills them from Vault
- **⚙️ Flexible Control**: Choose to update environment variables, Laravel configs, or both
- **🎨 Custom Mappings**: Define your own mappings between env variables and config paths
- **📦 Full KV v2 Support**: Automatically constructs proper v2 API paths
- **🔑 Vault Agent Ready**: Token-file support for container and Kubernetes environments
- **⚡ Smart Caching**: Configurable caching using Laravel's cache system
- **🎯 Auto Config Injection**: Built-in support for Database, Redis, Mail, AWS, and more
- **🛡️ Error Resilient**: Graceful error handling with detailed logging
- **🔧 Laravel 9-12 Compatible**: Works with all modern Laravel versions

### Built-in Config Mappings

Out-of-the-box support for:

- 💾 **Database**: MySQL, PostgreSQL, SQL Server
- 🔴 **Redis**: Connection credentials and settings
- 📧 **Mail**: SMTP, Mailgun, SES configurations
- ☁️ **AWS**: S3, SQS, SNS credentials
- 🔄 **Queue**: Redis, SQS, Beanstalkd
- 💿 **Cache**: Redis, Memcached
- 🔐 **Session**: Database, Redis storage

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
# Option A: include port in the address
VAULT_ADDR=https://vault.example.com:8200
VAULT_TOKEN=your_vault_token_here
VAULT_ENGINE=secret
VAULT_PATH=app/production
VAULT_SECRET=database

# Option B: provide host and port separately
# VAULT_ADDR may be scheme+host only; set VAULT_PORT to append when building the base URI
VAULT_ADDR=https://vault.example.com
VAULT_PORT=8200
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
    'addr' => env('VAULT_ADDR', 'http://127.0.0.1'),
    'token' => env('VAULT_TOKEN', ''),
    'token_file' => env('VAULT_TOKEN_FILE', ''),
    'port' => env('VAULT_PORT', 8200),
    'engine' => env('VAULT_ENGINE', 'secret'),
    'path' => env('VAULT_PATH', ''),
    'timeout' => 5,
    'cache_ttl' => 300,

    // Auto-update settings
    'update_env' => env('VAULT_UPDATE_ENV', true),
    'update_config' => env('VAULT_UPDATE_CONFIG', true),

    // Custom config mappings (ENV_KEY => config.path)
    'config_mappings' => [
        // Example: 'MY_API_KEY' => 'services.myapi.key',
    ],
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

## Auto-sync with .env file (New in v1.2.0)

The package now automatically reads your `.env` file and syncs any empty environment variables from Vault. This eliminates the need to manually specify which keys to fetch.

### How it works

1. Package reads your `.env` file and finds all **empty** keys (keys with no value)
2. Package fetches secrets from Vault at the configured path
3. For each empty key, if it exists in Vault, the package updates the environment variable and/or Laravel config

This approach gives you **full control** - only keys you define in `.env` (even if empty) will be synced from Vault.

### Example

Your `.env` file:

```env
APP_NAME=MyApp
DB_HOST=
DB_PASSWORD=
MAIL_PASSWORD=
MY_API_KEY=
```

Your Vault secret at `app/production/database`:

```json
{
  "DB_HOST": "mysql.server.com",
  "DB_PASSWORD": "secret123",
  "MAIL_PASSWORD": "mailpass",
  "MY_API_KEY": "key_xxxxx",
  "RANDOM_KEY": "will_be_ignored"
}
```

**What happens:**

1. Package finds empty keys in `.env`: `DB_HOST`, `DB_PASSWORD`, `MAIL_PASSWORD`, `MY_API_KEY`
2. Package gets all secrets from Vault
3. For each empty key, checks if it exists in Vault:
   - ✅ `DB_HOST` → Found in Vault → Applied
   - ✅ `DB_PASSWORD` → Found in Vault → Applied
   - ✅ `MAIL_PASSWORD` → Found in Vault → Applied
   - ✅ `MY_API_KEY` → Found in Vault → Applied
4. Keys in Vault but not in `.env` are ignored:
   - ❌ `RANDOM_KEY` → Not in `.env` → Ignored
5. Keys with values are not touched:
   - ❌ `APP_NAME` → Already has value → Not processed

### Control sync behavior

```env
# Update environment variables (default: true)
VAULT_UPDATE_ENV=true

# Update Laravel configs (default: true)
VAULT_UPDATE_CONFIG=true
```

**Disable env updates, only update configs:**

```env
VAULT_UPDATE_ENV=false
VAULT_UPDATE_CONFIG=true
```

**Disable both (read-only mode):**

```env
VAULT_UPDATE_ENV=false
VAULT_UPDATE_CONFIG=false
```

### Custom config mappings

Define your own mappings in `config/vault.php`:

```php
'config_mappings' => [
    'MY_API_KEY' => 'services.myapi.key',
    'STRIPE_SECRET' => 'services.stripe.secret',
    'CUSTOM_VALUE' => 'app.custom.value',
],
```

Now when `MY_API_KEY` is synced from Vault, it will automatically update `config('services.myapi.key')`.

### Built-in config mappings

The package includes default mappings for common Laravel services:

- **Database**: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- **Redis**: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- **Mail**: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`
- **AWS**: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`
- **Cache**: `CACHE_DRIVER`
- **Queue**: `QUEUE_CONNECTION`
- **Session**: `SESSION_DRIVER`

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

See `CHANGELOG.md` for release notes. Current: **1.2.2**

---

## Contributing & Support

- Report bugs and request features on GitHub Issues: https://github.com/shahkochaki/laravel-vault-pro/issues
- PRs welcome; please follow repository contribution guidelines.

---

## License

MIT — see `LICENSE` for details.

---

Made with ❤️ for the Laravel and HashiCorp Vault communities.

Author: shahkochaki (https://github.com/shahkochaki)
