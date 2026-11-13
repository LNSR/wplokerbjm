<!-- Context: project-intelligence/technical | Priority: critical | Version: 3.5 | Updated: 2026-11-13 -->

# Technical Domain — Lowker Site Theme

> WordPress theme (wplokerbjm) — headless job board with PHP 8 DI container, WPGraphQL, WP REST API.

## Primary Stack

| Layer        | Technology                              | Version | Rationale                                                |
| ------------ | --------------------------------------- | ------- | -------------------------------------------------------- |
| Platform     | WordPress                               | 7.x     | CMS; custom theme `wplokerbjm`                           |
| Language     | PHP                                     | 8.5     | PHP 8 attributes (`#[Action]`, `#[Filter]`) + named args |
| DI Container | PHP-DI                                  | v7      | Autowiring, lazy service resolution, MU-plugin lifecycle |
| Cache        | Redis (object-cache.php) + APCu         | —       | APCu for fast local; Redis fallback for shared/prod      |
| GraphQL      | WPGraphQL plugin                        | latest  | Schema registered via `graphql_register_types` hook      |
| Frontend     | SvelteKit (this repo — `../sveltekit/`) | latest  | Headless; WordPress is the API backend only              |
| Database     | MySQL (via wpdb)                        | —       | Standard WP; custom `JobQuery` builder for search SQL    |
| Testing      | PHPUnit + real WP `WP_Hook` engine      | latest  | Hook tests run WP core; brain/monkey for non-hook funcs  |

## Architecture Pattern

Headless WordPress (API-only backend): Controller/Resolver → DI → attribute hooks → WP lifecycle. Frontend is SvelteKit in `../sveltekit/` (same monorepo).

## Project Structure

```
server/
├── Core/{Container/{Attributes,Definitions,Support},Wordpress/{Abilities,Cron,Plugins,Redirect,Theme,Utilities}}
├── Transport/{REST/{Controllers,Route},GraphQL/{Registration,Resolvers}}
├── Services/{Auth/JWT,GraphQL/{ETag,Hooks},REST/Ingest,Schema/SEO,SEO/Integrations,WebHooks}
├── Presenters/{Header/Components,Page/{Homepage,JobDetail}}     # response shaping
├── Repositories/  QueryBuilders/  Models/{Schema}               # persistence + SQL
├── Adapter/  Configs/{Credential}  Factories/
└── Shared/{Cache,Log,Utilities/{Auth,DataObject}}
wplokerbjm/ root → tests/  tools/Composer/Scripts/  cache/  composer.json (classmap, NOT PSR-4)
```

**Layering**: `Transport → Services → Repositories/QueryBuilders → Models`. `Core` = infrastructure only; `Presenters` shape output; `Adapter` isolates third-party APIs.

## Dev Workflows (Critical)

**Autoload (classmap, NOT PSR-4)**: add/rename/move a class → regenerate the map or WP won't find it. VS Code task `php-composer-autoload` (`@wplokerbjm.code-workspace`) → `composer run watch:autoload` → `tools/Composer/Scripts/autoloadwatcher.sh` (inotifywait, 2s debounce → `composer dump-autoload --apcu -a -o` → `docker exec wordpress-${WP_ENV} ... litespeed-purge all`).

**Tests**: `composer run test` → `tools/Composer/Scripts/test.sh` loads `../../../../.env`, then runs PHPUnit inside `wordpress-${WP_ENV:-production}` (`--colors=always --fail-on-skipped --testdox`).
- Hook tests: `tests/WPHookTests/` (InitLazy, Once/OnceChain, Deferred, DeferRegisterUntilHook, RuntimeRegistry, PropertyHook, TaggedHook, PatternHooks, EntriesIndexer, ExecuteIf, RegisterIf) + `TestTracTicket61263.php`; engine = real WP `wp-includes/plugin.php` (`WplokerbjmTestCase` resets `$wp_filter`/`$wp_actions` per test), brain/monkey only for non-hook WP functions. Top-level: `CacheTest`, `ContainerDefinitionsTest`, `DependencyInjectorTest`, `LowonganIngestRestTest`.

**Lint / Xdebug**: `composer run lint:php` → `tools/Composer/Scripts/lint.sh` (parallel lint). Xdebug port 9004; VS Code launch "Listen for wplokerbjm Theme" maps `/var/www/html/` → `${workspaceFolder:wplokerbjm-root}/wordpress/`.

## WP Hook Engine (Core Pattern)

