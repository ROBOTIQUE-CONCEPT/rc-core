# AGENTS.md — rc-core

## Repository purpose

RC Core is the shared, transversal foundation for the Robotique Concept
WordPress platform: capabilities/roles, ERP access (Axonaut, via cached
provider contracts), multisite context, logging, a signed internal-API
foundation, and the declarative UI Registry that RC Portal renders from.
Core owns platform *conventions*; it contains no business/domain logic of
its own (no products, leads, maintenance, inventory, tools — those live in
`rc-portal`).

Current version: `0.6.0-alpha12` (`rc-core.php` header and the
`RC_CORE_VERSION` constant — both authoritative and, as of this writing,
in agreement). Requires WordPress `>= 6.8`, PHP `>= 8.1`.

**Recent incident (2026-09-19):** 0.6.0-alpha11 removed Core's
`ModuleInterface`/`ModuleRegistry` mechanism as "dead code," based on a
search that covered only `rc-core`, `rc-portal`, and `rc-portal-theme`.
**RC-Catalog** — a fourth, separate `www`-side plugin, outside all three of
those repos — depends on it. The removal broke production (RC Core is
network-active). Reverted in 0.6.0-alpha12; the mechanism is real and
load-bearing again. Lesson for future audits: "zero callers" is only ever
true for the repos you actually searched — RC-Catalog exists and must be
checked before any future claim that something in Core is unused.

## Architecture boundaries

- **Dependency direction is one-way and downward only.** RC Core never
  imports, references, or knows about any class in `rc-portal` (namespace
  root `RC\Portal\*`, including embedded modules under
  `RC\Portal\Modules\*`) or `RC\Catalog\*` (the separate `www`-side
  projection plugin). RC Core must be able to run correctly with no
  business plugin active at all.
- **(Decided 2026-09-19)** `my`-side business logic is not a set of
  separate plugins — it's a single `RC Portal` plugin with modules
  embedded under `modules/{name}/`. There is no `RC-Leads`, `RC-Products`,
  `RC-Assets`, etc. as independent deployables; those names now refer to
  modules inside `rc-portal`. `RC-Catalog` (`www`-side) is unaffected by
  this decision and remains separate/undecided.
- **(Decided 2026-09-19, no exception)** Business code depends on Core
  only — never on Portal for rendering. A module supplies semantic
  page/table data (`docs/PORTAL-UI.md`'s `PageDefinition`/`TableDefinition`
  contract); it never builds HTML. This is **not yet implemented** by
  `rc-portal`'s real modules today — see `docs/PORTAL-UI.md`'s
  implementation-status note. Don't treat the current HTML-building
  pattern in `products`/`tools` as an accepted design to extend.
- **Two module-lifecycle contracts genuinely coexist, scoped by
  repository — this is unresolved, not a mistake to "fix."**
  `Contracts\ModuleInterface`/`Module\ModuleRegistry`/`rc_register_module()`/
  `wprc/core/register_modules` are real Core code, used in production by
  **RC-Catalog** (the separate `www`-side plugin, not in this repo,
  `rc-portal`, or `rc-portal-theme`). `rc-portal`'s embedded modules use a
  different contract, `RC\Portal\Module\EmbeddedModuleInterface`, and
  always have. **Do not remove Core's mechanism** — it was tried once
  (0.6.0-alpha11) on the mistaken belief it was dead code, and it broke
  production. See `docs/ARCHITECTURE-OPEN-QUESTIONS.md` #1 (open) for the
  still-pending decision on how to formalize this split.
- **Namespace**: everything under `src/` is `WPRC\Core\*` — this is the
  real, current convention (confirmed by the autoloader, which only loads
  classes under that literal prefix). `docs/ARCHITECTURE.md` §37 documents
  a bare `RC\Core\*` target instead; that section is stale — do not follow
  it. Also note `rc-portal` itself uses a *different* prefix, `RC\Portal\*`
  (no `W`) — the two repos are not using a consistent root prefix today.
  This inconsistency is real but is not yours to fix unilaterally inside a
  single-repo change; don't rename Core's namespace to "fix" it.
- Core exposes three distinct lookup surfaces — know which one you're
  extending:
  - `Container` (`src/Container.php`) — Core's *internal* DI container,
    class-id keyed (`singleton()`/`factory()`/`instance()`/`alias()`),
    guarded against silent overwrite of an already-registered id.
  - `ServiceRegistry` (`src/ServiceRegistry.php`) — the *cross-plugin*
    contract registry, reached from anywhere via `rc_core()->services()`.
    Use this (`bind()`/`instance()`) for a contract another RC plugin is
    meant to consume.
  - `ProviderRegistry` (`src/ERP/ProviderRegistry.php`) — ERP-provider
    lookup specifically, source-keyed (`'axonaut'` today), reached via
    `rc_core()->erp()`.
  - `rc_core()` (`src/functions.php`) is the actual universal entry point:
    ~30 typed accessor methods on `Plugin` (`->erp()`, `->capabilities()`,
    `->ui()`, `->sites()`, `->services()`, …). Prefer adding a typed
    accessor here over asking callers to know a raw container/service id.

