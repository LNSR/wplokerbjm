````markdown
# 🎨 WPLokerBJM ~~Theme~~ Server

> 🚀 Backend PHP for Modern WordPress job board theme built with SvelteKit, TypeScript, and PHP-DI

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-2B2737?style=flat-square&logo=composer&logoColor=white)
![PHPUnit](https://img.shields.io/badge/PHPUnit-777BB4?style=flat-square&logo=phpunit&logoColor=white)

## 📁 Important Theme Files & Folders

1. 🎨 **`style.css`** - Boilerplate WordPress stylesheet for the theme. Contains theme metadata and custom styles, not literally used, we redirect_template to SvelteKit domain.
2. ⚙️ **`server/`** - Directory containing backend PHP code, including custom functions, GraphQL APIs, hooks, and filters.
3. 🛠️ **`tools/`** - Directory for development and build tools.

## 🔌 Must-Use Plugin (MU Plugin)

- 📍 **Location**: [`wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php`](../../mu-plugins/wplokerbjm-bootstrap.php)
- 🎯 **Purpose**: Loads the Composer autoloader and initializes the PHP-DI container early in the WordPress lifecycle.
- ⚡ **Benefits**: Ensures hooks, services, and dependencies are registered before regular plugins and themes load, preventing conflicts and ensuring early execution.
- 🚀 **Deployment**: Automatically deployed via GitHub Actions CI/CD pipeline to the remote server.

This MU plugin is crucial for the ~~theme's~~ **server's** architecture, as it bootstraps the dependency injection system and custom hooks that power the job board functionality.

## 🔗 Dependency Injection System

This ~~theme~~ **server** uses PHP-DI for dependency injection with automatic class discovery and registration.

### How It Works

The DI container automatically scans the `server/` directory recursively and registers all suitable classes for autowiring. This eliminates the need to manually register every class while still allowing for custom configurations when needed.

### Automatic Registration

The `AutowireScanner` class automatically discovers and registers classes that are:

- ✅ **Concrete classes** with public constructors
- ✅ **Regular service classes** (repositories, services, controllers, etc.)
- ✅ **Classes with dependencies** that can be autowired

### Automatically Skipped

The scanner intelligently skips classes that shouldn't be autowired:

- ❌ **Interfaces** - Cannot be instantiated
- ❌ **Abstract classes** - Cannot be instantiated
- ❌ **Final classes** - Cannot be extended or proxied
- ❌ **Static-only classes** - Don't need instances (utility classes, etc.)
- ❌ **Traits** - Cannot be instantiated
- ❌ **Classes with non-public constructors** - Cannot be autowired
- ❌ **The AutowireScanner class itself** - To avoid circular dependencies

### Manual Definitions

You can still create manual definitions for special cases:

1. **Interface bindings** - When you need to bind interfaces to implementations
2. **Complex factory patterns** - When simple autowiring isn't sufficient
3. **Service arrays** - When you need to pass arrays of services (like the `Init` class)
4. **Singleton patterns** - When you need specific instantiation logic

Manual definitions are placed in `server/Core/Container/Definitions/` and take precedence over auto-scanned definitions.

### Usage Examples

#### Simple Class (Auto-registered)

```php
namespace WPLokerBJM\Services;

class JobService
{
    public function __construct(
        private JobRepository $jobRepo,
        private TaxonomyService $taxService
    ) {}

    // ... methods
}
```
````

#### Using Services

```php
use WPLokerBJM\Core\Container;

// Get a service from the container
$container = Container::getContainer();
$jobService =$container->get(JobService::class);

```

### Performance

- **Development**: Classes are scanned on each request for flexibility
- **Production**: Container compilation is enabled with caching for optimal performance
- **APCu**: When available, definition caching is enabled for additional speed

## 🔌 Hook Registration

The server uses attribute-based hook registration for WordPress actions and filters. Instead of manually calling `add_action` or `add_filter`, you can use PHP attributes on your service methods. The attribute classes live in `server/Core/Container/Attributes/WPHooksAttributes.php`.

### Using Attributes

```php
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

class MyClassName
{
    #[Action('wp_enqueue_scripts')]
    public function enqueueScripts(): void
    {
        // Your code here
    }

    /**
     * normal attribute wordpress position
     */
    #[Filter(hook: 'the_content', priority: 10, acceptedArgs: 1)]
    public function filterContent(string $content): string
    {
        return $content;
    }
}

```

#### Attribute Surface

Every attribute parameter maps to a WordPress registration concern:

| Parameter                | Type       | Purpose                                                                             |
| ------------------------ | ---------- | ----------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `priority`               | `int`      | Execution order (default `10`)                                                      |
| `acceptedArgs`           | `int`      | Number of arguments passed to the callable (default `1`)                            |
| `once`                   | `bool`     | Self-removes after the first evaluation, whatever the gate result                   |
| `deferRegister`          | `bool`     | Skips registration by default; opt-in via `activateDeferredBy*()`                   |
| `deferRegisterUntilHook` | `string`   | Implies deferral; auto-activates when the trigger hook fires (or has already fired) |
| `registerIf`             | `callable` | Registration-time gate, evaluated once before the hook enters a pool                |
| `executeIf`              | `callable` | Per-fire gate, evaluated on every `do_action` / `apply_filters`                     |
| `tag`                    | `array     | callable`                                                                           | Static list or callable returning tags; powers the selector APIs |

#### Minimal Examples

##### `registerIf` — register only when the environment matches

```php
#[Filter('posts_search', registerIf: static function (): bool {
    return \defined('GRAPHQL_REQUEST') && !is_admin() && !SharedUtils::isWPCLI();
})]
public function graphqlOnly(string $search, \WP_Query$wp_query): string
{
    return $search;
}

```

##### `executeIf` — gate every single fire

```php
#[Filter('posts_search', executeIf: static function (\WP_Query $q): bool {
    return in_array(PostTypes::POST_TYPE_LOWONGAN, (array) $q->get('post_type'), true);
})]
public function lowonganOnly(string $search, \WP_Query$wp_query): string
{
    return $search;
}

```

##### `once` — fire once per request, then unregister itself

```php
#[Action('wp_footer', once: true)]
public function injectOncePerRequest(): void
{
    // removed after the first evaluation, regardless of gate executeIf results
}

```

##### `deferRegister` — opt in manually when the time is right

```php
#[Filter('posts_search', deferRegister: true, tag: ['lowongan'])]
public function optInSearch(string $search, \WP_Query$wp_query): string
{
    return $search;
}

```

```php
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;

class SearchActivator
{
    public function __construct(
        private WPHooksContainerRegistry $hooksRegistry,
    ) {}

    // Usually called from inside another hook's callback:
    public function activate(): void
    {
        $this->hooksRegistry->activateDeferredByTags(['lowongan']);
    }
}

```

##### `deferRegisterUntilHook` — auto-activate when the trigger fires

```php
#[Filter('posts_search', 10, 2, deferRegisterUntilHook: 'init_graphql_request')]
public function graphqlSearch(string $search, \WP_Query$wp_query): string
{
    // equivalent to add_action('init_graphql_request', fn () => add_filter('posts_search', ...))
}

```

##### `tag` + selectors — group hooks, unregister by tag or pattern

```php
// Inside a service with the registry autowired:
$this->hooksRegistry->unregisterByTags(['lowongan']);       // exact tag match$this->hooksRegistry->unregisterByHookPattern('posts_*');   // wildcard (prefix ≥ 2 chars)

```

#### Complex Hook Example

````php
class SearchHooks
{
    /**
     * @param string        $search   The current search SQL fragment (may be empty).
     * @param \WP_Query     $wp_query The WP_Query object being executed.
     * @return string Modified search SQL fragment.
     */
    #[Filter('posts_search', 10, 2,
        /**
         * only run once per each request
         * any eval result from executeIf will make hook unregister itself
         * if used with 'deferRegister' it's subject to the deferral mechanisms
         */
        once: true,
        /**
         * register hook inside init_graphql_request lifecycle
         * roughly just like
         * ```php
         * add_action('init_graphql_request', static function () {
         *     add_filter('posts_search', [SearchHooks::class, 'jobPostsSearchFilterImpl']);
         * });
         * ```
         */
        deferRegisterUntilHook: 'init_graphql_request',
        /**
         * register hook if condition is true
         */
        registerIf: static function (): bool {
            if (!\defined('GRAPHQL_REQUEST')) {
                return false;
            }
            return !is_admin() && !SharedUtils::isWPCLI();
        },
        /**
         * runtime hook evaluation, during do_action/apply_filters is invoked
         * Hook Engine invoker will decide whether allowed to execute exact callable if true
         * any closures param will be resolved by DI or incoming params from executeIf
         *
         * Regarding Closure params:
         * since hook `posts_search` will accept `string $search, \WP_Query $wp_query` params
         * any closures param will be resolved by DI(Typehint class), or incoming params from hook itself ($wp_query) must match parameter name!
         *
         */
        executeIf: static function (\WP_Query $wp_query): bool {
            $postTypes = (array) ($wp_query->get('post_type') ?: []);
            return in_array(PostTypes::POST_TYPE_LOWONGAN, $postTypes, true);
        }
    )]
    public function jobPostsSearchFilterImpl(string $search, \WP_Query$wp_query): string
    {
        global $wpdb;
        $q = (string) ($wp_query->query_vars['s'] ?? '');
        if ($wpdb === null \vert{}\vert{}$q === '') {
            return $search;
        }

        return JobQuery::buildPostsSearchSql($wpdb,$q);
    }
}