**Concept**: PHP 8 attributes replace manual `add_action()`/`add_filter()`. `#[Action]`/`#[Filter]` on methods AND properties → scanned by `WPHooksScanner` → compiled into callable plans → lazily registered via `Init` + DI container at boot. Lifecycle: scan → plan → register → fire, with gates, deferral, tags, and compiled caches. Two registries share the model: `WPHooksContainerRegistry` (attribute-discovered services) and `WPHooksInstanceRegistry` (runtime/programmatic hooks; renamed from `WPHooksRuntimeRegistry`).

**Key points**:

- `#[Action(hook, priority?, acceptedArgs?, executeIf?, registerIf?, tag?, deferRegister?, deferRegisterUntilHook?, once?, onUnregisterDo?)]` / `#[Filter(...)]` on public methods and properties
- `hook`: `string|\Closure` (resolved via container, must return string); `tag`: `string|array|\Closure` — items may be `BackedEnum`, all normalized to strings; invalid tag → that hook is logged + skipped
- `registerIf`: registration gate — once for eager hooks, re-evaluated at deferred activation; `executeIf`: fire-time gate (false → action skipped / filter passes value through)
- `deferRegister: true` (container path only) or `deferRegisterUntilHook: '<hook>'` (both paths) → deferred pool; plain `deferRegister` is IGNORED on the runtime/instance path
- Entries: `Registry/HookEntryHandlers.php` (`ContainerRegistryHandlerEntry` / `RuntimeRegistryHandlerEntry`) + `Trait/HandlerEntryTrait` (`register()` / `unregister()` / `isCurrentlyExecuting()`); `Indexers/EntriesIndexer` = O(K) lookup; `Janitor/QueuedHooksCleaner` = queued once-removal
- Caches: scanner output → `cache/WPhooksRegistryContainerCache.php`; runtime instance metadata → `cache/WPHooksInstanceObjectCache.php`; `DTO.php` = `HookKey`, `HookRegistration`, `InstanceHookMetadata`
- `Abstract/ModuleClassHookMetadata` — anonymous self-registering hook hosts (was `AnonClassHookMetadata`); the instance registry auto-unregisters hooks of dead owners
- Static methods and inherited (non-declared) methods are SKIPPED; multi-priority via stacked attributes

**Once-hooks & WP core #61263**: removing a callback while its own priority is dispatching makes `WP_Hook::resort_active_iterations()` skip the next priority whenever a *lower-priority* callback survives. Once-handlers therefore **never unregister mid-dispatch**: `isCurrentlyExecuting()` queues the entry in `QueuedHooksCleaner::$queuedRemovalEntry`, and the cleaner wipes previously-queued handlers as soon as the next invoker runs (remainder outside dispatch) — the consumed flag already prevents re-firing. See https://core.trac.wordpress.org/ticket/61263

**executeIf on deferred hooks**: the gate runs at activation **only** when every parameter is container-resolvable AND none is named like a hook argument (`getHookArgNames()` / `executeIfDependsOnHookArgs()`); otherwise it stays a per-fire guard evaluated at dispatch with the real hook args, so request-scoped params (e.g. `ResolveInfo`, `\WP_Query`) are never fabricated.

**Examples**:

```php
#[Action('template_redirect', 2,
    registerIf: static fn(): bool => self::shouldRegister(),
    executeIf:  static fn(): bool => !self::shouldSkipRedirect() && is_404() && is_singular('lowongan'),
)]
public function oldPost410Redirect(): void { ... }

#[Action('save_post_lowongan', 10, 2)] #[Action('delete_post_lowongan', 10, 1)] // stacked — five hooks total
public function invalidatePostCache(...$args): void { ... }

#[Action('init', registerIf: static fn(): bool => SharedUtils::isWPCLI())] // CLI-only (Core/Wordpress/Cron/WPCron.php)
public function registerCronWP(): void { ... }
```

**Guardrails**: attribute/closure metadata is powerful and easy to abuse. Gates are **decisions only** — keep closures pure, no side effects. Registry tricks (self-unregistration via `unregisterByCallable([$this, __FUNCTION__])`, tags, deferred re-activation) are deliberate tools: use them only toward the intended target, never cleverness that derails from it.

## WPHooks Subsystem Map

```
Support/WPHooks/
├── WPHooksScanner.php · DTO.php        # scan → HookRegistration[] · HookKey/InstanceHookMetadata
├── Abstract/ · Trait/                  # ModuleClassHookMetadata · scanner/provider/deferred/invoker/entry traits
├── Registry/                           # WPHooksContainerRegistry · WPHooksInstanceRegistry · HookEntryHandlers
│   ├── DeferredManager/                # container + instance deferred pools · DeferredHookEntryHandler
│   └── Resolver/                       # ContainerRegistryHookTargetResolver · InstanceRegistryRuntimeHookResolver
├── Invoker/                            # ContainerLazy + Runtime invokers (method/property variants)
├── Janitor/QueuedHooksCleaner.php      # queued once/GC removal (WP #61263)
└── Indexers/EntriesIndexer.php · Utilities/   # O(K) hook/class/tag/callable/namespace · HookPattern · Tag
```

