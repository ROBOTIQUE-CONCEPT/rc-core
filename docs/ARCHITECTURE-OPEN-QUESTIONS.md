# Architecture open questions

This document exists only to record contradictions between normative
documentation and actual, running code that a documentation pass could not
resolve on its own — because resolving them requires a product/architecture
decision, not just a wording fix. Each entry stays until a human closes it
(by deciding, then updating the source documents and deleting the entry
here). Do not add entries for stale version numbers alone — fix those
directly in the affected file.

There are currently **no open entries**. All three questions raised during
the 2026-09-19 documentation pass have been decided:

1. **Standalone-plugin vs. embedded-module architecture** — closed
   2026-09-19: no separate `my`-side plugins; embedded modules inside
   `rc-portal`. See `docs/ARCHITECTURE.md`'s "Amendments" section, decision 1.
2. **Module-rendered HTML vs. theme-only rendering** — closed 2026-09-19:
   no exception, the `docs/PORTAL-UI.md` declarative contract is normative
   (though not yet implemented — see that doc's implementation-status
   note). See `docs/ARCHITECTURE.md`'s "Amendments" section, decision 2.
3. **Two incompatible module-lifecycle contracts** (`rc-core`'s
   `ModuleInterface`/`ModuleRegistry` vs. `rc-portal`'s
   `EmbeddedModuleInterface`) — closed 2026-09-19: Core's mechanism was
   confirmed to have zero callers across all three repos and has been
   removed from `rc-core` (`Contracts/ModuleInterface.php`,
   `Module/ModuleRegistry.php`, `rc_register_module()`, the
   `wprc/core/register_modules` action). `EmbeddedModuleInterface` is the
   one real, canonical contract for an embedded `my`-side module. See
   `docs/ARCHITECTURE.md`'s "Amendments" section, decision 3, and
   `docs/MODULE-DEVELOPMENT.md`.

A new entry should only be added here when a genuinely new contradiction is
found that needs a human decision — not for routine documentation drift.
