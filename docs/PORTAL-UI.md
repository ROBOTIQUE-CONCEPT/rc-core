# RC Portal presentation contract

> **Implementation status (2026-09-19):** this contract was confirmed
> normative with **no exception** — modules never build HTML, only Portal
> does. It is **not yet implemented**, though: `rc-portal`'s actual
> embedded modules (`products`, `tools`) currently register pages via a
> simpler mechanism (`EmbeddedModuleInterface` + `rc_register_ui_page()`
> with a raw callback that returns a finished HTML string), not the
> `PageDefinition`/`TableDefinition` objects described below. Treat this
> file as the target contract to migrate existing modules toward, not a
> description of what they do today. See
> `rc-core/docs/ARCHITECTURE-OPEN-QUESTIONS.md` for a related, still-open
> question about the module lifecycle contract itself.

RC Portal is the **single presentation infrastructure** for the private `my` application.

Core remains non-visual. It owns security, capabilities, routing primitives, contracts and the low-level UI route registry. Portal owns all layout/rendering/CSS/JS and exposes the declarative presentation API used by business modules.

## Dependency rule

```text
Business module -> RC Core   (infrastructure/contracts)
Business module -> RC Portal (declarative presentation only)
Business module -X-> another business module
RC Portal       -X-> business modules
RC Core         -X-> RC Portal/business modules
```

A business module may import `WPRC\Portal\UI\Definition\*` and `WPRC\Portal\UI\Registry` only for semantic page definitions. It must not ship HTML renderers, templates, presentation CSS or browser JS.

## Surfaces

The underlying Core route registry keeps the canonical surfaces:

```php
use WPRC\Core\UI\Surface;

Surface::ADMIN;
Surface::INTERNAL;
Surface::EXTERNAL;
```

A module registers a declarative page in Portal:

```php
use WPRC\Portal\UI\Definition\PageDefinition;
use WPRC\Portal\UI\Definition\TableDefinition;

$portal->register(new PageDefinition(
    'assets',
    Surface::INTERNAL,
    'models',
    'Mécaniques',
    'rc_assets_read',
    new TableDefinition(/* semantic columns */),
    [$this, 'modelsData'],
    20,
    true,
    '' // parent path = module root
));
```

Portal bridges that definition into Core routing and performs the actual rendering.

## Navigation hierarchy

Navigation hierarchy is explicit through `PageDefinition::$parentPath`.

- `null` = top-level navigation item.
- `''` = child of the module root.
- any other path = child of that page path.

Portal must never infer hierarchy from URL shape.

## Security

A page capability controls navigation visibility and page access. Business operations independently enforce capability + scope + nonce.

Browser REST mutations use the WordPress `wp_rest` nonce supplied by Portal. Business modules validate payloads again server-side.

## Forms and tables

Portal owns reusable behavior such as:

- consultation-first layouts;
- form layout and action rail;
- search and sortable tables;
- responsive behavior;
- filters;
- badges and notices;
- REST form submission;
- loading/error states;
- dependent selects and repeaters.

Modules only describe semantic fields/columns/actions and provide data.