- **Container path**: scan → `HookRegistration` → lazy invoker (DI resolves only on fire). **Runtime path**: `registerHooksOn($object)` — weak owner refs, GC auto-clean, manual `registerAction()/registerFilter()`.
- **Deferred**: `activateDeferredBy{Hook,Class,Namespace,Callable,Tags,TagPattern}`; `unregisterDeferredBy*` mirrors it. Active-pool selectors (`unregisterByCallable()`, …) never sweep deferred entries.

## REST API Pattern

**Concept**: `Transport/REST/Route` classes define endpoints; `Transport/REST/Controllers` handle request logic. Both DI-autowired; routes register via `#[Action('rest_api_init')]`.

**Key points**: success → `new \WP_REST_Response($data, $status)`; failure → `\WP_Error`; `permission_callback` returns `true` or `\WP_Error` (401/403); input via `$request->get_param()` (body/query) and `$request->get_file_params()` (uploads).

**Example** (`Transport/REST/Route/LowonganIngestRoute.php`):

```php
#[Action('rest_api_init', acceptedArgs: 0)]
public function registerRoutes(): void
{
    register_rest_route(self::NAMESPACE, self::ROUTE, [
        'methods'  => 'POST',
        'callback' => fn($request) => $this->controller->ingest($request),
        'permission_callback' => fn($request) => $this->controller->permissionsCheck($request),
    ]);
}
```

## GraphQL Pattern

**Concept**: `GraphQLRegistration` registers the whole WPGraphQL schema; resolver classes fetch data. Hook: `#[Action('graphql_register_types', 0)]`.

**Key points**: `register_graphql_object_type()` (output) / `register_graphql_input_type()` (filters); `register_graphql_field('RootQuery'|'RootMutation', ...)` for queries + mutations; resolvers receive `($root, $args, $context, $info)`; cache via `CacheKey::` prefix + `md5(serialize($args))` → `Cache::get/set($key, ..., 86400)`.

**Example** (`Transport/GraphQL/Registration/GraphQLRegistration.php`):

```php
#[Action('graphql_register_types', 0)]
public function registerTypes(): void
{
    register_graphql_field(self::TYPE_ROOT_QUERY, 'jobGrid', [
        'type' => self::TYPE_JOB_GRID_RESPONSE,
        'args' => ['paged' => ['type' => self::TYPE_INT, 'defaultValue' => 1]],
        'resolve' => fn(...$args) => $this->jobsDataResolver->resolveJobGrid(...$args),
    ]);
}
```

## Naming Conventions

| Type               | Convention                      | Example                                          |
| ------------------ | ------------------------------- | ------------------------------------------------ |
| Files              | PascalCase.php                  | `GlobalHooks.php`, `WPHooksScanner.php`          |
| Namespaces         | `WPLokerBJM\{Layer}\{SubLayer}` | `WPLokerBJM\Controllers\REST`                    |
| Classes            | PascalCase                      | `LowonganIngestController`                       |
| Methods            | camelCase                       | `getHookRegistrations()`, `resolveJobGrid()`     |
| Route classes      | Suffix: `Route`                 | `LowonganIngestRoute`                            |
| Controller classes | Suffix: `Controller`            | `LowonganIngestController`                       |
| Resolver classes   | Suffix: `Resolver`              | `JobsDataResolver`, `TaxonomyResolver`           |
| GraphQL types      | UPPER_SNAKE constants           | `self::TYPE_JOB`, `self::TYPE_JOB_GRID_RESPONSE` |
| Cache keys         | PREFIX + md5 hash               | `CacheKey::JOB_GRID_PREFIX . md5(...)`           |

## Code Standards

- DI container auto-discovers services via `DependencyAutowireScanner` (Reflection-based); services identified by `#[Action]`/`#[Filter]` attributes — no manual hook registration
- Controllers return `\WP_REST_Response` or `\WP_Error`; never die/exit directly
- Resolvers always wrap in try/catch, log errors via `Logger`, return empty/default on failure
- Cache invalidation via `Cache::deleteMultiple()` / `Cache::deletePattern()` with wildcards
- Input sanitization: `sanitize_text_field()`, `wp_kses_post()`, custom `ControllerUtils` methods
- All service classes use constructor-based DI (no `new` for services outside container); exception: anonymous child objects (`AsChildClass` subclasses) receive property injection via `#[Inject]` + `DependencyInjector::injectOn($this)` — a compiled alternative to constructor DI for anonymous-class private state