class ThemeProp
{
    /**
     * Provide theme runtime data for frontend side.
     * @return ThemeData
     */
    #[Filter(
        /**
         * dynamic hook name/tag orchestration and multiple service tags orchestration
         * any closures param will be resolved by DI or incoming params from hook
         */
        hook: static function (): string {
            return \get_stylesheet() . '_graphql_theme_data';
        },
        tag: static function (InternalService $s, ServiceThirdParty $s2): array {
            $array =$s->arraySomething();
            $array2 =$s2->arraySomething();
            return [...$array, ...$array2];
        }
    )]
    public function themeData(): array
    {
        $loggedIn = is_user_logged_in();
        // For logged-in users store per-user caches to avoid leaking any per-user secrets (nonces)
        $cacheKey =$loggedIn
            ? CacheKey::THEME_DATA . '_user_' . (int) get_current_user_id()
            : CacheKey::THEME_DATA . '_anonymous';
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            if ($loggedIn) {$cached['wpRestNonce'] = wp_create_nonce('wp_rest');
            } else {
                // safety remove in case cached from logged-in
                if (isset($cached['wpRestNonce'])) {
                    unset($cached['wpRestNonce']);
                }
            }
            return $cached;
        }


        $logoData =$this->getLogoData();
        if (empty($logoData['sizes'])) {$logoData['sizes'] = '(max-width: 640px) 48px, (max-width: 1024px) 64px, 128px';
        }

        // compute optional site icon <link> tags using the same filter used in addSiteIconMetaTags()
        $siteIconTags = '';$tags = apply_filters('site_icon_meta_tags', []);
        if (!empty($tags) && is_array($tags)) {
            $siteIconTags = implode("\n", $tags);
        }

        $wpThemeData = [
            'logo' => [
                'logoUrl' => $logoData['url'] ?? '',
                'logoSrcset' => $logoData['srcset'] ?? '',
                'logoSizes' => $logoData['sizes'] ?? '',
                'logoDecoding' => 'async',
                'logoWidth' => intval($logoData['width'] ?? 0),
                'logoHeight' => intval($logoData['height'] ?? 0),             ],             'siteIconTags' =>$siteIconTags,
        ];

        if ($loggedIn) {$wpThemeData['wpRestNonce'] = wp_create_nonce('wp_rest');
        }

        Cache::set($cacheKey,$wpThemeData, 86400); // Cache for 1 day

        return $wpThemeData;
    }
}