## Repository map

- `rc-core.php` — plugin header/bootstrap (`Autoloader::register()` →
  `functions.php` → activation/deactivation hooks → `Plugin::instance()->boot()`
  on `plugins_loaded` priority 1). `Network: true`.
- `src/Plugin.php` — the composition root; `registerServices()` wires
  essentially every Core service into `Container`.
- `src/Contracts/` — the public interfaces other repos implement/consume:
  `ModuleInterface` (real, used in production by RC-Catalog — see
  Architecture boundaries above; do not remove), `ServiceProviderInterface`,
  `BootableInterface`, `CacheInterface`, `LoggerInterface`,
  `PlaceholderProviderInterface`, and per-entity ERP contracts under
  `Contracts/ERP/*` (Product/Company/Employee/Address/Opportunity/Quotation).
  `Contracts/Assets/*` and `Contracts/Products/ProductCatalogProviderInterface`
  are contracts pre-declared for a module that doesn't exist yet — don't
  delete them as "unused" either. **Before deleting anything in this
  directory as unused, check RC-Catalog too, not just `rc-portal`/
  `rc-portal-theme`** — this repo can't grep RC-Catalog's source, so
  "no callers found" here is not proof of zero callers platform-wide.
- `src/InternalApi/` — HMAC-signed server-to-server request/response
  primitives (`RequestSigner`, `ReplayGuard`, `Authenticator`, `Client`,
  `InternalApiSecret`). Foundations only — no live route uses this yet. Any
  future `my`↔`www` server-to-server call must be built on this, not a new
  ad hoc scheme.
- `src/ERP/`, `src/Connectors/Axonaut/` — the only ERP integration
  (Axonaut). `src/ERP/Cache/*` implements the mandatory
  request-cache → persistent-cache → anti-stampede-lock → remote pipeline
  (see Mandatory rules).
- `src/Security/Capabilities/*`, `src/Auth/*` — `CapabilityRegistry`,
  `RoleRegistry`, `RolePolicy` (the canonical role list), `RoleManager`,
  `AccessPolicy` (6 canonical `rc_*_access` capabilities), `PersonaResolver`.
- `src/Site/SiteContext.php` — the only sanctioned way to reference
  public/application/connector site ids/URLs, and the only place
  `switch_to_blog()` is called (via `onSite()`).
- `src/Database/Installer.php` — schema install/upgrade (see Versioning).
- `src/Logging/` — `DatabaseLogger`, `ContextSanitizer`, `RetentionScheduler`.
- `src/UI/` — the UI Registry business modules declare Admin/Internal/
  External pages into (see `docs/PORTAL-UI.md`).
- `tools/architecture-preflight.php` — static namespace/pattern scanner
  (see Validation). Rewritten 2026-09-19 to check the real namespaces
  (`RC\Portal\*`, `RC\Catalog\*`) instead of a retired standalone-plugin
  namespace list; also now skips any file named `*preflight.php` to avoid
  false-positiving on a preflight tool's own needle strings (a real
  failure mode, confirmed empirically — see Validation).
- `docs/ARCHITECTURE.md` — the declared "frozen" architecture baseline.
  Read its **"Amendments" section first** (right after the header) — it
  records the two 2026-09-19 decisions (no separate `my`-side plugins; no
  exception to Portal-owns-rendering) and which passages further down they
  supersede, rather than requiring a full rewrite of a 1500-line "frozen"
  document.