## InstanceDiscovery / Compiled Property Injection

**Concept**: Anonymous `AsChildClass` objects get compiled property injection via custom `#[Inject]` + `DependencyInjector::injectOn($this)` — reflection runs once at compile; the hot path is reflection-free.

**Key points**:

- `#[Inject]` (`Core/Container/Attributes/AttributesDI.php`): targets property|method|parameter; `name` (string|array|null) + `lazy` (bool); project-owned, invisible to PHP-DI's scanner
- `AsChildClass` (`Core/Container/Support/InstanceDiscovery/Abstract/`): readonly `parentClass` (string|object) + `identifier`; `ModuleClassHookMetadata` extends it
- Callables `[Class::class, 'member']` cover methods AND class properties; `lazy: true` (methods only) injects a first-class callable into a `\Closure`-typed property
- Compile-time validation: assignability, nullable rules, void/never + required-param methods, static/readonly/inherited-private properties, lazy-on-string. Plan cache at `cache/DependencyInjectorCache.php`; eager write; never exports live objects

**Example**: `$injector->injectOn($this)` in an anonymous child constructor; `#[Inject] private ?WPHooksInstanceRegistry $runtimeRegistry;` (typed container entry); `#[Inject([WPGraphQL::class, 'allowedOrigins'], lazy: true)] private \Closure $allowedOrigins;` (lazy first-class callable) — see `Core/Wordpress/Plugins/PluginsManager.php`, `ThirdParty/WPGraphQL.php`.

## Security Requirements

- All REST endpoints require `permission_callback` returning `true` or `\WP_Error`
- Input validated and sanitized at controller boundary (never trust raw `$_POST`/`$_GET`)
- Database queries via `$wpdb->prepare()` or `JobQuery` builder (parameterized)
- JWT auth for protected mutations, resolved via `JWTDataResolver`
- File uploads validated for type/size before processing

## 📂 Codebase References

**Hook System**: `server/Core/Container/Attributes/WPHooksAttributes.php`, `server/Core/Container/Support/WPHooks/WPHooksScanner.php`, `server/Core/Container/Support/WPHooks/DTO.php`, `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php`, `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php`, `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php`, `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php`, `server/Core/Container/Support/WPHooks/Registry/HookEntryHandlers.php`, `server/Core/Container/Support/WPHooks/Registry/DeferredManager/*`, `server/Core/Container/Support/WPHooks/Registry/Resolver/*`, `server/Core/Container/Support/WPHooks/Invoker/*`, `server/Core/Container/Support/WPHooks/Janitor/QueuedHooksCleaner.php`, `server/Core/Container/Support/WPHooks/Indexers/EntriesIndexer.php`, `server/Core/Container/Support/WPHooks/Utilities/*`, `server/Core/Container/Support/WPHooks/Abstract/ModuleClassHookMetadata.php`, `server/Core/Container/Support/WPHooks/Trait/*`, `server/Core/Container/Init.php`, `server/Core/Wordpress/GlobalHooks.php`

**REST**: `server/Transport/REST/Route/LowonganIngestRoute.php`, `server/Transport/REST/Controllers/Ingest/LowonganIngestControllers.php`

**GraphQL**: `server/Transport/GraphQL/Registration/GraphQLRegistration.php`, `server/Transport/GraphQL/Resolvers/Job/JobsDataResolver.php`, `server/Services/GraphQL/GraphQLJobData.php`

**Cache**: `server/Shared/Cache/Cache.php`, `server/Shared/Cache/CacheKey.php`

**DI**: `server/Core/Container/Support/InstanceDiscovery/DependencyAutowireScanner.php`, `server/Core/Container/Definitions/Factories.php`, `server/Core/Container/Attributes/AttributesDI.php`, `server/Core/Container/Support/InstanceDiscovery/DependencyInjector.php`, `server/Core/Container/Support/InstanceDiscovery/Abstract/AsChildClass.php`, `cache/DependencyInjectorCache.php`

**Dev Workflows**: `tools/Composer/Scripts/autoloadwatcher.sh`, `tools/Composer/Scripts/test.sh`, `tools/Composer/Scripts/lint.sh`, `tests/WPHookTests/InitLazyHookTest.php`, `tests/WPHookTests/DeferredHookTest.php`, `tests/WPHookTests/TestTracTicket61263.php`
