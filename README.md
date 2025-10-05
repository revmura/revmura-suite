# Revmura Suite

**Modules bundle** for Revmura. Load multiple features as internal modules (one plugin), with an admin **Modules** tab (in Manager) to enable/disable each module.

- Works with **Revmura Power Core** (engine) and **Revmura Manager** (UI shell).
- PSR-4 modules in `src/Models/...`
- Feature flags stored in a single option: `revmura_modules_enabled`
- Stores per-module version in `revmura_module_versions` for migrations
- Compatibility gates per module (PHP / WP / Core API)
- Lifecycle hooks: `on_enable`, `on_disable`, `uninstall`
- PHPCS-clean (WordPress + PHPCompatibility)

## Requirements
- WordPress 6.5+
- PHP 8.3+
- Active: Revmura Power Core + Revmura Manager

## Install
Copy to `wp-content/plugins/revmura-suite`, run:

```bash
composer install
