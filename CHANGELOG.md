# Changelog

All notable changes to this project will be documented in this file.

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
