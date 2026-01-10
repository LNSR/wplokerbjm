# 🎨 WPLokerBJM Theme

> 🚀 Modern WordPress job portal theme built with Svelte, TypeScript, and PHP-DI

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![Svelte](https://img.shields.io/badge/Svelte-4A4A55?style=flat-square&logo=svelte&logoColor=FF3E00)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=flat-square&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=flat-square&logo=vite&logoColor=white)

## 📁 Important Theme Files & Folders

2. 🎨 **`style.css`** - Boilerplate WordPress stylesheet for the theme. Contains theme metadata and custom styles.
3. ⚙️ **`server/`** - Directory containing backend PHP code, including custom functions, REST APIs, hooks, and filters.
4. 🖼️ **`src/`** - Directory containing Svelte components and all client-side code that enhances the user interface.
5. 📦 **`assets/`** - Directory for static assets like images, fonts, static site generation, and compiled CSS/JS files.
6. 🛠️ **`tools/`** - Directory for development and build tools.

## 🔌 Must-Use Plugin (MU Plugin)

- 📍 **Location**: [`wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php`](../../mu-plugins/wplokerbjm-bootstrap.php)
- 🎯 **Purpose**: Loads the Composer autoloader and initializes the PHP-DI container early in the WordPress lifecycle.
- ⚡ **Benefits**: Ensures hooks, services, and dependencies are registered before regular plugins and themes load, preventing conflicts and ensuring early execution.
- 🚀 **Deployment**: Automatically deployed via GitHub Actions CI/CD pipeline to the remote server.

This MU plugin is crucial for the theme's architecture, as it bootstraps the dependency injection system and custom hooks that power the job portal functionality.

## 📄 Theme Routing

The theme uses a single router file [`index.php`](index.php) that routes requests to appropriate view classes based on WordPress conditions.

| Page          | View Class             | Description                         | Status    |
| ------------- | ---------------------- | ----------------------------------- | --------- |
| 🏠 Homepage   | `HomepageView`         | Main landing page with job listings | ✅ Active |
| 💼 Job Detail | `SingleLowonganView`   | Individual job posting page         | ✅ Active |
| 📝 Post Job   | `PasangIklanLokerView` | Job posting submission form         | ✅ Active |

## 🔧 Automation Tools

- 🏗️ **[SSG](tools/SSG/docs/README.md)** — Static Site Generation via GitHub Actions pipeline
  - ⚡ Automated builds on content changes
  - 🚀 Performance optimization
  - 📊 SEO improvements

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

Manual definitions are placed in `server/Core/Definitions/` and take precedence over auto-scanned definitions.

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
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Filter;

class MyService
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

### How It Works

- The `AutowireScanner` scans all autowirable classes for methods with `#[Action]` or `#[Filter]` attributes.
- The `Init` service automatically registers these hooks when the container is initialized.
- Supports both static and instance methods.
- Parameters like priority and accepted_args are configurable via the attribute.

This approach keeps hook registration declarative and centralized.

## �📝 Development Notes

> 💡 **Architecture Tips**
>
> - 🔗 See [Init.php](server/Core/Container/Init.php) and [PHP-DI Container](server/Core/Container.php) for WordPress event-driven hooks implementation
> - 🔗 See the **Dependency Injection System** section above for details on automatic class discovery and registration
> - 🎨 [Assets](assets) are shared between backend and frontend - Tailwind scans both PHP and Svelte source files
> - ⚡ All frontend rendering happens in `<body>` (CSR) while `<head>` contains server-side data

## 📋 Mini Kanban Table

| 📥 BACKLOG                                             | 📋 TODO | 🚧 IN PROGRESS | ✅ COMPLETED                           |
| ------------------------------------------------------ | ------- | -------------- | -------------------------------------- |
|                                                        |         |                | ✅ Migrate to Svelte for most frontend |
| 🚀 Migrate to SvelteKit and deploy to Vercel/CF Worker |         |                | ✅ Fully CSR `<body>`                  |
| 🗺️ Add Job Fair Page (map & event details)             |         |                | ✅ Implement SSG via GitHub Actions    |
|                                                        |         |                | ✅ Client side bookmark system         |
