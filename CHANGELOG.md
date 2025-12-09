# Changelog

All notable changes to this project will be documented in this file.

## [1.2.1] - 2025-12-09

### Fixed

- **Corrected sync logic flow**: Now correctly reads empty keys from `.env` first, then checks Vault (instead of iterating Vault keys first)
- Improved logging to show which empty keys were found and which were applied from Vault
- Better handling of empty value detection in `.env` file (strips quotes properly)

### Changed

- Renamed `getEnvKeys()` to `getEmptyEnvKeys()` to better reflect its purpose
- Enhanced debug logging throughout the sync process

## [1.2.0] - 2025-12-09

### Added

- **Auto-sync with .env file**: Package now automatically reads `.env` file and syncs empty variables from Vault
- **Flexible sync control**: New config options `update_env` and `update_config` to control what gets updated
- **Custom config mappings**: Define custom mappings between env variables and Laravel config paths via `config_mappings`
- Built-in config mappings for common Laravel services (Database, Redis, Mail, AWS, Cache, Queue, Session)
- New `getEnvKeys()` method to read and parse `.env` file keys

### Changed

- Secret application logic now only processes keys that exist in `.env` file
- Config mappings now support custom user-defined mappings with priority over defaults
- Improved documentation with comprehensive examples of new features

### Notes

- This is a major feature release that changes how secrets are applied
- Backward compatible with existing configurations
- Recommended to review and test sync behavior before deploying to production

## [1.1.3] - 2025-12-09

### Fixed

- Improved address validation in `VaultServiceProvider::register()` to prevent port being added to empty addresses
- Added `runningInConsole()` check in `VaultServiceProvider::boot()` to skip Vault fetching during artisan commands (config:cache, cache:clear, etc.)
- Better handling of base_uri construction when address or port is missing

### Changed

- Refactored address construction logic for better reliability and validation

## [1.1.2] - 2025-12-09

### Added

- Honor `port` config / `VAULT_PORT` when building the Vault base URI.
- Improved README with a complete English usage guide and examples.

### Changed

- Documentation improvements and packaging metadata updates.

### Notes

- This release is a documentation and small feature bump (port support).

## [1.1.1] - 2025-12-09

### Added

- Support for reading Vault token from a `token_file` (useful for Vault Agent or mounted secrets).
- Improved HTTP status logging and graceful handling of 404 responses.

### Changed

- Minor docs updates and packaging metadata improvements.

### Fixed

- Better base URI normalization in the service provider.
