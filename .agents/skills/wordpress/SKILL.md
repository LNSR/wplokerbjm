---
name: wordpress
description: How to use custom WordPress Hook Attributes in WPLokerBJM
---

# WordPress Custom Hook Attributes

This project uses PHP Attributes to register WordPress hooks (`add_action`, `add_filter`) automatically, avoiding boilerplate hook registration calls and keeping registrations close to their implementation.

## How it Works

The theme provides two custom PHP attributes: `#[Action]` and `#[Filter]`.
These are defined in `WPLokerBJM\Core\Container\Attributes\WPHooksAttributes.php`.

When the application bootstraps (`mu-plugins/wplokerbjm-bootstrap.php`), the DI container is built using `WPLokerBJM\Core\Container\Container`.
Container definitions are assembled from:

- `WPLokerBJM\Core\Container\Definitions\Core` (manual definitions, including the `Init` service)
- `WPLokerBJM\Core\Container\Definitions\AutoScanned` (auto-discovered services)

### Hook discovery & registration flow

1. **Scan for autowirable services**
   - `WPLokerBJM\Core\Container\Support\AutowireScanner` recursively walks `server/` (excluding `vendor`, `tests`, `node_modules`, etc.) and finds all PHP classes that are:
     - concrete (not interface/abstract/trait/final)
     - not static-only (must have instance methods or properties)
     - have a public constructor (or no constructor)
     - not attribute classes themselves
   - It generates DI container definitions via `DI\autowire()` for those classes.

2. **Scan for hook attribute registrations**
   - The same scanner also inspects methods on each class for `#[Action]` and `#[Filter]` attributes.
   - It builds a list of hook registrations containing: class, method, hook name, priority, accepted args, and whether the method is static.

3. **Init registers hooks**
   - `WPLokerBJM\Core\Container\Init` receives the hook registration list and the container reference.
   - For each registration:
     - If the method is static, it registers the hook directly against `Class::method`.
     - If the method is an instance method, it resolves the class from the DI container (or uses an already instantiated service) and registers the hook.

4. **Cache**
   - Hook registrations and autowirable definitions are cached (APCu preferred, Redis fallback) to avoid repeated scanning.
   - Caches are invalidated automatically when files change (directory mtime / compiled container hash changes).

## Where to Put Your Hooked Classes

✅ Place new services under `server/` inside the `WPLokerBJM` namespace.

The scanner expects the class to be discoverable by its namespace and file contents. If the class is in `server/`, uses the correct namespace, and is autowirable, its hook attributes will be detected automatically.

## Adding a New Hook

To attach a method to a WordPress action or filter, simply add the corresponding attribute to your method. This works for both **instance methods** and **static methods**.

### 1. Import the Attributes

At the top of your class file, make sure to import the required attribute:

```php
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
```

### 2. Annotate Your Method

Decorate your method with the attribute. The attributes accept up to three parameters mapping directly to WordPress hook arguments:
1. `$hook` (string): The exact name of the WordPress hook.
2. `$priority` (int, default: `10`): The priority/execution order.
3. `$acceptedArgs` (int, default: `1`): The number of arguments the method expects.

**Example: Action Hook**
```php
#[Action('template_redirect', 10, 1)]
public function handleTemplateRedirect(): void
{
    // Your action logic here
}
```

**Example: Filter Hook**
```php
#[Filter('posts_search', 10, 2)]
public function modifyJobSearch(string $search, \WP_Query $wp_query): string
{
    // Your filter logic here
    return $search;
}
```

**Example: Multiple Hooks on the Same Method**
Because the attributes are mapped with `Attribute::IS_REPEATABLE`, you can register the exact same method to multiple hooks:
```php
#[Action('save_post', 10, 2)]
#[Action('delete_post', 10, 1)]
#[Action('trashed_post', 10, 1)]
public function purgeCacheOnChange(...$args): void
{
    // This method will be called for save_post, delete_post, and trashed_post
}
```

## Requirements & Nuances

### Make sure your class is autowirable
If your class is not autowirable, it will not appear in the scan and its hook attributes won't be registered.

A class is autowirable if:
- It exists and is loadable (`class_exists()` passes).
- It is a concrete class (not interface, abstract, trait, or final).
- It has a public constructor (or no constructor).
- It is not a static-only utility class (must have at least one non-static method or property).
- It is not itself an attribute class.

### Where hooks are registered

Hook registration happens during bootstrap via:
- `WPLokerBJM\Core\Container\Definitions\Core` → `Init` service
- `WPLokerBJM\Core\Container\Init::initialize()` registers hooks via `add_action` / `add_filter`.

### Troubleshooting

- If your hook isn't firing:
  - Confirm the class is in the `server/` folder and uses the `WPLokerBJM` namespace.
  - Verify the file contains a class with a matching namespace (scanner uses token parsing).
  - Ensure the method is `public` and the hook attribute is applied to the method.
  - If using an instance method, ensure the class is not excluded by autowiring rules.

- Check theme logs (via `WPLokerBJM\Shared\Log\Logger`) for warnings about missing services or failures during hook registration.

## Notes

- The scanner ignores folders like `vendor`, `node_modules`, `cache`, `tests`, etc.
- Caches are invalidated whenever the compiled container hash changes (e.g., after dependency changes or code updates).
