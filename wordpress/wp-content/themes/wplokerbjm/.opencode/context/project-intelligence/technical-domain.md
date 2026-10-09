<!-- Context: project-intelligence/technical | Priority: critical | Version: 3.3 | Updated: 2026-10-07 -->

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

```
Type: Headless WordPress (API-only backend) — Controller + Resolver services → DI → Attribute hooks → WP lifecycle
```

Theme serves only as backend API. Frontend rendering handled by SvelteKit in `../sveltekit/` (same monorepo).

## Project Structure

```
wplokerbjm/
├── server/
│   ├── Core/Container/          # DI container, Init, attribute hooks
│   ├── Controllers/{REST,GraphQL/Resolvers}
│   ├── Services/{REST,GraphQL}
│   ├── Shared/{Cache,Log,Utilities}
│   └── Models/
├── tests/                       # PHPUnit tests (hook tests in tests/WPHookTests/)
├── tools/Composer/Scripts/      # Dev workflow scripts
└── composer.json                # classmap autoload (NOT PSR-4)
```

## Dev Workflows (Critical)

### Autoload Regeneration (Classmap, NOT PSR-4)

Codebase uses **classmap autoloading** (not PSR-4). Every time you add/rename/move a PHP class file, autoload map must be regenerated or WordPress won't find the class.

- **Recommended**: Run VS Code task `php-composer-autoload` in `@wplokerbjm.code-workspace` → `composer run watch:autoload` → `tools/Composer/Scripts/autoloadwatcher.sh` (inotifywait watcher, 2s debounce, auto-runs `composer dump-autoload --apcu -a -o`, then `docker exec wordpress-${WP_ENV} ... litespeed-purge all` for HMR)

### Testing

- **Run tests**: `composer run test` → `tools/Composer/Scripts/test.sh` → loads `../../../../.env`, then `docker exec wordpress-${WP_ENV:-production} sh -c 'cd /var/www/html/wp-content/themes/wplokerbjm && ./vendor/bin/phpunit --colors=always --fail-on-skipped --testdox'`
- **Hook tests**: live in `tests/WPHookTests/` (InitLazyHookTest, OnceHookTest, OnceChainTest, DeferredHookTest, DeferRegisterUntilHookTest, RuntimeRegistryTest, PropertyHookTest, TaggedHookTest, ExecuteIfHookTest, RegisterIfHookTest, …) plus `TestTracTicket61263.php`, which pins WP core's `resort_active_iterations()` skip. Top-level tests: `CacheTest.php`, `ContainerDefinitionsTest.php`, `DependencyInjectorTest.php`, `LowonganIngestRestTest.php` (fixtures in `tests/Support/Fixtures/`)
- **Engine**: `tests/bootstrap.php` loads the **real WordPress hook engine** (`wp-includes/plugin.php`, guarded by `function_exists('add_action')`), so `add_action`/`do_action`/`remove_action`/`doing_action`/`WP_Hook` are genuine core; `WplokerbjmTestCase` resets the four hook globals (`$wp_filter`, `$wp_actions`, `$wp_filters`, `$wp_current_filter`) per test. brain/monkey remains only for non-hook WP functions (`get_stylesheet`, `is_admin`, `wp_cache_*`, …)

### Linting & Xdebug

- **Lint**: `composer run lint:php` → `tools/Composer/Scripts/lint.sh` → PHP parallel lint
- **Xdebug**: Port 9004; VS Code launch "Listen for wplokerbjm Theme" maps `/var/www/html/` → `${workspaceFolder:wplokerbjm-root}/wordpress/`

## WP Hook Engine (Core Pattern)

**Concept**: PHP 8 attributes replace manual `add_action()`/`add_filter()`. `#[Action]`/`#[Filter]` on methods AND properties → scanned by `WPHooksScanner` → compiled into callable plans → lazily registered via `Init` + DI container at boot. Lifecycle: scan → plan → register → fire, with gates, deferral, tags, and compiled caches. Two registries share the model: `WPHooksContainerRegistry` (attribute-discovered services) and `WPHooksInstanceRegistry` (runtime/programmatic hooks; renamed from `WPHooksRuntimeRegistry`).

**Key points**:

