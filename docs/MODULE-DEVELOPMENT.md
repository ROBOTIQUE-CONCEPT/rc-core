# RC Module Development Contract

This file is a concise implementation companion to `ARCHITECTURE.md`.

> **Implementation status (2026-09-19):** the module lifecycle shown below
> (`ModuleInterface`, `rc_register_module()`, the `wprc/core/register_modules`
> action) is real, shipped Core code — but `rc-portal`'s actual embedded
> modules do not use it. They implement a different, `rc-portal`-owned
> interface instead (`EmbeddedModuleInterface`, discovered by
> `ModuleCatalog::loadFromDirectory()` globbing `modules/*/module.php`),
> with a different shape (`descriptor(): ModuleDescriptor` instead of
> separate `id()`/`version()`/`minimumCoreVersion()` methods, and no
> `Container` parameter on `register()`). Which of the two should be
> canonical for an embedded `my`-side module is an open question — see
> `rc-core/docs/ARCHITECTURE-OPEN-QUESTIONS.md`. Don't assume the example
> below reflects what a module in `rc-portal/modules/` actually looks like
> today; read an existing one (e.g. `rc-portal/modules/products/module.php`)
> instead. Separately, the "Presentation surfaces" section further down is
> also not yet implemented — see its own note and `docs/PORTAL-UI.md`.

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

> **Implementation status (2026-09-19):** confirmed as the target with no
> exception; not yet implemented. See `docs/PORTAL-UI.md`'s status note.

RC Portal is the only renderer/layout/CSS/JS owner for the `my` application. Business modules may depend on RC Portal's declarative UI definitions and registry, but must not render HTML or ship presentation CSS/JS. Modules provide semantic page/table/form/detail definitions and data providers only.

Core remains responsible for non-visual infrastructure, contracts, security, roles/capabilities, REST foundations and cross-module service registration. Every page declares an explicit capability. State-changing UI operations use WordPress nonces; browser REST uses the standard `wp_rest` nonce.
