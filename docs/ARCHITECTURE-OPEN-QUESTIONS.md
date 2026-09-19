# Architecture open questions

This document exists only to record contradictions between normative
documentation and actual, running code that a documentation pass could not
resolve on its own — because resolving them requires a product/architecture
decision, not just a wording fix. Each entry stays until a human closes it
(by deciding, then updating the source documents and deleting the entry
here). Do not add entries for stale version numbers alone — fix those
directly in the affected file.

## 1. `rc-core`'s preflight tool and several docs describe a retired "standalone business-module plugin" architecture

**Context.** `docs/ARCHITECTURE.md` (§37, the "namespace policy" section) and
`tools/architecture-preflight.php` both assume business logic ships as
separate, independent plugins — one per domain (`RC-Catalog`, `RC-Leads`,
`RC-Products`, `RC-Assets`, `RC-Interventions`, `RC-Inventory`, `RC-Quotes`,
`RC-Contacts`, `RC-Orders`, `RC-Invoices`, `RC-Projects`, `RC-Tasks`,
`RC-Deliveries`), each under its own `RC\{Module}\` (per §37) or
`WPRC\{Module}\` (per the preflight script's hardcoded namespace map)
namespace root. `docs/MULTISITE-CUTOVER.md` is a concrete runbook built on
the same premise (it sequences updating "RC Core", "RC Catalog
1.7.0-alpha2", "RC Leads 0.3.0-alpha2" as three separately-deployed
plugins).

**What actually ships today:** a single `rc-portal` plugin that embeds all
current business modules (`products`, `tools`, `maintenance`, `inventory`,
`leads`) under one shared namespace root, `RC\Portal\Modules\{Module}\`.
There is no `RC-Catalog`, `RC-Leads`, or any other standalone business
plugin in this workspace's three repos (`rc-core`, `rc-portal`,
`rc-portal-theme`).

**Impact.**
- `tools/architecture-preflight.php`'s Check A/B (Core-must-not-depend-upward,
  module-to-module isolation) search for the namespace strings
  `WPRC\{Module}\` and `WPRC\Portal\`. Real `rc-portal` code uses
  `RC\Portal\*`, not `WPRC\Portal\*` — so when this script is pointed at the
  real `rc-portal` repo, its Portal/module isolation checks never match
  anything and silently pass regardless of what `rc-portal` actually
  imports. The isolation guarantee this tool is meant to provide is not
  currently being verified.
- `docs/MULTISITE-CUTOVER.md`'s runbook cannot be executed as written —
  the plugins it names to update in sequence do not exist as separate
  deployables anymore.

**Options.**
- (a) Update the preflight script's namespace map to the real
  `RC\Portal\Modules\{Module}\` convention and drop the standalone-plugin
  namespace list, then correct `ARCHITECTURE.md` §37 to match; mark
  `MULTISITE-CUTOVER.md` as historical (a past migration record, not a
  live runbook) rather than delete it.
- (b) Decide the standalone-per-domain-plugin model is still the intended
  *future* shape (i.e. `rc-portal`'s embedded modules are a transitional
  state) and keep the tool/docs aimed at that future, while documenting
  today's embedded-module reality as the current transitional exception.
- (c) Retire `tools/architecture-preflight.php` from `rc-core` entirely and
  rely on `rc-portal/tools/preflight.php`'s own cross-module-import check
  (which does target the real `RC\Portal\Modules\*` namespace and does work
  correctly today) as the sole enforcement point for module isolation.

**Recommendation.** (a) — it costs one afternoon, restores a currently-inert
safety check, and removes a runbook that cannot be followed as written. (c)
is a reasonable fallback if nobody wants to maintain two preflight tools,
but would leave `rc-core` with no isolation check of its own for anything
that isn't `rc-portal`.

**Status.** Pending decision.

## 2. Is module-owned HTML through the UI Registry contract the permanent presentation model, or a transitional exception?

**Context.** `docs/ARCHITECTURE.md`'s presentation rule and `rc-portal`'s own
`README.md` both state, in effect, that RC Portal Theme is the sole owner of
frontend HTML/CSS/JS on `my` and that business modules do not own visual
templates. In the actual, working implementation, RC Core's UI Registry
contract (documented in `docs/PORTAL-UI.md`) has a module's registered page
supply its *own* pre-rendered HTML string via a `renderer` callback; RC
Portal's router stores that string on `RouteContext::$pageHtml`, and the
theme's `templates/portal/parts/page.php` does `echo $context->pageHtml;`
verbatim. In `rc-portal`, `modules/products/src/Ui/ProductsPages.php` (over
1000 lines) and `modules/tools/src/Ui/ToolsPages.php` build complete
`<table>`/`<form>`/tab markup this way — this is the normal, working
mechanism for every module page today, not a bug or a one-off shortcut.

**Impact.** An agent reading "the theme owns all presentation" literally
would either wrongly flag every module's `Ui/*Pages.php` class as a
violation, or wrongly conclude modules should stop using the UI Registry
render contract — neither is correct today. Conversely, an agent extending
this pattern indefinitely (more and more markup logic living in module `Ui`
classes) forecloses ever moving to a model where modules hand the theme
structured data and only the theme decides markup — a heavier, harder to
reverse investment.

**Options.**
- (a) Formalize the current behavior as permanent and correct
  `ARCHITECTURE.md`/`rc-portal/README.md` wording: the theme owns layout,
  chrome, design system, CSS and JS; a module owns the content HTML of the
  pages it registers, produced through the UI Registry `renderer` contract.
- (b) Keep the "theme owns all presentation" rule as the long-term target
  and schedule a migration where module render callbacks return structured
  data (arrays/DTOs) and the theme (or a shared Portal template layer) is
  responsible for turning that into HTML — a real, multi-module refactor.

**Recommendation.** No recommendation offered here — both are workable and
the cost/benefit depends on product priorities (how many more module UIs
are expected, how much reuse across modules would benefit from shared
templates) that are not this audit's call to make.

**Status.** Pending decision.
