# Revmura Suite

**Modules bundle** for Revmura. Loads internal modules in one plugin and exposes a **Modules** screen (via Revmura Manager) to toggle them on/off.

- Works with **Revmura Power Core** (engine) and **Revmura Manager** (UI shell)
- PSR‑4 modules under `src/Models/...`
- Feature flags stored in `revmura_modules_enabled`
- Per‑module version store `revmura_module_versions` (future migrations)
- Compatibility gates (PHP / WP / Core API) per module
- Lifecycle hooks: `boot()`, `on_enable()`, `on_disable()`, `uninstall()`

---

## Requirements
- WordPress **6.5+**
- PHP **8.3+**
- Active: **Revmura Power Core** and **Revmura Manager**

---

## Install
1. Copy to `wp-content/plugins/revmura-suite`
2. `composer install`
3. Activate **Revmura Suite**
4. Open **Dashboard → Revmura → Modules**

---

## Create a module (example)

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
        // Register CPT/Tax through Core helpers; or hook Manager panels.
    }
    public function on_enable(): void {}
    public function on_disable(): void {}
    public function uninstall(): void {}
}
```

Register the module during `plugins_loaded`:

```php
use Revmura\Suite\Models\ModuleRegistry;
use Revmura\Suite\Models\Offers\OffersModule;

add_action('plugins_loaded', static function (): void {
    if (!defined('REVMURA_CORE_API')) { return; } // Core required
    ModuleRegistry::register(new OffersModule());
}, 30);
```

---

## Development

```bash
# install dev tools (PHPCS/WPCS/etc.)
composer install

# auto-fix what can be fixed, then check (cross-platform)
composer run lint:fix
composer run lint
```

PHPCS uses the project ruleset (`phpcs.xml.dist`) and also runs in CI via `.github/workflows/phpcs.yml`.

**Raw commands (only if you’re not using composer scripts):**

**Windows (PowerShell):**
```powershell
.\vendor\bin\phpcbf.bat -p -s --standard=phpcs.xml.dist .
.\vendor\bin\phpcs.bat  -q -p -s --standard=phpcs.xml.dist .
```

**macOS/Linux:**
```bash
vendor/bin/phpcbf -p -s --standard=phpcs.xml.dist .
vendor/bin/phpcs  -q -p -s --standard=phpcs.xml.dist .
```

---

## License
GPL‑2.0‑or‑later
