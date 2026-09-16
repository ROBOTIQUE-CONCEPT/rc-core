# RC Module Development Contract

This file is a concise implementation companion to `ARCHITECTURE.md`.

## Mandatory rules

1. A business module depends on RC Core for infrastructure and RC Portal for declarative presentation on `my`; it never depends on another business module.
2. Never import another RC business module namespace.
3. Never call the ERP directly from a business module.
4. Never implement a second cache/logger/translation/placeholder/permission framework.
5. Declare reusable infrastructure in Core.
6. Keep domain rules in the owning module.
7. Keep controllers, admin pages and templates thin.
8. Cross-domain collaboration must use Core contracts, Core events or the internal REST boundary.
9. Use capabilities, not hard-coded role names.
10. Site-to-site business integration must not rely on `switch_to_blog()`.

## Module lifecycle

A migrated module implements:

```php
use WPRC\Core\Container;
use WPRC\Core\Contracts\ModuleInterface;

final class Module implements ModuleInterface
{
    public function id(): string
    {
        return 'assets';
    }

    public function version(): string
    {
        return RC_ASSETS_VERSION;
    }

    public function minimumCoreVersion(): string
    {
        return '0.6.0-alpha2';
    }

    public function register(Container $container): void
    {
        rc_register_capabilities($this->id(), [
            'rc_assets_read',
            'rc_assets_edit',
        ]);

        // Register module-owned services here.
    }

    public function boot(): void
    {
        // Register WordPress runtime hooks here.
    }
}
```

The plugin file registers the module early enough for Core's lifecycle event:

```php
add_action('wprc/core/register_modules', static function (): void {
    rc_register_module(new Module());
});
```

## Forbidden example

Inside RC Interventions:

```php
use WPRC\Assets\AssetRepository; // Forbidden.
```

Use a contract defined by Core or an event instead.

## ERP

```php
$productProvider = rc_core()->erp()->get(
    \WPRC\Core\Contracts\ERP\ProductProviderInterface::class
);
```

Do not use `wp_remote_*()` from a business module.

## Runtime context

```php
$context = rc_core()->requestContext();

if ($context->isRest()) {
    // REST-specific adapter only.
}
```

Business services must not depend on this context unless the concern is genuinely runtime-specific.

## Preflight

```bash
php tools/architecture-preflight.php /path/to/rc-core /path/to/module
```

A module is not releasable when the architecture preflight fails.


## Presentation surfaces

RC Portal is the only renderer/layout/CSS/JS owner for the `my` application. Business modules may depend on RC Portal's declarative UI definitions and registry, but must not render HTML or ship presentation CSS/JS. Modules provide semantic page/table/form/detail definitions and data providers only.

Core remains responsible for non-visual infrastructure, contracts, security, roles/capabilities, REST foundations and cross-module service registration. Every page declares an explicit capability. State-changing UI operations use WordPress nonces; browser REST uses the standard `wp_rest` nonce.