````

### How Hook Registration Works

- **Two registration paths.** Container path: the `AutowireScanner` finds `#[Action]` / `#[Filter]` methods, and the `Init` service registers them as lazy `ContainerLazyHookHandler`s when the container initializes — the service is resolved from the DI container only when the hook actually fires. Runtime path: `WPHooksRuntimeRegistry::registerHooksOn($object)` registers the hooks of an already-instantiated object immediately (attribute closures resolved via `RuntimeWPHookProvider`, static closures required).
- **Gates.** `registerIf` runs once at registration time; `executeIf` runs on every fire — when it fails, filters pass the original value through unchanged and actions return `null`.
- **Deferred pool.** `deferRegister` hooks skip registration until activated via `activateDeferredByHook()` / `ByClass()` / `ByNamespace()` / `ByCallable()` / `ByTags()`; `deferRegisterUntilHook` auto-activates when its trigger hook fires.
- **Lifecycle.** `once` hooks self-remove after the first evaluation; `unregisterByCallable()` / `ByHook()` / `ByClass()` / `ByNamespace()` / `ByTags()` plus wildcard `unregisterByHookPattern()` / `unregisterByTagPattern()` tear hooks down.
- Supports both static and instance methods; priority and accepted args are configurable via the attribute.

This approach keeps hook registration declarative and centralized.

