# Changelog

## 0.6.0-alpha10
- Add `ProductProviderInterface::updateInternalId(string $externalId, string $internalId): bool`, implemented in the Axonaut provider via a `PUT /products/{id}` request (RC Products, Phase 2 follow-up: writing the RC-side identifier back onto the Axonaut record's `internal_id` establishes a strong two-way link between the ERP and the RC application). Returns `false` on any transport/HTTP failure instead of throwing, purges the affected GET caches on success.
- Add `ecoParticipation`/`taxDeee` to `ProductData`, mapped from Axonaut's native `eco_participation`/`tax_deee` fields — these were missing even though they are part of the native (non-`custom_fields`) product schema RC Products now treats as authoritative from the ERP.

## 0.6.0-alpha9
- Add `findMany(array $externalIds): array` to `CompanyProviderInterface`, `ProductProviderInterface`, `EmployeeProviderInterface`, `OpportunityProviderInterface` and `QuotationProviderInterface`, so modules can resolve several ERP entities in one call without duplicating caching logic (routes through the same per-id request cache / persistent cache / cache lock as `find()`).
- Add an optional `bool $checkCapability = true` parameter to `UiRegistry::match()`. Existing callers (`AdminSurface`) are unaffected; a caller can now pass `false` to resolve a route independently of the current user's capability and apply its own access decision (needed by RC Portal's forthcoming Internal-surface router adapter).
- Add the `rc_support` role to `RolePolicy`, with the same default `CAP_INTERNAL`/`CAP_PORTAL` grants as `rc_technician`/`rc_logistics`, ahead of the Support module (Phase 6).
- Rename the `customer` role to `rc_customer` to align with the platform's `rc_*` role naming convention. A one-time migration in `Installer` (DB version `2026.09.16.1`) reassigns any account still holding the legacy role before removing it, so no access is lost regardless of whether real accounts exist on `my` yet.

## 0.6.0-alpha8
- Expand the normalized ERP product contract with native Axonaut product fields while keeping legacy fields backward compatible.
- Add cached `ProductProviderInterface::all()` and disabled-product support for business catalog modules.
- Product collection/detail reads use Core request cache + native WordPress object cache (Redis when available) with product-specific TTLs.
- Expose legacy ERP custom fields read-only for controlled migration into module-owned enrichment; they are not canonical RC data.
- Add `TechnicalReferenceCatalogInterface` so modules can list robot models/controllers through a Core contract without depending on RC Assets.
- Add `ProductCatalogProviderInterface` / `CatalogProductData` for future cross-module access to RC-enriched products without depending on RC Products.
- Update module-development guidance to reflect RC Portal as the single presentation infrastructure for application modules.


## 0.6.0-alpha7
- Add application-site manufacturer reference owned by Core for cross-module use.
- Add admin-only manufacturer management under Tools on `my`.
- Keep manufacturer IDs stable for module migrations.

## 0.6.0-alpha6
- Added explicit `navigationParent` metadata to Core UI pages.
- Added backward-compatible `parentPath` argument to `rc_register_ui_page()` / `UiRegistry::register()`.
- Navigation hierarchy can now be declared by modules instead of inferred from URL shape.

## 0.6.0-alpha5

- Added stable Core read contracts for physical assets and technical robot models/controllers.
- Added immutable Core DTOs `AssetData`, `TechnicalModelData` and `ControllerData` for cross-module communication without business-module dependencies.
- No schema or runtime behavior change in RC Core.

## 0.6.0-alpha4

- Fixed RC Portal Turnstile login being validated twice when native WordPress login protection was also enabled.
- Portal login submissions are now owned exclusively by the `portal_login` Turnstile context; the native `authenticate` filter skips `rc_portal_action=login`.
- No schema, API or role-policy change.

## 0.6.0-alpha3

- Added canonical platform access capabilities and capability-based personas.
- Added Admin / Internal / External UI registry for business modules.
- Added generic Core wp-admin surface adapter.
- Added Portal login Turnstile context.
- Added public `rc_register_ui_page()` helper.
- Added RC Portal architecture guidance.

## 0.6.0-alpha2 — 2026-09-13

### Added

- Network-level site roles: public site, application site and connector site.
- Network Admin **Rôles & permissions RC** page with capability matrix and application-site user role assignment.
- Network-persisted capability catalog, allowing permissions to be managed even when a business plugin is active only on another subsite.
- RC role policy for administrator, manager, sales, technician, logistics, customer and partner profiles.
- WooCommerce My Account Turnstile protection for login and registration on the configured application site.
- SiteContext helpers for public/application URLs and REST endpoints.

### Changed

- Role application is restricted to the configured application site.
- Network user assignment lists network identities rather than only users attached to the current Network Admin site.
- Architecture preflight now detects business-module → business-module dependencies, direct HTTP, direct role mutation and direct `switch_to_blog()` outside Core.

### Compatibility

- Requires no database schema migration.
- RC Catalog `1.7.0-alpha2` and RC Leads `0.3.0-alpha2` are the coordinated Multisite cutover releases.
- Existing connector credentials remain on the configured connector site.

## 0.6.0-alpha1 — 2026-09-12

### Added

- `ModuleInterface` and `ModuleRegistry` as the canonical future RC module lifecycle.
- Core capability and role registries with a common role application service.
- Generic `RequestContext`.
- ERP request identity map.
- Canonical ERP cache key factory.
- Distributed object-cache lock primitives.
- Request-scoped ERP telemetry.
- Signed internal REST client and authenticator foundations.
- Replay protection for internal REST requests.
- Architecture preflight CLI scanner.
- Public helpers `rc_register_module()` and `rc_register_capabilities()`.

### Changed

- Axonaut GET reads now use request-level deduplication before the persistent object cache.
- Axonaut GET cache misses use a short distributed lock to reduce cache stampedes.
- Axonaut transport records remote-call telemetry.
- Global cache groups now include ERP locks and internal API replay protection.
- RC Core exposes modules, capabilities, roles, request context, ERP telemetry and internal API services.

### Compatibility

- Existing ERP contracts remain unchanged.
- Existing public Core methods remain available.
- RC Catalog `1.6.0-alpha8` and RC Leads `0.2.0-alpha7` require no migration for this release.
- No database schema change.
