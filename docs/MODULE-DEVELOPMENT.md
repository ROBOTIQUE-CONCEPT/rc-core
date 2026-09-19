# RC Module Development Contract

This file is a concise implementation companion to `ARCHITECTURE.md`.

> **Status (2026-09-19, corrected):** two real, separate module-lifecycle
> contracts coexist today, scoped by repository. `rc-core` ships
> `Contracts\ModuleInterface`, `Module\ModuleRegistry`, `rc_register_module()`,
> and the `wprc/core/register_modules` action — this is genuinely used in
> production by **RC-Catalog** (the separate `www`-side plugin). It was
> briefly removed as "dead code" on 2026-09-19 (`rc-core` 0.6.0-alpha11)
> based on a search that only covered `rc-core`, `rc-portal`, and
> `rc-portal-theme` — RC-Catalog wasn't checked, the removal broke
> production, and it was restored in 0.6.0-alpha12. Separately,
> `rc-portal`'s embedded modules use their own, different interface
> (`EmbeddedModuleInterface`, discovered by
> `ModuleCatalog::loadFromDirectory()` globbing `modules/*/module.php`) and
> always have — they still don't use Core's mechanism. If you're writing an
> embedded `my`-side module, use `EmbeddedModuleInterface` — read an
> existing module (e.g. `rc-portal/modules/products/module.php`) and
> `rc-portal/modules/AGENTS.md`. If you're touching RC-Catalog, Core's
> `ModuleInterface` below is real and load-bearing — don't remove it again
> without first confirming RC-Catalog's actual usage (see
> `docs/ARCHITECTURE-OPEN-QUESTIONS.md` #1, still open). Separately, the
> "Presentation surfaces" section further down is also not yet implemented
> — see its own note and `docs/PORTAL-UI.md`.

## Mandatory rules

1. A business module depends on RC Core for infrastructure. Presentation is
   also Core-owned at the contract level (decided 2026-09-19, no exception):
   a module supplies semantic page/table data into Core's declarative UI
   Registry (`docs/PORTAL-UI.md`); it does not depend on RC Portal itself
   for rendering. A module never depends on another business module.
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

**Two real contracts exist — pick the one for the repository you're in**
(see the status note above; `docs/ARCHITECTURE-OPEN-QUESTIONS.md` #1 tracks
formalizing this split, currently open).

**RC-Catalog (`www`-side) / anything outside `rc-portal`:** implement
Core's `ModuleInterface`:

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

**Embedded `my`-side module (inside `rc-portal/modules/`):** do **not** use
the above. Implement `RC\Portal\Module\EmbeddedModuleInterface`
(`descriptor()`, `register()`, `boot()`), discovered by
`ModuleCatalog::loadFromDirectory()` globbing `modules/*/module.php`. That
contract, its `ModuleDescriptor` shape, and the per-module rules are
documented in `rc-portal/modules/AGENTS.md` — read that file and an
existing module (e.g. `rc-portal/modules/products/module.php`) instead of
expecting a second example here. What such a module still gets from Core,
regardless of which lifecycle mechanism it uses, is everything below:
capability registration (`rc_register_capabilities()`), ERP access, the
request context, and the architecture preflight gate.

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
