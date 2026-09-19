# Architecture open questions

This document exists only to record contradictions between normative
documentation and actual, running code that a documentation pass could not
resolve on its own — because resolving them requires a product/architecture
decision, not just a wording fix. Each entry stays until a human closes it
(by deciding, then updating the source documents and deleting the entry
here). Do not add entries for stale version numbers alone — fix those
directly in the affected file.

Two entries that stood here (standalone-plugin vs. embedded-module
architecture; module-rendered HTML vs. theme-only rendering) were closed on
2026-09-19 — both decided, both docs updated. See
`docs/ARCHITECTURE.md`'s "Amendments" section and `docs/PORTAL-UI.md`'s
implementation-status note for the decisions and what they supersede.

## 1. Two incompatible module-lifecycle contracts exist — which is canonical for an embedded `my`-side module?

**Context.** `rc-core` ships a full module-lifecycle mechanism:
`Contracts\ModuleInterface` (`id()`, `version()`, `minimumCoreVersion()`,
`register(Container)`, `boot()`), `Module\ModuleRegistry` (enforces
`minimumCoreVersion()` via `version_compare()`), the global
`rc_register_module()` function, and a `do_action('wprc/core/register_modules', ...)`
hook — all real, working code, and it's exactly what
`docs/MODULE-DEVELOPMENT.md`'s "Module lifecycle" example shows.

`rc-portal` does not use any of it. Its embedded modules implement a
different, `rc-portal`-owned interface instead:
`RC\Portal\Module\EmbeddedModuleInterface` (`descriptor(): ModuleDescriptor`,
`register(): void` — no `Container` argument, `boot(): void`), discovered by
`ModuleCatalog::loadFromDirectory()` globbing `modules/*/module.php` — a
parallel mechanism that never calls `rc_register_module()` or fires/listens
to `wprc/core/register_modules`.

Both are real, both are exercised in production (`rc-core`'s own
`ModuleRegistry` has no callers today since nothing calls
`rc_register_module()`; `rc-portal`'s `EmbeddedModuleInterface` has five
real implementations). Neither doc describes the other's existence.

**Impact.** An agent reading `docs/MODULE-DEVELOPMENT.md` and implementing
a new module exactly as shown would produce a module `rc-portal` cannot
discover at all (it only globs `modules/*/module.php` for
`EmbeddedModuleInterface`, never listens for `rc_register_module()` calls).
Conversely, `rc-core`'s `ModuleRegistry`/`minimumCoreVersion()` enforcement
is currently dead code from `rc-portal`'s perspective — `rc-portal`'s own
`ModuleCatalog` re-implements a version check itself instead
(`RC_PORTAL_MIN_CORE_VERSION` checked against `RC_CORE_VERSION` at
activation/boot, a plugin-level check, not a per-module one).

**Options.**
- (a) Make `rc-portal`'s `EmbeddedModuleInterface` extend/implement Core's
  `ModuleInterface` (or have `ModuleCatalog` adapt each embedded module
  into one and call `rc_register_module()` on its behalf), so Core's
  registry and version-gate actually get exercised for embedded modules
  too. Update `docs/MODULE-DEVELOPMENT.md`'s example to show the real
  `EmbeddedModuleInterface` shape instead of the abstract one.
- (b) Retire Core's `ModuleInterface`/`ModuleRegistry`/`rc_register_module()`
  as dead/aspirational (they'd have made sense for the standalone-plugin
  architecture that no longer exists — see the now-closed entry above) and
  rewrite `docs/MODULE-DEVELOPMENT.md`'s lifecycle section to document
  `EmbeddedModuleInterface` as the one real contract.
- (c) Keep both, explicitly scoped: Core's `ModuleInterface` for some other,
  not-yet-built kind of module (e.g. a future standalone `www`-side plugin
  under `RC-Catalog`'s umbrella), `EmbeddedModuleInterface` for anything
  embedded in `rc-portal`. Document the split clearly so neither looks like
  dead code.

**Recommendation.** (b) is the simplest and matches the "no separate
`my`-side plugins" decision already made — but only if nobody has a
concrete near-term plan for a module that isn't embedded in `rc-portal`. If
such a plan exists, (a) is worth the (small) integration cost so there's
one real lifecycle contract instead of two. Not this audit's call.

**Status.** Pending decision.
