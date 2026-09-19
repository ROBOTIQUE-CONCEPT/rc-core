# RC Module Development Contract

This file is a concise implementation companion to `ARCHITECTURE.md`.

> **Resolved 2026-09-19** (was: implementation-status note about two
> competing lifecycle contracts). RC Core used to ship its own module
> lifecycle mechanism (`Contracts\ModuleInterface`, `Module\ModuleRegistry`,
> `rc_register_module()`, the `wprc/core/register_modules` action), but
> `rc-portal`'s embedded modules never used it — they used a different,
> `rc-portal`-owned interface (`EmbeddedModuleInterface`, discovered by
> `ModuleCatalog::loadFromDirectory()` globbing `modules/*/module.php`).
> Decision: Core's mechanism was dead/aspirational code with zero callers
> (confirmed by grep across all three repos) and has been **removed** from
> `rc-core` (`Contracts/ModuleInterface.php`, `Module/ModuleRegistry.php`,
> `rc_register_module()`, the `wprc/core/register_modules` action, and
> `Plugin::modules()`/its container registration — see `CHANGELOG.md`).
> `EmbeddedModuleInterface` is the one real, canonical contract for an
> embedded `my`-side module today. This doc no longer shows a Core-owned
> lifecycle example below — read an existing module instead (e.g.
> `rc-portal/modules/products/module.php`) and `rc-portal/modules/AGENTS.md`
> for the module-author's-eye rules. Separately, the "Presentation surfaces"
> section further down is also not yet implemented — see its own note and
> `docs/PORTAL-UI.md`.

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

RC Core does not define a module lifecycle contract itself (it did once —
see the resolved note above). An embedded module implements RC Portal's
`RC\Portal\Module\EmbeddedModuleInterface` (`descriptor()`, `register()`,
`boot()`), discovered by `ModuleCatalog::loadFromDirectory()` globbing
`modules/*/module.php`. That contract, its `ModuleDescriptor` shape, and
the per-module rules are documented in `rc-portal/modules/AGENTS.md` — read
that file and an existing module (e.g.
`rc-portal/modules/products/module.php`) rather than expecting an example
here. What a module still gets from Core, regardless of that lifecycle
mechanism, is everything below: capability registration
(`rc_register_capabilities()`), ERP access, the request context, and the
architecture preflight gate.

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
