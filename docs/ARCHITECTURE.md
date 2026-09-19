# RC Platform — Architecture & Development Guidelines

**Status:** Frozen architecture baseline  
**Scope:** WordPress Multisite platform for Robotique Concept  
**Target:** WordPress network with `www` (public projection) and `my` (business application)  
**Principle:** One source of truth per data domain, strict module isolation, RC-Core as platform SDK

---

## Amendments (read first)

This baseline was written assuming `my`-side business logic would ship as
several independent plugins (RC-Catalog, RC-Leads, RC-Products, RC-Assets,
RC-Interventions, RC-Inventory — see §3, §5, §20, §37, §39). Two decisions
since then supersede that assumption and the specific passages named below;
everything else in this document (ERP ownership, `www`/`my` site
responsibilities, caching, security, roles) is unaffected.

1. **(Decided 2026-09-19) No separate `my`-side business plugins.** All
   `my`-side business logic is embedded inside a single `RC Portal` plugin
   (`rc-portal`), one module per bounded context under `modules/{name}/`,
   sharing the namespace root `RC\Portal\Modules\{Module}\`. Wherever §3's
   topology diagram, §5's dependency diagram, §20's "Business plugins"
   list, or §37's namespace list name RC-Leads/RC-Products/RC-Assets/
   RC-Interventions/RC-Inventory as separate plugins, read them as
   embedded modules of `rc-portal` instead — the isolation rules
   themselves (no cross-module import, no shared table access, cross-
   domain reads via Core contracts) are unchanged, just enforced within
   one repository instead of across several (`rc-portal/tools/preflight.php`
   is the actual enforcement point today, not a separate per-module
   preflight run). This does **not** change anything about `RC-Catalog` or
   `www` — that remains a separate, distinct plugin/site and an open
   question in its own right (not decided here).
2. **(Decided 2026-09-19) No exception to Portal-owns-rendering.** The
   declarative presentation contract in `docs/PORTAL-UI.md`
   (`PageDefinition`/`TableDefinition`, modules supply semantic data only,
   Portal is the only HTML/CSS/JS author) is confirmed as normative with
   **no exception** — this directly settles §50 rule 6's intent. It is,
   however, **not yet implemented**: `rc-portal`'s actual embedded modules
   (`products`, `tools`) currently build HTML directly in their own
   `Ui/*Pages.php` classes rather than through `PageDefinition`/
   `TableDefinition`. This is tracked as migration debt, not an accepted
   permanent design — see `docs/PORTAL-UI.md`'s own implementation-status
   note and `rc-core/docs/ARCHITECTURE-OPEN-QUESTIONS.md`.
3. **(Decided 2026-09-19) Core's own module-lifecycle mechanism is
   retired.** §9 "Module contract" below describes a `ModuleInterface`
   conceptual example; the real, shipped version of that mechanism
   (`Contracts\ModuleInterface`, `Module\ModuleRegistry`,
   `rc_register_module()`, the `wprc/core/register_modules` action) existed
   in Core but was never used by `rc-portal`'s real embedded modules —
   confirmed to have zero callers across all three repos. It has been
   **removed** from `rc-core` (see `CHANGELOG.md`).
   `RC\Portal\Module\EmbeddedModuleInterface` is the one real, canonical
   module-lifecycle contract for an embedded `my`-side module — see
   `rc-portal/modules/AGENTS.md` and `rc-core/docs/MODULE-DEVELOPMENT.md`.
   Read §9 as historical context for a mechanism that no longer exists in
   code, not as current API.

See `rc-core/docs/ARCHITECTURE-OPEN-QUESTIONS.md` for what remains
genuinely undecided — as of this decision, no entries are currently open.

---

## 1. Purpose

This document defines the mandatory architecture and development rules for the Robotique Concept WordPress platform.

It is the reference guideline for:

- RC-Core;
- RC-Catalog;
- RC-Leads;
- future business modules;
- the public theme;
- the future WordPress Multisite migration;
- ERP integrations;
- internal REST communication;
- user roles and capabilities;
- caching, logging, security, testing and deployment.

Any new development MUST comply with this document unless an explicit architecture decision supersedes it.

---

# 2. Architectural principles

## 2.1. One source of truth per domain

A business datum MUST have one authoritative owner.

### ERP-owned data

The ERP is the source of truth for:

- products;
- customers;
- suppliers;
- contacts;
- addresses;
- quotations;
- customer orders;
- supplier orders;
- invoices.

Current ERP:

- Axonaut.

Future ERP:

- Odoo, if migration is validated.

WordPress MUST NOT become an alternative authoritative database for these domains.

ERP data MAY be cached, mapped, projected or enriched locally, but the original ERP-owned fields remain authoritative.

### WP.my-owned data

`my` is the source of truth for Robotique Concept-specific business data that is not natively owned by the ERP:

- technical robot models;
- controller models;
- technical specifications;
- robot/assets;
- interventions;
- serialized inventory;
- internal metadata and relations required by those domains.

### WP.www-owned data

`www` owns only its public publishing layer:

- public WordPress pages;
- public articles;
- acquisition / SEO content;
- public WooCommerce product projections;
- public translations;
- public taxonomies where required;
- public media;
- SEO metadata.

`www` MUST NOT be considered an authoritative business source.

Public business projections MUST be reconstructible from their upstream source.

---

# 3. Target network topology

```text
WordPress Multisite Network
│
├── RC-Core                           [Network Active]
│
├── www.robotiqueconcept.com
│   ├── WooCommerce
│   ├── Polylang
│   ├── SEO plugin
│   ├── RC-Catalog                    [www only]
│   ├── Public theme
│   ├── Public pages / articles
│   └── Materialized public projections
│
└── my.robotiqueconcept.com
    ├── WooCommerce
    ├── RC-Leads                      [my only]
    ├── future RC-Products            [my only]
    ├── future RC-Assets              [my only]
    ├── future RC-Interventions       [my only]
    ├── future RC-Inventory           [my only]
    ├── Customer / partner frontend
    └── Internal backoffice
```

---

# 4. Site responsibilities

## 4.1. `www`

`www` is a public showcase and acquisition website.

It MAY:

- display projected WooCommerce products;
- publish technical robot model pages;
- publish service pages;
- publish application pages;
- publish articles;
- run SEO and multilingual public content;
- capture public forms;
- send leads to `my`.

It MUST NOT:

- own ERP business data;
- directly implement internal business workflows;
- depend on business plugin PHP classes installed only on `my`;
- query the ERP during normal public page rendering;
- use `switch_to_blog()` as a business integration mechanism.

## 4.2. `my`

`my` is the WordPress business application.

It contains:

### Internal context

Initially:

- WordPress Admin.

Possible future evolution:

- front-only internal application.

### Customer / partner context

Initially:

- WooCommerce My Account.

Possible future evolution:

- unified front application based on role/capability.

`my` aggregates:

- live/cached ERP data;
- Robotique Concept local business data;
- permissions;
- customer-specific and partner-specific views.

Business plugins on `my` SHOULD behave as if `my` were their only WordPress site.

They MUST NOT require knowledge of `www` to perform their core business responsibilities.

---

# 5. Plugin dependency model

The mandatory dependency graph is:

```text
RC-Catalog ──────┐
RC-Leads ────────┤
RC-Products ─────┤
RC-Assets ───────┼──► RC-Core
RC-Interventions ┤
RC-Inventory ────┘
```

Allowed dependency:

```text
Business Module → RC-Core
```

Forbidden dependencies:

```text
Business Module → Business Module
RC-Core → Business Module
```

Examples of FORBIDDEN code:

```php
use RC\Assets\AssetRepository;
use RC\Products\ProductService;
use RC\Leads\OpportunityManager;
```

inside another business module.

A business module MUST NOT:

- instantiate another module's classes;
- access another module's internal database tables;
- call another module's private/internal functions;
- assume another business plugin is active;
- bootstrap another business plugin.

---

# 6. RC-Core role

RC-Core is the platform SDK and architecture leader.

RC-Core MUST:

- define development conventions;
- expose shared primitives;
- own cross-cutting infrastructure;
- define public contracts;
- standardize business module registration;
- standardize security and permissions;
- standardize ERP access;
- standardize caching;
- standardize internal REST communication;
- standardize logging;
- standardize translations;
- standardize placeholders;
- standardize validation and error handling;
- standardize schema/migration primitives;
- standardize events;
- provide observability;
- remain independent from every business module.

RC-Core MUST be able to run while no business module is active.

---

# 7. What belongs in Core

A feature belongs in RC-Core when at least one of these conditions applies:

1. it is infrastructure;
2. it is technical rather than domain-specific;
3. it can reasonably be reused by multiple domains;
4. it defines a convention all modules must follow;
5. duplicating it in multiple plugins would create divergent behavior.

Examples:

- ERP contracts;
- ERP HTTP client;
- request cache;
- Redis/object-cache abstraction;
- cache key generation;
- cache invalidation;
- anti-stampede locks;
- translations;
- placeholders;
- logger;
- UID generation;
- REST authentication;
- internal API client;
- request signing;
- nonce/replay protection;
- event bus;
- validation primitives;
- capability framework;
- module registry;
- database schema utilities;
- pagination primitives;
- shared DTO/value primitives when genuinely cross-domain;
- HTTP wrappers;
- common admin/UI primitives when shared;
- generic outbox/retry infrastructure.

---

# 8. What MUST NOT go in Core

Core MUST NOT become a monolithic business application.

The following remain domain-owned:

- robot model business rules;
- asset business rules;
- intervention lifecycle;
- serialized inventory rules;
- product publication rules;
- lead/opportunity rules;
- WooCommerce product projection mappings;
- technical compatibility business logic.

Example:

Core MAY provide a projection engine.

RC-Catalog / RC-Products MUST define how an ERP product maps to WooCommerce.

Core MAY provide a schema migration service.

RC-Assets MUST define its own asset tables and schema.

---

# 9. Module contract

> **Historical (see Amendments, decision 3, 2026-09-19):** the concrete
> version of this API (`Contracts\ModuleInterface`, `Module\ModuleRegistry`,
> `rc_register_module()`) has been removed from `rc-core` — it had zero
> real callers. The canonical module-lifecycle contract today is
> `rc-portal`'s `EmbeddedModuleInterface`; see `rc-portal/modules/AGENTS.md`.

Every business plugin MUST register itself through the Core-defined module API.

Conceptual example:

```php
interface ModuleInterface
{
    public function id(): string;

    public function version(): string;

    public function register(): void;

    public function boot(): void;
}
```

Core MAY evolve this API, but all modules MUST follow the same lifecycle.

Modules MUST NOT invent independent bootstrapping conventions.

---

# 10. Standard module structure

All business modules SHOULD use the same directory convention.

```text
plugin-name/
├── plugin-name.php
├── src/
│   ├── Module.php
│   ├── Domain/
│   │   ├── Entity/
│   │   ├── ValueObject/
│   │   ├── Contract/
│   │   └── Exception/
│   ├── Application/
│   │   ├── Service/
│   │   ├── Command/
│   │   ├── Query/
│   │   └── DTO/
│   ├── Infrastructure/
│   │   ├── Database/
│   │   ├── WordPress/
│   │   └── External/
│   ├── Admin/
│   ├── Frontend/
│   └── Rest/
├── assets/
├── languages/
└── uninstall.php
```

Actual folders MAY be reduced when unnecessary.

Folder naming and layering MUST remain consistent across modules.

---

# 11. Shared feature rule

Before implementing a helper, service, abstraction or utility inside a business plugin, development MUST verify whether:

1. Core already provides it;
2. it should be promoted to Core;
3. it is genuinely domain-specific.

Duplicate generic functionality is forbidden.

Examples of unacceptable duplication:

- multiple translation engines;
- multiple placeholder resolvers;
- multiple logging wrappers;
- multiple cache services;
- multiple REST response formats;
- multiple permission systems;
- multiple ERP HTTP clients;
- multiple date/format helpers solving the same platform problem.

---

# 12. ERP abstraction

No business module may directly perform ERP HTTP requests.

Forbidden:

```php
wp_remote_get($axonautUrl);
$client->get('/products/123');
```

outside RC-Core ERP infrastructure.

Modules MUST consume contracts such as:

```text
ProductProviderInterface
CompanyProviderInterface
ContactProviderInterface
AddressProviderInterface
QuotationProviderInterface
OrderProviderInterface
InvoiceProviderInterface
```

Current implementation:

```text
Contract
   ↓
Axonaut Provider
```

Future implementation:

```text
Contract
   ↓
Odoo Provider
```

Business modules MUST NOT need changes solely because the ERP provider changes.

---

# 13. ERP read-cache policy

ERP reads are a Core responsibility.

The cache architecture MUST include:

```text
Business module
      ↓
ERP Provider Contract
      ↓
Request Identity Map
      ↓
Network-global persistent cache
      ↓
ERP API only on cache miss
```

## 13.1. Request-level deduplication

During one PHP request, an ERP object MUST NOT be fetched remotely more than once for the same canonical identity.

Repeated:

```php
$productProvider->find('123');
```

must resolve from in-request memory after the first resolution.

## 13.2. Network-global persistent cache

ERP data is network-global.

A product from Axonaut is the same object whether requested from:

- `my`;
- `www`;
- cron;
- WP-CLI;
- webhook processing.

ERP cache entries MUST therefore not be site-specific.

Conceptual keys:

```text
rc_erp:axonaut:product:123
rc_erp:axonaut:company:456
rc_erp:axonaut:contact:789
```

Core owns cache-key generation.

Business modules MUST NOT create ERP cache keys directly.

## 13.3. N+1 prevention

Provider APIs SHOULD support bulk/prefetch operations such as:

```php
findMany(array $ids)
```

when screens or workflows need multiple external objects.

Modules SHOULD NOT implement loops that cause uncontrolled remote reads.

## 13.4. Anti-stampede

Core MUST prevent simultaneous cache expiry from generating many identical ERP requests.

An implementation MAY use:

- short-lived distributed locks;
- stale-while-revalidate;
- controlled retry;
- stale fallback where safe.

## 13.5. Webhook invalidation

ERP webhooks SHOULD invalidate or refresh precise Core cache entries.

Long TTLs are acceptable when reliable invalidation exists.

---

# 14. Public rendering policy

`www` MUST NOT require ERP availability for normal page rendering.

Public product request:

```text
Browser
  ↓
www
  ↓
Local WC_Product
  ↓
HTML
```

Forbidden normal rendering flow:

```text
Browser
  ↓
www
  ↓
my
  ↓
ERP
  ↓
HTML
```

Public projections MUST be materialized locally.

---

# 15. Product projection

ERP owns the product.

`my` may enrich or pilot publication.

`www` owns the materialized WooCommerce representation.

Conceptual flow:

```text
ERP Product
    ↓
my business/application layer
    ↓
projection
    ↓
www WC_Product
```

Typical mapping:

```text
ERP reference      → Woo SKU
ERP name           → Woo product name
ERP selling price  → Woo regular price
ERP tax data       → Woo tax configuration/mapping
```

RC-Catalog or future RC-Products owns mapping rules.

Core owns only generic projection mechanisms.

ERP-owned mapped fields MUST NOT become independently editable authoritative values on `www`.

---

# 16. RC-Catalog migration role

RC-Catalog remains operational during migration.

It MUST NOT be split immediately.

Migration approach:

1. preserve current behavior;
2. align with new RC-Core;
3. replace local generic mechanisms with Core mechanisms;
4. introduce internal source/sink abstractions;
5. migrate progressively toward REST calls to `my`;
6. keep `www` functional throughout;
7. extract business domains only after Multisite stabilization.

RC-Catalog may remain the public projection plugin on `www`.

---

# 17. Internal REST boundary

Business communication between `www` and `my` SHOULD use an internal REST API instead of direct PHP dependencies or `switch_to_blog()`.

Target:

```text
www
  ↓
Core Internal REST Client
  ↓
my
```

Core MUST define:

- internal request authentication;
- signing;
- timestamp validation;
- replay protection;
- standardized errors;
- timeouts;
- retry policy;
- observability.

A recommended design is HMAC signing using:

- HTTP method;
- route;
- timestamp;
- nonce;
- request body hash.

Secrets MUST NOT be hardcoded in plugin source code.

---

# 18. Lead submission policy

Public leads MAY be transactionally submitted from `www` to `my`.

Conceptual flow:

```text
Public form
   ↓
RC-Catalog www
   ↓
Internal REST POST
   ↓
RC-Leads my
```

A generic Core outbox/retry mechanism SHOULD be available so temporary `my` unavailability does not lose a lead.

RC-Catalog MUST NOT directly depend on RC-Leads PHP code.

---

# 19. `switch_to_blog()` policy

`switch_to_blog()` changes WordPress site context but does not dynamically load or unload plugins.

It MUST NOT be used as the main business integration mechanism between `www` and `my`.

Allowed use cases:

- network maintenance;
- migrations;
- low-level Core utilities;
- carefully controlled WordPress operations;
- one-off technical tooling.

Forbidden architectural assumption:

> switching to another site makes its site-only plugins available.

Cross-site business communication SHOULD use explicit contracts or internal REST.

---

# 20. Plugin activation policy

## RC-Core

- MUST be Network Active.

## RC-Catalog

Target:

- active on `www`;
- not required on `my`.

## Business plugins

Examples:

- RC-Leads;
- RC-Products;
- RC-Assets;
- RC-Interventions;
- RC-Inventory.

Target:

- active on `my`;
- inactive on `www`.

Business plugins SHOULD therefore remain unaware of public-site concerns.

---

# 21. User model

WordPress Multisite users are network-level identities.

Roles and capabilities are site-contextual.

A user may have different rights on:

- `www`;
- `my`.

`my` is the authoritative application context for internal, customer and partner permissions.

---

# 22. Roles and capabilities

Core owns the role/capability framework.

Business modules declare required capabilities.

Example:

```text
RC-Assets declares:
- rc_assets_read
- rc_assets_create
- rc_assets_edit
- rc_assets_delete
```

Core handles:

- capability registration;
- capability migrations;
- role mappings;
- permission checks/conventions;
- lifecycle updates;
- multisite-aware assignment rules.

Business plugins MUST NOT maintain independent role frameworks.

## Suggested role families

Names are not yet frozen, but the platform must support at least:

- internal administrator;
- internal technician;
- internal sales/commercial;
- customer;
- partner.

Permissions SHOULD use capabilities rather than hard-coded role names.

Forbidden:

```php
if ($user->roles[0] === 'customer') { ... }
```

Preferred:

```php
current_user_can('rc_assets_read');
```

---

# 23. Front / back isolation on `my`

Every business service MUST be independent from its UI.

Target layering:

```text
Domain / Application Service
         │
     ┌───┼─────────┐
     ▼   ▼         ▼
WP Admin REST   Woo My Account
```

Future:

```text
Domain / Application Service
         │
         ▼
REST API
         │
         ▼
Front-only application
```

Business logic MUST NOT be embedded exclusively inside:

- admin screens;
- form callbacks;
- templates;
- REST controllers.

Controllers and UI handlers MUST remain thin.

---

# 24. Future front-only application

The architecture MUST allow `my` to evolve from:

```text
Internal  → WP Admin
Customer  → Woo My Account
```

toward:

```text
Unified front application
├── internal
├── customer
└── partner
```

without rewriting business services or changing data ownership.

This is a design constraint, not an immediate implementation requirement.

---

# 25. Database ownership

Each module owns its business schema.

Core owns schema tooling, not every table definition.

Example:

```text
RC-Assets
  → defines asset tables

RC-Interventions
  → defines intervention tables

RC-Inventory
  → defines serialized inventory tables
```

For true network-global data, use network-level tables based on:

```php
$wpdb->base_prefix
```

Site-specific WordPress projections remain in the relevant site's standard WordPress tables.

A module MUST NOT directly write another module's tables.

---

# 26. Module-to-module collaboration

Direct module dependencies are forbidden.

Allowed mechanisms:

## 26.1. Core-defined contracts

Core may define shared capability/provider contracts.

A module may implement a Core contract.

Another module may consume only the Core contract.

## 26.2. Core event bus

Modules may publish domain events through Core.

Examples:

```text
asset.created
asset.updated
intervention.closed
inventory.serial.assigned
```

Listeners MUST remain optional.

A module MUST continue functioning when another business module is disabled unless its own domain is intrinsically impossible without that capability.

---

# 27. Graceful degradation

A business plugin MUST NOT fatal-error because another business plugin is disabled.

Example:

```text
RC-Core          active
RC-Products      active
RC-Assets        disabled
```

RC-Products MUST continue to function.

Optional asset-related features MAY disappear or report unavailable capability.

---

# 28. Translation policy

The Core translation mechanism is mandatory for RC modules.

Modules MUST NOT implement parallel translation systems for shared platform concepts.

Public WordPress content translations on `www` may continue to use Polylang where appropriate.

Core and Polylang responsibilities MUST remain distinct:

- Core: application/module strings and platform translation helpers;
- Polylang: public translated WordPress content and relationships.

---

# 29. Placeholder policy

Core owns the placeholder engine.

Modules may register domain-specific placeholders through the Core API.

Modules MUST NOT implement independent placeholder parsers/resolvers.

Example concept:

```text
Core:
  PlaceholderRegistry

RC-Assets:
  registers asset.serial_number

RC-Products:
  registers product.reference
```

---

# 30. Logging policy

All RC plugins MUST use the standard Core logging API / approved logger integration.

Modules MUST NOT implement divergent log systems.

Logs SHOULD include structured context where possible:

- module;
- action;
- entity type;
- entity ID;
- request/correlation ID;
- ERP provider;
- user ID when relevant;
- site context;
- duration;
- error code.

Secrets, tokens and sensitive payloads MUST NOT be logged.

---

# 31. ERP observability

Core SHOULD expose ERP read telemetry.

At minimum, developers should be able to determine per request:

```text
ERP logical reads
Request-cache hits
Persistent-cache hits
Remote API calls
Remote duration
Failures
```

Public `www` rendering target:

```text
Remote ERP GET = 0
```

Normal cached `my` screens SHOULD minimize remote reads.

---

# 32. REST API conventions

Core defines shared REST conventions:

- namespaces;
- authentication helpers;
- permission callbacks;
- validation;
- response envelopes if used;
- error codes;
- pagination;
- internal API signing;
- correlation IDs.

Modules own their domain routes and application behavior.

REST controllers MUST NOT contain deep business logic.

---

# 33. Security constraints

All module development MUST follow WordPress security practices.

Mandatory where relevant:

- capability checks;
- nonce checks for browser actions;
- strict REST permission callbacks;
- sanitization at input boundaries;
- escaping at output boundaries;
- prepared SQL statements;
- allowlists for controlled values;
- CSRF protection;
- replay protection for internal signed REST;
- rate limiting where exposed publicly;
- least privilege;
- no secrets in source code;
- no unserialize of untrusted data;
- no arbitrary file access;
- no dynamic code execution;
- safe media/file validation.

Internal REST MUST NOT rely solely on obscurity or network location.

---

# 34. Context isolation

Modules must explicitly separate:

- internal backoffice;
- customer frontend;
- partner frontend;
- REST;
- cron/background tasks;
- CLI where applicable.

Public `www` context MUST remain isolated from `my` business implementation details.

A public plugin MUST communicate through stable APIs, not internal class knowledge.

---

# 35. Theme responsibilities

The theme is a rendering layer.

It MAY:

- render public content;
- provide templates;
- provide CSS/JS;
- consume public plugin APIs;
- provide presentation-specific hooks.

It MUST NOT:

- become an ERP client;
- own business rules;
- own shared cache logic;
- implement platform-wide translations/placeholders;
- write directly to business tables;
- duplicate plugin services.

Business logic discovered inside the existing theme should be moved to the appropriate plugin/Core during refactoring.

---

# 36. Coding standards

Code MUST:

- be written in English;
- use clear English comments;
- use PHPDoc where useful;
- follow WordPress coding/security principles unless a documented project convention supersedes formatting details;
- use strict and consistent namespaces;
- avoid global mutable state where possible;
- favor dependency injection/service registration over hidden singletons;
- have explicit input/output boundaries;
- use DTOs/value objects when they materially improve contracts.

---

# 37. Namespace policy

Actual namespaces in shipped code today (2026-09-19) — this supersedes the
originally-planned list below:

```text
WPRC\Core\                       (RC Core — note the "W" prefix; shipped
                                   code has never used bare "RC\Core\")
RC\Portal\                       (RC Portal runtime)
RC\Portal\Modules\{Module}\      (each embedded `my`-side business module,
                                   e.g. RC\Portal\Modules\Products\)
```

`RC\Catalog\` remains reserved for the separate, still-undecided `www`-side
projection plugin (see Amendments above) — not an embedded `rc-portal`
module.

Originally-planned, now-superseded list (kept for history — do not use):

```text
RC\Core\
RC\Leads\
RC\Products\
RC\Assets\
RC\Interventions\
RC\Inventory\
```

A module MUST NOT import another business module's namespace
(`RC\Portal\Modules\{OtherModule}\`). RC Core MUST NOT import `RC\Portal\*`
or `RC\Catalog\*`.

This rule is automatically checked by `rc-core/tools/architecture-preflight.php`
(the Core-boundary check) and by `rc-portal/tools/preflight.php` (the
module-to-module isolation check, since all `my`-side modules live in one
repository — see Amendments above).

---

# 38. Versioning and Core compatibility

Core exposes a public platform API.

Business modules MUST declare compatible Core requirements.

Core changes SHOULD follow semantic-impact discipline:

- additive compatible changes;
- deprecation;
- controlled removal.

Breaking Core changes MUST NOT silently break deployed modules.

Temporary compatibility shims MAY be used during the current migration but MUST be:

- explicitly marked deprecated;
- documented;
- scheduled for removal before or during Multisite stabilization.

---

# 39. Migration philosophy

No big-bang rewrite.

Migration order is frozen as:

1. refactor RC-Core;
2. adapt RC-Catalog to new Core without immediate functional split;
3. adapt RC-Leads to new Core without immediate functional split;
4. refactor theme;
5. migrate to Multisite;
6. continue/extract new business modules.

During steps 2 and 3:

- preserve user-visible behavior;
- avoid unnecessary data migrations;
- avoid premature module extraction;
- introduce compatibility adapters where justified;
- remove duplication progressively.

---

# 40. Multisite migration principle

Multisite migration occurs only after Core, Catalog, Leads and theme are aligned with the architecture.

The migration must not simultaneously introduce:

- a new Core architecture;
- domain plugin extraction;
- large irreversible data migrations;
- new public URL behavior;
- major business UX rewrites.

Each risk must be isolated into a controlled phase.

---

# 41. RC-Catalog future

RC-Catalog remains the public catalogue/projection layer on `www`.

During transition it may remain hybrid.

Progressively:

```text
legacy/local source
      ↓
internal adapter
      ↓
REST source from my
```

It may expose:

- Woo projections;
- public catalogue behavior;
- technical model projections;
- public forms;
- SEO/public integration;
- internal REST bridge.

Later domain extraction may introduce:

- RC-Products on `my`;
- RC-Assets on `my`.

The RC-Catalog name MAY remain appropriate as the `www` publication plugin.

---

# 42. Future RC-Products

Expected ownership:

- ERP product consumption;
- WordPress-side product enrichment on `my`;
- publication state;
- projection orchestration toward `www`;
- product-related business metadata not authoritative in ERP.

It MUST NOT become an ERP replacement.

---

# 43. Future RC-Assets

Expected ownership:

- technical robot model repository;
- controller models;
- robot/controller compatibility;
- technical specifications;
- service/maintenance technical data;
- physical robot/assets;
- asset ownership/location references;
- model publication orchestration where required.

Technical models belong to the asset domain because they define the template/specification for physical assets.

---

# 44. Future RC-Interventions

Expected ownership:

- intervention lifecycle;
- scheduling-specific business data not owned elsewhere;
- technician/intervention relations;
- intervention measurements and results;
- consumed business references via Core contracts;
- customer-facing intervention views.

It MUST NOT directly depend on RC-Assets or RC-Inventory PHP code.

---

# 45. Future RC-Inventory

Expected ownership:

- serialized inventory;
- serial state;
- stock locations specific to RC business rules where not delegated to ERP;
- serialized movement history;
- assignment relationships through Core-defined contracts/events.

The exact boundary with a future Odoo inventory must be re-evaluated before implementation if ERP ownership changes.

---

# 46. Tests and preflight

The platform SHOULD enforce architecture automatically.

Preflight/CI target checks:

```text
✓ PHP syntax
✓ coding standards
✓ PHPUnit
✓ database/schema tests
✓ Core API compatibility
✓ forbidden cross-module namespace imports
✓ forbidden direct ERP HTTP calls outside Core
✓ forbidden duplicate platform infrastructure
✓ REST permission callbacks
✓ capability registration
✓ translation registration
✓ cache behavior
✓ ERP request deduplication
✓ multisite context tests
✓ public render performs zero ERP GET
```

Architecture rules that can be machine-checked SHOULD be machine-checked.

---

# 47. Deployment constraints

Deployments MUST favor reversibility.

Schema changes SHOULD be additive before destructive cleanup.

Destructive migrations SHOULD occur only after:

- new code is validated;
- data has been verified;
- rollback strategy is known.

Production migrations SHOULD not require manual patching of plugin source code per site/domain.

---

# 48. Hard-coded context prohibition

Business code MUST NOT rely on:

```php
$blog_id === 1
$blog_id === 2
```

or hard-coded production domains to determine application behavior.

Core MAY expose network/site identity primitives where genuinely necessary.

For the target architecture, business plugins on `my` SHOULD rarely need network context at all.

---

# 49. Public projection invariants

A public projection MUST:

- be locally renderable;
- contain a stable upstream mapping;
- record synchronization state;
- be reproducible;
- tolerate temporary ERP or `my` unavailability;
- never silently become the authoritative ERP record.

Useful metadata may include:

```text
upstream provider
upstream entity ID
projection version/hash
last synchronized timestamp
projection status
last error
```

Exact implementation remains a domain decision.

---

# 50. Frozen architecture rules

The following are considered frozen unless explicitly revised:

1. ERP owns commercial ERP data.
2. `my` owns Robotique Concept-specific business data.
3. `www` is a public projection and acquisition site.
4. RC-Core is Network Active.
5. RC-Core is the platform SDK and convention authority.
6. Business modules depend only on RC-Core (decided 2026-09-19, no
   exception: the `docs/PORTAL-UI.md` presentation contract has modules
   supply semantic data only, never HTML — Portal's role is consuming
   that data, not being "depended on" for rendering. Not yet fully
   implemented — see Amendments above and `docs/PORTAL-UI.md`).
7. RC-Core does not depend on business modules.
8. Business modules do not depend on each other (enforced today via
   `rc-portal/tools/preflight.php`'s cross-module-import check, since all
   `my`-side modules are embedded in one repository — see Amendments
   above).
9. ERP HTTP access belongs to Core.
10. ERP cache is network-global.
11. ERP reads are request-deduplicated.
12. `www` public rendering performs no ERP lookup.
13. Cross-site business communication uses internal REST progressively.
14. `switch_to_blog()` is not a business integration strategy.
15. Roles/capabilities are governed by Core.
16. Modules declare capabilities; Core manages the framework.
17. Translations/placeholders/logger shared mechanisms belong to Core.
18. Business logic remains independent from UI.
19. The current migration is incremental, not a rewrite.
20. Existing RC-Catalog remains operational until its migration phase is complete (RC-Leads is no longer a separate plugin — it is the `leads` module embedded in RC Portal, see Amendments above).

---

# 51. Architecture decision test

Before adding a new feature, answer:

### A. Is this ERP-owned data?
If yes, access it through a Core ERP contract.

### B. Is this Robotique Concept business data?
If yes, identify the owning `my` business module.

### C. Is this only a public representation?
If yes, it belongs to the `www` projection layer.

### D. Is the functionality generic/cross-cutting?
If yes, it belongs to Core.

### E. Does implementation require importing another business module?
If yes, the design is invalid and must be replaced by a Core contract/event/API.

### F. Does `www` need live ERP data to render?
If yes, the design is invalid for normal public rendering.

### G. Is the business rule embedded in UI/controller/template code?
If yes, move it into a reusable application/domain service.

---

# 52. Definition of architecture compliance

A module is considered compliant when:

- it depends only on Core;
- it uses Core-provided shared infrastructure;
- it does not duplicate platform services;
- it owns only its domain logic and schema;
- it has no direct ERP HTTP implementation;
- it can be disabled without fatal errors in other business modules;
- its business services are callable independently from UI;
- permissions use Core conventions;
- logs use Core conventions;
- REST uses Core conventions;
- its cross-domain communication uses Core contracts/events;
- its code passes architecture preflight checks.

---

# 53. Current migration sequence

```text
Current monolithic installation
        │
        ▼
[1] RC-Core refactor
        │
        ▼
[2] RC-Catalog adaptation
    no immediate split
        │
        ▼
[3] RC-Leads adaptation
    no immediate split
        │
        ▼
[4] Theme adaptation
        │
        ▼
[5] Multisite migration
    my = business application
    www = public projection
        │
        ▼
[6] New domain modules
    RC-Products
    RC-Assets
    RC-Interventions
    RC-Inventory
```

This sequence MUST preserve production continuity.

---

# 54. Guideline governance

This document is the default architecture authority for RC WordPress development.

When a future requirement conflicts with this guideline:

1. the conflict must be identified explicitly;
2. the architectural impact must be assessed;
3. the decision must be validated;
4. this document must be updated;
5. existing validated rules must not be silently changed.