- `#[Action(hook, priority?, acceptedArgs?, defer?, executeIf?, registerIf?, tags?)]` / `#[Filter(...)]` on public methods and properties
- `hook`: `string` | `\Closure` — dynamic names resolved via container (must return string)
- `tags`: `string` | `\BackedEnum` | `\Closure` — normalized to string (enum → value); invalid values logged + registration skipped
- `registerIf`: registration gate, re-evaluated at deferred activation; `executeIf`: fire-time gate (false → hook skipped entirely, even deferred)
- `defer: true` / `deferRegisterUntilHook: '<hook>'` → activated by `activateDeferredByHook/Class/Callable/Tags/Namespace`; removal via `unregisterByHook/Class/Callable/Tags/Namespace` (+ `unregisterDeferredBy*`) — class/namespace ops drop inactive plugins at `plugins_loaded`
- Registries pair entry objects (`Registry/HookEntryHandlers.php` → `ContainerRegistryHandlerEntry` / `RuntimeRegistryHandlerEntry`) with `Trait/HandlerEntryTrait` (`register()` / `unregister()` / `stillDispatchingSameHook()`); `Indexers/EntriesIndexer` gives O(K) key lookup; `DeferredHookManager` + `Trait/DeferredHooksTrait` own the deferred pools
- Caches: scanner output → `cache/WPhooksRegistryContainerCache.php` (VarExporter, precomputed plans, reflection-free hot path); runtime instance metadata → `cache/WPHooksInstanceObjectCache.php` (class `WPHooksInstanceObjectCache`, `__set_state` object literals). `DTO.php`: `HookKey`, `HookRegistration`, `InstanceHookMetadata` (was `RuntimeHookMetadata`), `DeferredHookEntryDTO`
- Attribute closures may reference `self` = the anonymous `ModuleClassHookMetadata` instance (`Abstract/ModuleClassHookMetadata.php`, was `AnonClassHookMetadata`); the instance registry auto-unregisters dead-owner hooks
- Static methods skipped — instance methods only (DI container); multi-priority + inherited attributes supported

**Once-hooks & WP core #61263**: removing a callback while its own priority is dispatching makes `WP_Hook::resort_active_iterations()` skip the next priority whenever a *lower-priority* callback survives. Once-handlers therefore **never unregister mid-dispatch**: `stillDispatchingSameHook()` moves the entry into `queuedRemovalEntry`, flushed on a later removal outside dispatch — keep this deferral (the consumed flag already prevents re-firing). See https://core.trac.wordpress.org/ticket/61263

**executeIf on deferred hooks**: the gate runs at activation **only** when every parameter is container-resolvable AND none is named like a hook argument (`getHookArgNames()` / `executeIfDependsOnHookArgs()`); otherwise it stays a per-fire guard evaluated at dispatch with the real hook args, so request-scoped params (e.g. `ResolveInfo`, `\WP_Query`) are never fabricated.

**Examples**:

```php
// Decision-only gates: registerIf + executeIf (server/Core/GlobalHooks.php)
#[Action('template_redirect', 2,
    registerIf: static function (): bool { return self::shouldRegister(); },
    executeIf:  static function (): bool { return !self::shouldSkipRedirect() && is_404() && is_singular('lowongan'); },
)]
public function oldPost410Redirect(): void { ... }

#[Action('save_post_lowongan', 10, 2)] #[Action('delete_post_lowongan', 10, 1)] // stacked — one method, five hooks (CacheInvalidationHooks)
public function invalidatePostCache(...$args): void { ... }

// CLI-gated registration (server/Core/Cron/WPCron.php); third-party gating via PluginConfigInterface (Rankmath.php)
#[Action('init', registerIf: static fn(): bool => SharedUtils::isWPCLI())]
public function registerCronWP(): void { ... }
```

**Guardrails**: attribute/closure metadata is powerful and easy to abuse. Gates are **decisions only** — keep closures pure, no side effects. Registry tricks (self-unregistration via `unregisterByCallable([$this, __FUNCTION__])`, tags, deferred re-activation) are deliberate tools: use them only toward the intended target, never cleverness that derails from it.

## REST API Pattern

**Concept**: Route classes define endpoints; Controller classes handle request logic. Both DI-autowired. Routes register via `#[Action('rest_api_init')]`.

**Key points**:

- Route classes in `Services/REST/` — define namespace, route, HTTP methods
- Controller classes in `Controllers/REST/` — validation, business logic, responses
- Responses: `new \WP_REST_Response($data, $status_code)` for success; same with error for failures
- Permission callbacks: return `true` or `new \WP_Error(...)` with 401/403
- Input: `$request->get_param()` for body/query; `$request->get_file_params()` for uploads

**Example** (`Services/REST/LowonganIngestRoute.php`):

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

**Concept**: Single `GraphQLRegistration` service registers entire WPGraphQL schema. Resolver classes handle data fetching. Hook: `#[Action('graphql_register_types', 0)]`.

**Key points**:

- `register_graphql_object_type()` — output types (Job, JobSummary, CarouselResponse)
- `register_graphql_input_type()` — input filters (SortOptionInput, JobFiltersInput)
- `register_graphql_field('RootQuery', 'fieldName', [...])` / `('RootMutation', ...)` — queries + mutations
- Resolvers receive `($root, $args, $context, $info)` — standard WPGraphQL signature
- Caching: `CacheKey::` prefix + `md5(serialize($args))` → `Cache::get/set($key, ..., 86400)`

