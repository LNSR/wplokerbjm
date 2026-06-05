# 🎨 WPLokerBJM Theme

> 🚀 Modern WordPress job board theme built with SvelteKit, TypeScript, and PHP-DI

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![Svelte](https://img.shields.io/badge/Svelte-4A4A55?style=flat-square&logo=svelte&logoColor=FF3E00)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=flat-square&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=flat-square&logo=vite&logoColor=white)

## 📁 Important Theme Files & Folders

1. 🎨 **`style.css`** - Boilerplate WordPress stylesheet for the theme. Contains theme metadata and custom styles, not literally used, we redirect_template to SvelteKit domain.
2. ⚙️ **`server/`** - Directory containing backend PHP code, including custom functions, GraphQL APIs, hooks, and filters.
3. 🖼️ **`src/`** - Directory containing SvelteKit code.
4. 📦 **`/.svelte-kit`** - Directory for SvelteKit build artifacts.
5. 🛠️ **`tools/`** - Directory for development and build tools.
6. 🚀 **`wrangler.jsonc`** - Configuration file for Cloudflare Workers deployment.
7. 📁 **`public/`** - Directory for static assets and public files.

## 🔌 Must-Use Plugin (MU Plugin)

- 📍 **Location**: [`wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php`](../../mu-plugins/wplokerbjm-bootstrap.php)
- 🎯 **Purpose**: Loads the Composer autoloader and initializes the PHP-DI container early in the WordPress lifecycle.
- ⚡ **Benefits**: Ensures hooks, services, and dependencies are registered before regular plugins and themes load, preventing conflicts and ensuring early execution.
- 🚀 **Deployment**: Automatically deployed via GitHub Actions CI/CD pipeline to the remote server.

This MU plugin is crucial for the theme's architecture, as it bootstraps the dependency injection system and custom hooks that power the job board functionality.

## 🔗 Dependency Injection System

This theme uses PHP-DI for dependency injection with automatic class discovery and registration.

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

#### Using Services

```php
use WPLokerBJM\Core\Container;

// Get a service from the container
$container = Container::getContainer();
$jobService = $container->get(JobService::class);
```

### Performance

- **Development**: Classes are scanned on each request for flexibility
- **Production**: Container compilation is enabled with caching for optimal performance
- **APCu**: When available, definition caching is enabled for additional speed

## � Hook Registration

The theme uses attribute-based hook registration for WordPress actions and filters. Instead of manually calling `add_action` or `add_filter`, you can use PHP attributes on your service methods.

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

    #[Filter('the_content', priority: 10, acceptedArgs: 1)]
    public function filterContent(string $content): string
    {
        return $content;
    }
}
```

### How Hook Registration Works

- The `AutowireScanner` scans all autowirable classes for methods with `#[Action]` or `#[Filter]` attributes.
- The `Init` service automatically registers these hooks when the container is initialized.
- Supports both static and instance methods.
- Parameters like priority and accepted_args are configurable via the attribute.

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
> - 🔗 See [Init.php](server/Core/Container/Init.php) and [PHP-DI Container](server/Core/Container.php) for WordPress event-driven hooks implementation
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
