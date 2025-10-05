# Revmura Suite

**Modules bundle** for Revmura. Load multiple features as internal modules (one plugin), with an admin **Modules** tab (provided by Revmura Manager) to enable/disable each module.

- Works with **Revmura Power Core** (engine) and **Revmura Manager** (UI shell)
- PSR-4 modules under `src/Models/...`
- Feature flags in one option: `revmura_modules_enabled`
- Per-module version storage: `revmura_module_versions` (for future migrations)
- Compatibility gates per module (PHP / WP / Core API)
- Lifecycle hooks per module: `on_enable()`, `on_disable()`, `uninstall()`
- PHPCS-clean (WordPress + PHPCompatibility), CI included

> **Heads-up**: No demo module is registered by default. Add your own modules and register them (see below).

---

## Requirements
- WordPress 6.5+
- PHP 8.3+
- Active: **Revmura Power Core** and **Revmura Manager**

---

## Install
1. Copy to `wp-content/plugins/revmura-suite`
2. `composer install`
3. Activate **Revmura Suite**
4. Go to **Dashboard → Revmura → Modules** to toggle modules

---

## Create a module

1) Create a class implementing the contract:

```php
<?php
declare(strict_types=1);

namespace Revmura\Suite\Models\Offers;

use Revmura\Suite\Models\Contracts\ModuleInterface;

final class OffersModule implements ModuleInterface {
    public function id(): string { return 'offers'; }
    public function label(): string { return __('Offers', 'revmura'); }
    public function version(): string { return '1.0.0'; }
    public function required_core_api_min(): string { return '1.0.0'; }
    public function required_wp_min(): string { return '6.5'; }
    public function required_php_min(): string { return '8.3'; }

    public function boot(): void {
        // Register CPT/Tax etc. (call Core helpers), or hook Manager panels via do_action(...)
    }
    public function on_enable(): void {}
    public function on_disable(): void {}
    public function uninstall(): void {
        // Remove options/tables/meta created by this module.
    }
}

2) Register it in the Suite loader (e.g. inside plugins_loaded in revmura-suite.php or a dedicated bootstrap file):

use Revmura\Suite\Models\ModuleRegistry;
use Revmura\Suite\Models\Offers\OffersModule;

add_action('plugins_loaded', static function (): void {
    if (!defined('REVMURA_CORE_API')) { return; } // Core required
    ModuleRegistry::register(new OffersModule());
}, 30);


The Suite will:

	* Gate by PHP/WP/Core API versions
	* Try to boot() the module (safe try/catch)
	* Store the module version on success
	* Expose toggles in Revmura → Modules
	* Call lifecycle hooks from the toggles UI


Manager integration

If modules want to add their own panel:

add_action('init', static function (): void {
    if (has_action('revmura_manager_register_panel')) {
        do_action('revmura_manager_register_panel', [
            'id'        => 'offers',
            'label'     => __('Offers', 'revmura'),
            'render_cb' => function (): void {
                echo '<div class="wrap"><h2>' . esc_html__('Offers', 'revmura') . '</h2></div>';
            },
        ]);
    }
}, 20);


Development

composer install
vendor/bin/phpcbf .
vendor/bin/phpcs -q --report=summary

PHPCS runs in CI via .github/workflows/phpcs.yml.


License

GPL-2.0-or-later