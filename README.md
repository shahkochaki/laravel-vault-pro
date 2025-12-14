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
php artisan vendor:publish --provider="Shahkochaki\Vault\VaultServiceProvider" --tag=config
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

### 🐳 Docker / Kubernetes Setup

**If you're using Docker, Docker Compose, or Kubernetes**, you should use **VAULT mode** instead of the default DOTENV mode. This is because container environments typically don't have a `.env` file and instead use environment variables directly.

#### Docker Compose Example

```yaml
version: "3.8"

services:
  app:
    image: your-laravel-app
    environment:
      # Vault Configuration
      - VAULT_ADDR=http://vault:8200
      - VAULT_TOKEN=${VAULT_TOKEN}
      - VAULT_ENGINE=secret
      - VAULT_PATH=app/production
      - VAULT_SECRET=database

      # Important: Use VAULT sync mode for Docker
      - VAULT_SYNC_MODE=vault

      # Optional: Control what gets updated
      - VAULT_UPDATE_ENV=true
      - VAULT_UPDATE_CONFIG=true
    depends_on:
      - vault

  vault:
    image: vault:latest
    ports:
      - "8200:8200"
```

#### Kubernetes Example

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
data:
  VAULT_ADDR: "http://vault.vault.svc.cluster.local:8200"
  VAULT_ENGINE: "secret"
  VAULT_PATH: "app/production"
  VAULT_SECRET: "database"
  VAULT_SYNC_MODE: "vault" # Important for Kubernetes!

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    spec:
      containers:
        - name: app
          image: your-laravel-app:latest
          envFrom:
            - configMapRef:
                name: laravel-config
          env:
            - name: VAULT_TOKEN
              valueFrom:
                secretKeyRef:
                  name: vault-token
                  key: token
```

#### Why use `VAULT_SYNC_MODE=vault` for Docker?

- ✅ No `.env` file needed in the container
- ✅ Environment variables come from Docker/Kubernetes
- ✅ Vault fills in only the missing/empty variables
- ✅ Perfect for microservices and orchestrated deployments
- ✅ Works seamlessly with CI/CD pipelines

**How it works:**

1. Your orchestrator (Docker/K8s) sets base environment variables
2. Laravel Vault reads all secrets from Vault
3. For each secret, it checks if `env()` is empty
4. Only empty/missing variables are filled from Vault
5. Your existing environment variables are preserved

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
        $secret = $vault->read('app/production/database');

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
        $credentials = $this->vault->read('app/payment/stripe') ?? [];
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
$secret = $vault->read('my/secret/path');
```

### Clear cache for a secret

```php
$vault->clearCache('app/production/database');
$fresh = $vault->read('app/production/database');
```

### Backward compatibility

The old `getSecret()` method is still available for backward compatibility:

```php
// Old method (still works)
$secret = $vault->getSecret('app/production/database');

// New method (recommended)
$secret = $vault->read('app/production/database');
```

### Runtime engine and path configuration

You can dynamically change the Vault engine and base path at runtime without modifying config:

```php
use Shahkochaki\\Vault\\VaultService;

$vault = app(VaultService::class);

// Use default engine and path from config
$secret1 = $vault->read('database');

// Switch to a different engine
$vault->setEngine('kv-v1');
$secret2 = $vault->read('database');

// Use another custom engine
$vault->setEngine('custom-engine');
$secret3 = $vault->read('database');

// Change base path dynamically
$vault->setPath('app/staging');
$secret4 = $vault->read('database'); // Reads from app/staging/database

// Chain methods for both engine and path
$vault->setEngine('secret')->setPath('app/production');
$secret5 = $vault->read('api/credentials');

// Reset to config defaults
$vault->resetEngine()->resetPath();
$secret6 = $vault->read('path/to/secret');
```

**Get current engine and path:**

```php
$currentEngine = $vault->getEngine(); // Returns current engine name
$currentPath = $vault->getPath();     // Returns current base path
```

**Important Note:** 🔒

Runtime customizations (`setEngine()`, `setPath()`) only affect **your manual API calls**. The automatic environment synchronization by `VaultServiceProvider` always uses the values from your `.env` file and `config/vault.php`, ensuring consistency and predictability.

```php
// In your controller or service
$vault->setPath('custom/path')->read('secret'); // Uses custom path ✓

// Meanwhile, VaultServiceProvider auto-sync
// Still reads from VAULT_PATH in .env ✓
// Your runtime changes don't affect it!
```

**Use cases:**

- You have multiple KV engines in Vault
- Different secrets are stored in different engines
- You need to switch between KV v1 and KV v2 engines
- Working with custom secret engines
- Multi-environment configurations (dev/staging/production)
- Per-tenant secret paths in SaaS applications
- Namespace isolation for microservices

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

## 🔄 Dual Sync Modes (New in v1.3.3)

The package supports **two sync modes** to fit different deployment environments:

### 1️⃣ DOTENV Mode (Default)

Perfect for traditional deployments with `.env` files.

**How it works:**

1. Package reads your `.env` file and finds all **empty** keys (keys with no value)
2. Package fetches secrets from Vault at the configured path
3. For each empty key, if it exists in Vault, the package updates the environment variable and/or Laravel config

This approach gives you **full control** - only keys you define in `.env` (even if empty) will be synced from Vault.

### 2️⃣ VAULT Mode (New!)

Perfect for Docker, Kubernetes, and container environments where `.env` doesn't exist.

**How it works:**

1. Package fetches **all** secrets from Vault
2. For each secret, checks if `env()` is empty or not set
3. Only applies secrets that are missing or empty in the environment

This is ideal when you set environment variables via `docker-compose.yml`, Kubernetes ConfigMaps, or orchestration tools.

### Configuration

Set the sync mode in your `.env` or `config/vault.php`:

```env
# DOTENV mode (default) - for traditional .env files
VAULT_SYNC_MODE=env

# VAULT mode - for Docker/Kubernetes environments
VAULT_SYNC_MODE=vault
```

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

See `CHANGELOG.md` for release notes. Current: **1.3.4**

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
