# RC Core 0.6.0-alpha2

Socle technique et SDK partagé des plugins WordPress Robotique Concept.

- WordPress : `>= 6.8`
- PHP : `>= 8.1`
- Namespace : `WPRC\Core`
- Compatible single-site et multisite
- Cible : activation réseau en Multisite

## Rôle

RC Core est l'autorité de conventions de la plateforme RC. Les modules métier dépendent de Core ; Core ne dépend d'aucun module métier.

Core fournit notamment :

- conteneur de dépendances ;
- registre de services publics ;
- lifecycle commun des futurs modules RC ;
- framework central des capabilities / rôles ;
- contrats ERP et providers actifs ;
- cache WordPress / Redis ;
- cache ERP global réseau et déduplication dans la requête ;
- télémétrie ERP ;
- primitives de verrouillage anti-stampede ;
- client et authentification REST interne signés ;
- logger ;
- placeholders ;
- traductions RC ;
- UID ;
- contexte WordPress générique ;
- contexte de site ;
- connecteurs Axonaut, Mailjet et Turnstile.

Core ne doit contenir aucune règle métier propre au catalogue, aux assets, aux interventions, à l'inventaire ou aux leads.

## Dépendances autorisées

```text
Business module -> RC Core
```

Interdit :

```text
Business module -> Business module
RC Core -> Business module
```

## Module lifecycle

Les futurs modules migrés vers le SDK Core implémentent :

```php
WPRC\Core\Contracts\ModuleInterface
```

Ils sont enregistrés pendant :

```text
wprc/core/register_modules
```

Puis Core exécute leur phase `boot()` avant :

```text
wprc/core/ready
```

Les versions actuelles de RC Catalog et RC Leads restent compatibles et ne sont pas obligées d'utiliser ce lifecycle avant leur phase de migration.

## Capabilities

Core possède le mécanisme de permissions.

Les modules déclarent uniquement leurs capabilities :

```php
rc_register_capabilities('assets', [
    'rc_assets_read',
    'rc_assets_create',
    'rc_assets_edit',
    'rc_assets_delete',
]);
```

Les modules ne doivent pas créer leur propre framework de rôles/capabilities.


## Multisite site roles

Core now identifies three network responsibilities from Network Admin settings:

```text
public site      → www / public projection
application site → my / business application
connector site   → site storing WordPress Connector credentials
```

Consumers use `rc_core()->sites()` and never hard-code blog IDs or domains.

## Network roles and capabilities

Network Admin exposes **Rôles & permissions RC**. Modules declare `rc_*` capabilities; Core persists the catalog network-wide and applies the selected matrix only to the application site. User identities remain network-wide while role membership remains WordPress site-specific.

Initial RC profiles are: administrator, rc_manager, rc_sales, rc_support, rc_technician, rc_logistics, rc_customer and rc_partner. The matrix is authoritative; business code must test capabilities, never role names.

## WooCommerce Turnstile

Turnstile settings can protect WooCommerce login and registration on the configured application site without overriding Woo templates. The integration uses WooCommerce's public form hooks and validation filters.

## ERP

Les consommateurs accèdent à l'ERP via les contrats Core.

Contrats actuellement exposés :

- `ProductProviderInterface` ;
- `CompanyProviderInterface` ;
- `EmployeeProviderInterface` ;
- `AddressProviderInterface` ;
- `OpportunityProviderInterface` ;
- `QuotationProviderInterface`.

Les futurs contrats Contact / Order / Invoice seront ajoutés lors de l'extension des providers réels ; aucun endpoint ERP n'est inventé dans cette release.

## Cache ERP v2 — fondations

Le groupe Axonaut existant reste :

```text
wprc_axonaut
```

Il demeure global réseau en Multisite.

La lecture GET suit maintenant :

```text
logical read
   ↓
request identity map
   ↓
persistent object cache (Redis)
   ↓
short distributed lock
   ↓
Axonaut only on miss
```

