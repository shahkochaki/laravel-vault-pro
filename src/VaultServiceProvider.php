<?php

namespace shahkochaki\Vault;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class VaultServiceProvider extends ServiceProvider
{
    private static $bootApplied = false;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/vault.php', 'vault');

        $this->app->singleton(VaultService::class, function ($app) {
            $config = $app['config']->get('vault', []);
            $rawAddr = $config['addr'] ?? env('VAULT_ADDR', '');
            $addr = trim((string) $rawAddr);

            // Only proceed with address construction if we have a non-empty address
            if ($addr !== '') {
                // Add protocol if missing
                if (!preg_match('#^https?://#i', $addr)) {
                    $addr = 'http://' . $addr;
                }

                // If a port is provided separately and the address doesn't already include one, append it
                $port = $config['port'] ?? env('VAULT_PORT', null);
                if ($port && !preg_match('#:\\d+(?:$|/)#', $addr)) {
                    $addr = rtrim($addr, '/') . ':' . $port;
                }
            }

            $base = rtrim($addr, '/');

            $client = new Client([
                'base_uri' => $base !== '' ? $base . '/' : null,
                'timeout' => $config['timeout'] ?? 5,
            ]);
            // use logger from container
            $logger = $app->make(LoggerInterface::class);
            return new VaultService($client, $app['cache.store'], $logger, $config);
        });
    }

    public function boot()
    {
        // publish config
        $this->publishes([
            __DIR__ . '/../config/vault.php' => config_path('vault.php'),
        ], 'config');

        // Skip boot logic if already applied OR if running in console (artisan commands)
        // This prevents issues with cache:clear, config:cache, etc.
        if (self::$bootApplied || $this->app->runningInConsole()) {
            return;
        }

        try {
            $vault = $this->app->make(VaultService::class);
            if (!$vault) return;

            $config = $this->app['config']->get('vault', []);
            $path = $config['path'] ?? env('VAULT_PATH', '');
            $secretName = env('VAULT_SECRET', '');

            if ($path === '') {
                $secretPath = $secretName;
            } else {
                if (preg_match('#/data/[^/]+$#', $path)) {
                    $secretPath = $path;
                } elseif (preg_match('#/data$#', $path)) {
                    $secretPath = rtrim($path, '/') . '/' . $secretName;
                } else {
                    $secretPath = rtrim($path, '/') . '/' . $secretName;
                }
            }

            $secretPath = trim((string) $secretPath);
            if ($secretPath === '') {
                Log::debug('VaultServiceProvider: secretPath is empty; skipping Vault fetch.');
                return;
            }

            $secret = $vault->getSecret($secretPath);
            if (!is_array($secret)) {
                Log::debug('VaultServiceProvider: no secret found at ' . $secretPath);
                return;
            }

            // Apply all secrets from Vault
            $secretUpper = array_change_key_case($secret, CASE_UPPER);
            $updateEnv = $config['update_env'] ?? true;
            $updateConfig = $config['update_config'] ?? true;

            // Get all env keys from .env file
            $envKeys = $this->getEnvKeys();

            foreach ($secretUpper as $key => $value) {
                // Check if this key exists in .env file
                if (!in_array($key, $envKeys, true)) {
                    continue;
                }

                // Check if the env variable is empty or not set
                $envValue = env($key);

                if ($envValue === null || $envValue === '') {
                    // Apply to specific config paths based on key name (if enabled)
                    if ($updateConfig) {
                        $this->applySecretToConfig($key, $value);
                    }

                    // Also set it as a runtime environment variable (if enabled)
                    if ($updateEnv) {
                        putenv("{$key}={$value}");
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }

                    Log::debug("VaultServiceProvider: Applied {$key} from Vault (env: {$updateEnv}, config: {$updateConfig})");
                }
            }

            self::$bootApplied = true;
        } catch (\Throwable $e) {
            Log::warning('VaultServiceProvider bootstrap vault fetch failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all keys from .env file
     *
     * @return array
     */
    private function getEnvKeys(): array
    {
        $envPath = $this->app->environmentPath();
        $envFile = $this->app->environmentFile();
        $fullPath = $envPath . DIRECTORY_SEPARATOR . $envFile;

        if (!file_exists($fullPath)) {
            Log::debug("VaultServiceProvider: .env file not found at {$fullPath}");
            return [];
        }

        $keys = [];
        $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);

                if ($key !== '') {
                    $keys[] = strtoupper($key);
                }
            }
        }

        return $keys;
    }

    /**
     * Apply a secret value to the appropriate config path
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function applySecretToConfig(string $key, $value): void
    {
        // Get custom mappings from config
        $customMappings = $this->app['config']->get('vault.config_mappings', []);

        // Database configuration mappings
        $dbMappings = [
            'DB_PASSWORD' => 'database.connections.mysql.password',
            'DB_USERNAME' => 'database.connections.mysql.username',
            'DB_USER' => 'database.connections.mysql.username',
            'DB_HOST' => 'database.connections.mysql.host',
            'DB_PORT' => 'database.connections.mysql.port',
            'DB_DATABASE' => 'database.connections.mysql.database',
        ];

        // Cache configuration mappings
        $cacheMappings = [
            'CACHE_DRIVER' => 'cache.default',
            'REDIS_HOST' => 'database.redis.default.host',
            'REDIS_PASSWORD' => 'database.redis.default.password',
            'REDIS_PORT' => 'database.redis.default.port',
        ];

        // Queue configuration mappings
        $queueMappings = [
            'QUEUE_CONNECTION' => 'queue.default',
        ];

        // Mail configuration mappings
        $mailMappings = [
            'MAIL_MAILER' => 'mail.default',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_PORT' => 'mail.mailers.smtp.port',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'MAIL_ENCRYPTION' => 'mail.mailers.smtp.encryption',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'MAIL_FROM_NAME' => 'mail.from.name',
        ];

        // Session configuration mappings
        $sessionMappings = [
            'SESSION_DRIVER' => 'session.driver',
        ];

        // AWS configuration mappings
        $awsMappings = [
            'AWS_ACCESS_KEY_ID' => 'services.aws.key',
            'AWS_SECRET_ACCESS_KEY' => 'services.aws.secret',
            'AWS_DEFAULT_REGION' => 'services.aws.region',
            'AWS_BUCKET' => 'filesystems.disks.s3.bucket',
        ];

        // Merge all mappings (custom mappings have priority)
        $allMappings = array_merge(
            $dbMappings,
            $cacheMappings,
            $queueMappings,
            $mailMappings,
            $sessionMappings,
            $awsMappings,
            $customMappings // Custom mappings override default ones
        );

        if (isset($allMappings[$key])) {
            config([$allMappings[$key] => $value]);
        }

        // Special case for VAULT_TEST or custom vault config
        if (str_starts_with($key, 'VAULT_')) {
            $configKey = 'vault.' . strtolower(substr($key, 6));
            config([$configKey => $value]);
        }
    }
}
