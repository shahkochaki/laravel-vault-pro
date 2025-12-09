# shahkochaki / Laravel Vault

Laravel Vault provides a lightweight, production-minded integration between Laravel and HashiCorp Vault. It supports KV v2, token-file authentication (Vault Agent / mounted tokens), configurable caching, and safe runtime config injection so you can fetch secrets at boot or on demand without committing secrets to source control.

Installation (local development via path repository):

1. Add repository to your project's `composer.json`:

```json
"repositories": [
  {
    "type": "path",
    "url": "packages/shahkochaki/laravel-vault"
  }
]
```

2. Require the package:

```bash
composer require shahkochaki/laravel-vault:dev-main
```

3. (Optional) Publish config:

```bash
php artisan vendor:publish --provider="shahkochaki\\Vault\\VaultServiceProvider" --tag=config
```

Usage:

- Set `VAULT_ADDR`, `VAULT_TOKEN`, `VAULT_PATH` in `.env` or `config/vault.php`.
- The package registers a `VaultService` singleton and will attempt to read the configured secret path at boot.

Publishing to Packagist

- Ensure this repository is pushed to GitHub (or another VCS) and has a valid `composer.json`.
- Tag a release: `git tag v1.0.0 && git push --tags`.
- Add the repository to Packagist and follow their instructions to enable automated updates.

Usage example

1. Install via Composer (when published):

```bash
composer require shahkochaki/laravel-vault
```

2. Publish config (optional):

```bash
php artisan vendor:publish --provider="shahkochaki\\Vault\\VaultServiceProvider" --tag=config
```

3. Set `.env` values:

```
VAULT_ADDR=https://vault.example.com:8200
VAULT_TOKEN=your_token_here
VAULT_ENGINE=secret
VAULT_PATH=app/production
VAULT_SECRET=database
```

4. The package will attempt to read the configured secret and apply common DB keys (DB_PASSWORD, DB_USER, DB_HOST, DB_DATABASE) into runtime config.

What's new in v1.1.1

- Support for `token_file` in `config/vault.php`: point this to a local file containing a Vault token (e.g., Vault Agent sink or mounted secret) and the package will use it when `VAULT_TOKEN` is not set.
- Improved error logging and handling for Vault HTTP responses.

Config example (new option):

```php
'token' => env('VAULT_TOKEN', ''),
'token_file' => env('VAULT_TOKEN_FILE', '/var/run/secrets/vault-token'),
```

Notes:

- This package is lightweight and intended as a starting point; for production consider AppRole auth and Vault Agent.