Les groupes globaux Core incluent désormais aussi :

```text
wprc_erp
wprc_erp_locks
wprc_internal_api
```

`ErpTelemetry` expose pour la requête courante :

- logical reads ;
- request-cache hits ;
- persistent-cache hits ;
- remote calls ;
- remote errors ;
- remote duration ;
- remote calls by endpoint.

Accès :

```php
rc_core()->erpTelemetry()->snapshot();
```

## REST interne

La release introduit les fondations de communication explicite entre futurs sites `www` et `my` :

- `InternalApi\Client` ;
- `InternalApi\Authenticator` ;
- `RequestSigner` ;
- `ReplayGuard` ;
- secret réseau partagé.

La signature HMAC couvre :

- méthode HTTP ;
- route ;
- query canonique ;
- hash du body ;
- timestamp ;
- nonce.

Le secret peut être fourni par `RC_INTERNAL_API_SECRET`. À défaut, Core génère et persiste un secret dans ses réglages réseau lors de la première utilisation.

Aucune route métier n'est ajoutée par Core dans cette release.

## Runtime context

Le contexte WordPress générique est disponible via :

```php
rc_core()->requestContext();
```

Il fournit : admin, AJAX, REST, CLI, cron, frontend et site ID.

## API publique principale

```php
$core = rc_core();

$core->container();
$core->services();
$core->modules();
$core->capabilities();
$core->roles();
$core->requestContext();
$core->erp();
$core->erpTelemetry();
$core->cache();
$core->internalApi();
$core->internalApiAuthenticator();
$core->logger();
$core->uid();
$core->placeholders();
$core->translations();
$core->sites();
$core->credentials();
$core->mailjet();
$core->mailjetContacts();
$core->turnstile();
```

APIs existantes conservées sans rupture.

## Outil de preflight architecture

```bash
php tools/architecture-preflight.php /path/to/rc-core /path/to/rc-catalog /path/to/rc-leads
```

Le scanner vérifie notamment :

- Core ne référence pas les namespaces des modules métier ;
- aucun `wp_remote_*()` direct n'apparaît dans un module métier scanné.

Le scanner vérifie aussi les dépendances Module → Module, les mutations directes de rôles et les changements de site directs hors Core.

## Traductions RCONCEPT

Le mécanisme `0.5.x` est conservé :

```php
rc_register_translations('rc-catalog', [
    'key' => ['fallback' => '...'],
]);

rc_translate('key');
```

Il n'existe pas de second moteur de traduction.

## Compatibilité

`0.6.0-alpha2` reste additive côté données métier et coordonne le cutover Multisite avec Catalog/Leads.

Historique `0.6.0-alpha1` :

- pas de migration métier ;
- pas de changement de table ;
- pas de changement d'URL ;
- pas de suppression des contrats ERP existants ;
- pas de modification requise de RC Catalog `1.6.0-alpha8` ;
- pas de modification requise de RC Leads `0.2.0-alpha7`.


## UI platform (0.6.0-alpha3)

Business modules use RC Portal declarative definitions for all Admin/Internal/External presentation. Portal is the sole renderer/CSS/JS owner on `my` and bridges routes into Core. Core remains non-visual and owns access capabilities, persona resolution, security and routing primitives. See `docs/PORTAL-UI.md`.

## Asset contracts (0.6.0-alpha5)

Core exposes cross-module read-only contracts for the Assets domain:

- `WPRC\Core\Contracts\Assets\AssetProviderInterface`
- `WPRC\Core\Contracts\Assets\TechnicalModelProviderInterface`

and immutable DTOs under `WPRC\Core\Data\Assets`.

Business modules may consume these contracts through `rc_core()->services()` but must never import `WPRC\Assets\*` classes or query RC Assets tables directly.


## Shared manufacturers
Core owns the application-site manufacturer reference used by business modules. It is administered only by users with `manage_options` on the application site.
