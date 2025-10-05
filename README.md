# Revmura Suite

**Modules bundle** for Revmura. Load multiple features as internal modules (one plugin), with an admin **Modules** tab (in Manager) to enable/disable each module.

- Works with **Revmura Power Core** (engine) and **Revmura Manager** (UI shell).
- PSR-4 modules in `src/Modules/...`
- Feature flags stored in a single option: `revmura_modules_enabled`
- Stores per-module version in `revmura_module_versions` for migrations
- Compatibility gates per module (PHP / WP / Core API)
- Lifecycle hooks: `on_enable`, `on_disable`, `uninstall`
- PHPCS-clean (WordPress + PHPCompatibility)

## Requirements
- WordPress 6.5+
- PHP 8.3+
- Revmura Power Core + Revmura Manager active

## Install
Copy to `wp-content/plugins/revmura-suite`, run `composer install`, activate.  
Open **Dashboard → Revmura → Modules** to toggle modules.

## Create a module
Implement `Revmura\Suite\Modules\Contracts\ModuleInterface` and register it in `plugins_loaded`:
```php
\Revmura\Suite\Modules\ModuleRegistry::register( new YourModule() );
```
The Suite loader will:
- gate by PHP/WP/Core API,
- try `boot()` in a try/catch,
- store the module version on success,
- call lifecycle hooks from the toggles UI.