- `docs/MODULE-DEVELOPMENT.md` — shorter, more current companion to
  ARCHITECTURE.md; prefer it for the module-author's-eye view. Shows
  **both** real module-lifecycle contracts side by side (Core's
  `ModuleInterface`, for RC-Catalog/anything outside `rc-portal`, and
  `rc-portal`'s `EmbeddedModuleInterface`, for embedded `my`-side modules)
  and which one to use where — read its status note before assuming either
  one is "the" contract.
- `docs/MULTISITE-CUTOVER.md` — a migration runbook written for the
  retired standalone-plugin architecture; now marked historical at the top
  of the file. Don't follow it literally.
- `docs/PORTAL-UI.md` — the UI Registry / Portal presentation contract;
  confirmed normative with no exception, but not yet implemented — read
  its implementation-status note before assuming any module follows it
  today.
- `VALIDATION.md` — a per-release validation snapshot, last written for
  `0.6.0-alpha8`; stale as a "current state" pointer but not wrong about
  what it covers.

## Mandatory rules

- Never add a `use` of, or otherwise reference, any class under
  `RC\Portal\*` (covers RC Portal and every embedded module, e.g.
  `RC\Portal\Modules\Products\*`) or `RC\Catalog\*` from inside `src/`.
  Enforced by `tools/architecture-preflight.php`.
- All ERP access happens through `src/ERP`/`src/Connectors` and is exposed
  to the rest of the platform only via the `Contracts/ERP/*` interfaces and
  `rc_core()->erp()`. Never add a code path elsewhere (Core or otherwise)
  that talks to Axonaut without going through this pipeline.
- Any new ERP read path must go through the existing cache pipeline, in
  order: `RequestCache` (per-request memoization) → persistent
  `CacheInterface` (global `wprc_axonaut` object-cache group) →
  `CacheLock` (anti-stampede: acquire, or poll the persistent cache instead
  of issuing a duplicate remote call) → the real Axonaut HTTP call as a
  last resort. Don't bypass any of these stages for a new provider method.
  Cache keys go through `ErpCacheKeyFactory` so equivalent-but-differently-
  ordered params hash identically.
- Axonaut's HTTP 403 is a pagination-negotiation response on some
  collection endpoints, not a real error — `ErpTelemetry` deliberately
  excludes it from error counts. Don't "fix" this by treating 403 as a hard
  failure without re-reading `AxonautClient`'s pagination handling first.
- Capabilities are the only thing business logic (anywhere in the
  platform) is allowed to branch on — never a role name. Every capability
  registered via `CapabilityRegistry::register()` must start with `rc_`
  (enforced at runtime, not just convention). Role definitions
  (`RoleRegistry`/`RolePolicy`) and their application to real WordPress
  roles (`RoleManager`) live only in Core.
- `switch_to_blog()` is called only inside `SiteContext::onSite()`. Never
  call it directly anywhere else in Core (the preflight tool's Check E
  enforces this for non-Core roots; hold Core itself to the same bar).
- Never hard-code a blog ID or domain. Use `SiteContext` accessors
  (`publicSiteId()`, `applicationSiteId()`, `connectorSiteId()`, and their
  URL equivalents).
- Secrets/tokens must never be logged. `ContextSanitizer` redacts common
  key patterns automatically, but don't rely on it as a substitute for not
  passing raw secrets into a log context in the first place.
- Don't invent a second cache, logger, translation, or placeholder
  framework anywhere in the platform — Core's is the only one.

## Security rules

- The InternalApi HMAC scheme (`RequestSigner`/`Authenticator`) is the only
  sanctioned mechanism for signed server-to-server requests between sites.
  Its replay window is clamped 30–900s and its nonce-replay cache
  (`wprc_internal_api` group) needs a real persistent/shared object cache
  to be effective across multiple PHP workers — don't assume it's airtight
  on a box without one.
- `SecurityLogRateLimiter` silently becomes a no-op (always allows) when no
  external object cache is active (`wp_using_ext_object_cache()` false).
  Don't assume security-event logging is rate-limited in every environment.
- Turnstile verification (`TurnstileVerifier`) fails **open** when
  credentials aren't configured — this is intentional/historical behavior,
  not a bug; don't silently flip it to fail-closed without a deliberate,
  reviewed decision (it would change login availability behavior).
- Capability/role mutation (`add_role`/`remove_role`, and — via
  `RoleManager` — capability grants) happens only in Core, applied only to
  the application site by default (`applyApplicationSite()`), never
  network-wide implicitly.

## Change workflow

- Read `docs/MODULE-DEVELOPMENT.md` first for anything touching the
  module-facing contract surface (capability registration, ERP access, the
  `rc_core()` accessor surface, and — since there are genuinely two,
  scoped by repository — which module-lifecycle contract applies). Read
  `docs/ARCHITECTURE.md` for anything touching ERP caching, multisite, or
  the InternalApi — but cross-check its dependency-graph and namespace
  sections against this file first (see above).
- A change to any interface under `src/Contracts/` is a cross-repo
  breaking-change risk — `rc-portal`'s `products` module imports several of
  these directly. Check `rc-portal/modules/products` for usages before
  changing a contract's method signature.
- A change to `src/Database/Installer.php` must stay idempotent — there is
  no incremental migration ladder; `maybeUpgrade()` re-runs the whole
  `install()` on any `DB_VERSION` mismatch, so every step inside it must be
  safe to run again on an already-migrated site.

## Validation

- `php -l` every changed file (no build step, no Composer autoload to
  regenerate).
- `php tools/architecture-preflight.php .` (Core's own root) — checks: no
  reference to a business-module or Portal namespace inside Core; no
  `wp_remote_*`, `add_role`/`remove_role`, or `switch_to_blog()` outside
  Core. This is a plain string/regex scanner over raw file text, not an
  AST parser — it can be fooled by a comment containing a flagged string,
  and it can miss a dynamically-built namespace string.
- The script also accepts one or more repo roots on the command line (e.g.
  `php tools/architecture-preflight.php . /path/to/rc-portal
  /path/to/rc-portal-theme`) and checks each against the real namespace map
  (`RC\Portal\*`, `RC\Catalog\*`). It skips any file whose name ends in
  `preflight.php` (its own needle strings, and the other repos' preflight
  scripts' needle strings, would otherwise self-match Checks A/C/E — a real,
  previously-confirmed false positive, now fixed). Still complement it with
  `rc-portal/tools/preflight.php`'s own cross-module-import check, which
  targets a narrower, `rc-portal`-specific rule this scanner doesn't cover.
- No PHPUnit/test suite exists in this repo today. Don't reference one that
  isn't there; if you add meaningful new logic, consider whether a small,
  runnable test is worth adding, but there is no existing harness to plug
  into.

## Versioning and documentation

- Bump `rc-core.php`'s `Version:` header **and** the `RC_CORE_VERSION`
  constant together — they must always agree (they do today).
- Add a `CHANGELOG.md` entry for every release, including an explicit
  "Compatibility" note when the change affects what a consuming module
  needs (this is the platform's actual backward-compatibility record — read
  the last several entries before assuming a given contract is stable).
- `ModuleRegistry::register()` enforces `minimumCoreVersion()` at runtime
  via `version_compare()` for any module registered through Core's
  mechanism (RC-Catalog today) — a module declaring a floor above the
  installed Core version will hard-fail. Separately, `rc-portal` has its
  own coarser, plugin-level check (`RC_PORTAL_MIN_CORE_VERSION` vs.
  `RC_CORE_VERSION`) unrelated to `ModuleRegistry`. Keep both gates in mind
  when bumping Core's version — they're both executing gates, not just
  documentation, on different sides of the platform.
- Do not treat `README.md`'s version header as authoritative — it has
  drifted from `rc-core.php` before (fixed once during this documentation
  pass; watch for it recurring). `CHANGELOG.md`'s top entry is the more
  reliable current-version signal if `rc-core.php` itself is ambiguous.

## Definition of done

- `php -l` clean on every changed file.
- `php tools/architecture-preflight.php .` passes.
- No new reference to any `RC\Portal\*` or business-module namespace
  anywhere under `src/`.
- Any new/changed `Contracts/*` interface has been checked against known
  consumers in `rc-portal` (see Change workflow).
- `CHANGELOG.md` updated; `rc-core.php`/`RC_CORE_VERSION` bumped together if
  the change is release-worthy.
- No functional/behavioral change bundled into a documentation-only change,
  and vice versa — keep the two kinds of change in separate commits/PRs.

## Read before changing

| Touching… | Read first |
|---|---|
| Module contract, `rc_core()` accessors, capability registration | `docs/MODULE-DEVELOPMENT.md` |
| ERP providers, caching/locking/telemetry | `docs/ARCHITECTURE.md` §12–13 (ERP sections) + `src/ERP/Cache/*` source |
| Multisite / `SiteContext` | `docs/ARCHITECTURE.md` (multisite sections) + `docs/MULTISITE-CUTOVER.md` (marked historical — re-derive the real order from current versions, don't follow it literally) |
| UI Registry / Admin-Internal-External pages | `docs/PORTAL-UI.md`, then `rc-portal/modules/AGENTS.md` for the consuming side |
| InternalApi / signed requests | `src/InternalApi/*` source directly — no dedicated doc beyond `docs/ARCHITECTURE.md` §17 |
| Module lifecycle / registration | **Two real contracts, scoped by repo** — Core's `ModuleInterface` (RC-Catalog/`www`-side; do not remove) vs. `rc-portal`'s `EmbeddedModuleInterface` (embedded `my`-side). Read `docs/MODULE-DEVELOPMENT.md` and `rc-portal/modules/AGENTS.md`; formalizing this split is `docs/ARCHITECTURE-OPEN-QUESTIONS.md` #1, still open |
| Namespace conventions, dependency direction, presentation ownership | These are now **decided** (2026-09-19, no exception) — read `docs/ARCHITECTURE.md`'s "Amendments" section and `docs/PORTAL-UI.md`'s implementation-status note, not the open-questions doc |