## 🧪 Testing (PHPUnit)

The theme includes a PHPUnit test suite for backend PHP services, container definitions, GraphQL behavior, caching, and REST ingest flows. Tests are designed to run without loading a full WordPress installation by mocking WordPress functions where needed.

### How Tests Work

- **PHPUnit** runs the test suite defined in `phpunit.xml`.
- **`tests/bootstrap.php`** loads shared test support before PHPUnit executes tests.
- **Brain Monkey** mocks WordPress functions such as `register_rest_route`, `wp_cache_get`, and remote response helpers.
- **Patchwork** is loaded for function patching support used by Brain Monkey.
- **`tests/Support/WplokerbjmTestCase.php`** is the base test case. It boots the proxy container, resets per-test state, initializes Brain Monkey in `setUp()`, and tears mocks down after each test.
- **`tests/Support/ProxyContainer.php`** provides the test container layer used by tests that need PHP-DI services.

### Running Tests

From the theme directory, run:

```bash
composer test

```

The Composer script loads the root `.env`, then executes PHPUnit inside the WordPress Docker container:

```bash
docker exec wordpress-${WP_ENV:-production} sh -c "cd /var/www/html/wp-content/themes/wplokerbjm \
&& ./vendor/bin/phpunit --colors=always --fail-on-skipped --testdox"

```

If you are already inside the container, run PHPUnit directly:

```bash
./vendor/bin/phpunit --colors=always --fail-on-skipped --testdox

```

### Test Files

- `tests/CacheTest.php` — cache behavior and WordPress cache mocks.
- `tests/ContainerDefinitionsTest.php` — PHP-DI container definition coverage.
- `tests/GraphQLTest.php` — GraphQL query and response behavior.
- `tests/LowonganIngestOptionsRestTest.php` — ingest options REST endpoint behavior.
- `tests/LowonganIngestRestTest.php` — lowongan ingest REST endpoint behavior.

## 📝 Development Notes

> 💡 **Architecture Tips**
>
> - 🔗 See [Init.php](https://www.google.com/search?q=server/Core/Container/Init.php) and [PHP-DI Container](https://www.google.com/search?q=server/Core/Container.php) for WordPress event-driven hooks implementation
> - 🔗 See the **Dependency Injection System** section above for details on automatic class discovery and registration

## 📋 Mini Kanban Table

| 📥 BACKLOG                                 | 📋 TODO | 🚧 IN PROGRESS | ✅ COMPLETED                                                           |
| ------------------------------------------ | ------- | -------------- | ---------------------------------------------------------------------- |
| 🗺️ Add Job Fair Page (map & event details) |         |                | ✅ Migrate from AlpineJS to Vue                                        |
|                                            |         |                | ✅ Migrate from Vue to bare theme and Svelte for frontend `<body>` CSR |
|                                            |         |                | ✅ Playwright Github Action microservice for SSG                       |
|                                            |         |                | ✅ Client side bookmark system                                         |
|                                            |         |                | ✅ Add SkeletonHTML for SEO efficiency                                 |
|                                            |         |                | ✅ Virtualization listing for better performance                       |
|                                            |         |                | ✅ Migrated to SvelteKit                                               |

```

```
