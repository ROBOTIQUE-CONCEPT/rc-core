# Architecture open questions

This document exists only to record contradictions between normative
documentation and actual, running code that a documentation pass could not
resolve on its own — because resolving them requires a product/architecture
decision, not just a wording fix. Each entry stays until a human closes it
(by deciding, then updating the source documents and deleting the entry
here). Do not add entries for stale version numbers alone — fix those
directly in the affected file.

Two entries closed 2026-09-19 and still closed:

1. **Standalone-plugin vs. embedded-module architecture** — no separate
   `my`-side plugins; embedded modules inside `rc-portal`. See
   `docs/ARCHITECTURE.md`'s "Amendments" section, decision 1.
2. **Module-rendered HTML vs. theme-only rendering** — no exception, the
   `docs/PORTAL-UI.md` declarative contract is normative (though not yet
   implemented). See `docs/ARCHITECTURE.md`'s "Amendments" section,
   decision 2.

## 1. Two incompatible module-lifecycle contracts exist — which is canonical for an embedded `my`-side module? (reopened 2026-09-19)

**Context.** `rc-core` ships a module-lifecycle mechanism:
`Contracts\ModuleInterface` (`id()`, `version()`, `minimumCoreVersion()`,
`register(Container)`, `boot()`), `Module\ModuleRegistry` (enforces
`minimumCoreVersion()` via `version_compare()`), the global
`rc_register_module()` function, and a `do_action('wprc/core/register_modules', ...)`
hook. `rc-portal`'s embedded modules do not use it — they implement a
different, `rc-portal`-owned interface instead:
`RC\Portal\Module\EmbeddedModuleInterface`, discovered by
`ModuleCatalog::loadFromDirectory()`.

**This entry was closed once already, incorrectly.** On 2026-09-19 it was
decided to retire Core's mechanism as dead code, based on a repo-wide
search across `rc-core`, `rc-portal`, and `rc-portal-theme` that found zero
callers. It was removed in `rc-core` 0.6.0-alpha11. This broke production:
**RC-Catalog** (the separate `www`-side plugin) depends on this mechanism.
RC-Catalog's source is not part of this repository, `rc-portal`, or
`rc-portal-theme`, so it was never checked — the "zero callers" finding was
true only for the three repos actually searched, not for the whole
platform. The removal was reverted in `rc-core` 0.6.0-alpha12; Core's
mechanism is back and must not be removed again without confirming
RC-Catalog's actual usage first.

**Impact.** Any future decision here must account for RC-Catalog as a real,
confirmed consumer of `Contracts\ModuleInterface`/`rc_register_module()` —
not a hypothetical one. Concretely: read RC-Catalog's source (get access to
that repository) before proposing any change to this mechanism, and check
exactly which classes/hooks it uses and how tightly, before choosing an
option below.

**Options.**
- (a) Make `rc-portal`'s `EmbeddedModuleInterface` extend/implement Core's
  `ModuleInterface` (or have `ModuleCatalog` adapt each embedded module
  into one and call `rc_register_module()` on its behalf), so Core's
  registry and version-gate get exercised for embedded modules too, while
  RC-Catalog keeps using Core's mechanism directly as it does today.
- (b) ~~Retire Core's mechanism as dead/aspirational.~~ **Ruled out** — it
  is not dead; RC-Catalog uses it in production. Do not choose this again
  without first migrating RC-Catalog off it, which is a separate,
  deliberate, coordinated change of its own.
- (c) Keep both, explicitly scoped: Core's `ModuleInterface` for
  `RC-Catalog` (a standalone `www`-side plugin), `EmbeddedModuleInterface`
  for anything embedded in `rc-portal`. Document the split clearly in
  `docs/MODULE-DEVELOPMENT.md` so neither looks like dead code from either
  repo's perspective.

**Recommendation.** (c) is very likely correct now that RC-Catalog is a
confirmed real consumer — it matches what's actually running today with
the least risk, and just needs the documentation split written up clearly.
(a) is worth it only if there's a concrete reason to unify the two
lifecycles technically, not just document them. Not this audit's call to
finalize — get RC-Catalog's actual source/usage in front of a human before
closing this again.

**Status.** Pending decision. Do not remove Core's `ModuleInterface`,
`ModuleRegistry`, `rc_register_module()`, or the `wprc/core/register_modules`
action again until this is genuinely resolved with RC-Catalog's real usage
accounted for.