**Example** (`Services/GraphQL/GraphQLRegistration.php`):

```php
#[Action('graphql_register_types', 0)]
public function registerTypes(): void
{
    // register_graphql_object_type(self::TYPE_JOB, [...]) — output types

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

**Concept**: Anonymous child objects extending `AsChildClass` get compiled property injection via custom `#[Inject]` + `DependencyInjector::injectOn($this)` — a performance-oriented alternative to constructor DI for anonymous-class private state. Reflection runs once at compile; the hot path is reflection-free.

**Key points**:

- Custom `#[Inject]` (`server/Core/Container/Attributes/AttributesDI.php`): targets property|method|parameter; params `name` (string|array|null) + `lazy` (bool); project-owned, invisible to PHP-DI's scanner
- `AsChildClass` base (`Support/InstanceDiscovery/Abstract/AsChildClass.php`): readonly `parentClass` (string|object) + `identifier`; `getParentClass()` normalizes object parents; `ModuleClassHookMetadata` extends it
- `injectOn(AsChildClass $target)`: validates anonymous target, resolves plan from compiled cache, assigns via scope-bound setter (private/protected child properties writable)
- Array-callable entries `[Class::class, 'member']` support methods AND class field properties (`kind: method|property`) — closures/anon classes stored in class properties are injectable
- `lazy: true` (method callables only): injects a first-class callable closure; target property must be `\Closure`-typed
- Compile-time validation: named/union/intersection assignability, nullable rules, void/never + required-param methods rejected, static/readonly/inherited-private properties rejected, lazy-on-string rejected
- Compiled cache: Brick VarExporter plan cache at theme `cache/DependencyInjectorCache.php`; eager write on compile; never exports live container/object references

**Example** (`server/Core/Plugins/PluginsManager.php` + `server/Core/Plugins/ThirdParty/WPGraphQL.php`):

```php
$injector->injectOn($this); // anonymous child constructor

#[Inject] private ?WPHooksInstanceRegistry $runtimeRegistry; // typed entry via container

#[Inject([WPGraphQL::class, 'allowedOrigins'], lazy: true)]
private \Closure $allowedOrigins; // lazy first-class callable
```

## Security Requirements

- All REST endpoints require `permission_callback` returning `true` or `\WP_Error`
- Input validated and sanitized at controller boundary (never trust raw `$_POST`/`$_GET`)
- Database queries via `$wpdb->prepare()` or `JobQuery` builder (parameterized)
- JWT auth for protected mutations, resolved via `JWTDataResolver`
- File uploads validated for type/size before processing

## 📂 Codebase References

**Hook System**: `server/Core/Container/Attributes/WPHooksAttributes.php`, `server/Core/Container/Support/WPHooks/WPHooksScanner.php`, `server/Core/Container/Support/WPHooks/DTO.php`, `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php`, `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php`, `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php`, `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php`, `server/Core/Container/Support/WPHooks/Registry/HookEntryHandlers.php`, `server/Core/Container/Support/WPHooks/Indexers/EntriesIndexer.php`, `server/Core/Container/Support/WPHooks/Abstract/ModuleClassHookMetadata.php`, `server/Core/Container/Support/WPHooks/Trait/*`, `server/Core/Container/Init.php`, `server/Core/GlobalHooks.php`

**REST**: `server/Services/REST/LowonganIngestRoute.php`, `server/Controllers/REST/LowonganIngestController.php`

**GraphQL**: `server/Services/GraphQL/GraphQLRegistration.php`, `server/Controllers/GraphQL/Resolvers/JobsDataResolver.php`, `server/Services/GraphQL/GraphQLJobData.php`

**Cache**: `server/Shared/Cache/Cache.php`, `server/Shared/Cache/CacheKey.php`

**DI**: `server/Core/Container/Support/InstanceDiscovery/DependencyAutowireScanner.php`, `server/Core/Container/Definitions/Factories.php`, `server/Core/Container/Attributes/AttributesDI.php`, `server/Core/Container/Support/InstanceDiscovery/DependencyInjector.php`, `server/Core/Container/Support/InstanceDiscovery/Abstract/AsChildClass.php`, `cache/DependencyInjectorCache.php`

**Dev Workflows**: `tools/Composer/Scripts/autoloadwatcher.sh`, `tools/Composer/Scripts/test.sh`, `tools/Composer/Scripts/lint.sh`, `tests/WPHookTests/InitLazyHookTest.php`, `tests/WPHookTests/DeferredHookTest.php`, `tests/WPHookTests/TestTracTicket61263.php`

## Related Files

- `business-domain.md` — Job board business context
- `decisions-log.md` — Architecture decisions (DI container choice, headless approach)
- `business-tech-bridge.md` — Business requirements → technical implementation mapping
- `living-notes.md` — Ongoing technical notes
