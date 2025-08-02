# Dependency Injection System

This theme uses PHP-DI for dependency injection with automatic class discovery and registration.

## How It Works

The DI container automatically scans the `inc/` directory recursively and registers all suitable classes for autowiring. This eliminates the need to manually register every class while still allowing for custom configurations when needed.

### Automatic Registration

The `AutowireScanner` class automatically discovers and registers classes that are:
- ✅ **Concrete classes** with public constructors
- ✅ **Regular service classes** (repositories, services, controllers, etc.)
- ✅ **Classes with dependencies** that can be autowired

### Automatically Skipped

The scanner intelligently skips classes that shouldn't be autowired:
- ❌ **Interfaces** - Cannot be instantiated
- ❌ **Abstract classes** - Cannot be instantiated
- ❌ **Static-only classes** - Don't need instances (utility classes, etc.)
- ❌ **Traits** - Cannot be instantiated
- ❌ **Classes with non-public constructors** - Cannot be autowired

### Manual Definitions

You can still create manual definitions for special cases:

1. **Interface bindings** - When you need to bind interfaces to implementations
2. **Complex factory patterns** - When simple autowiring isn't sufficient
3. **Service arrays** - When you need to pass arrays of services (like the `Init` class)
4. **Singleton patterns** - When you need specific instantiation logic

Manual definitions are placed in `inc/Core/Definitions/` and take precedence over auto-scanned definitions.

## Directory Structure

```
inc/
├── Core/
│   ├── Container.php           # Main DI container
│   ├── AutowireScanner.php     # Auto-discovery class
│   └── Definitions/
│       ├── AutoScanned.php     # Auto-discovered classes
│       ├── Core.php           # Manual core definitions
│       ├── Repositories.php   # Interface bindings
│       ├── Factories.php      # Factory definitions
│       └── UnsolvableWiring.php # Complex definitions
├── Components/
├── Controllers/
├── Factories/
├── Models/
├── Repositories/
├── Services/
└── ... (other directories)
```

## Usage Examples

### Simple Class (Auto-registered)
```php
namespace AstraChild\Services;

class JobService
{
    public function __construct(
        private JobRepository $jobRepo,
        private TaxonomyService $taxService
    ) {}
    
    // ... methods
}
```

### Interface Binding (Manual definition)
```php
// In Definitions/Repositories.php
return [
    DataProviderInterface::class => get(CustomFieldRepository::class),
];
```

### Using Services
```php
use AstraChild\Core\Container;

// Get a service from the container
$container = Container::getContainer();
$jobService = $container->get(JobService::class);
```

## Performance

- **Development**: Classes are scanned on each request for flexibility
- **Production**: Container compilation is enabled with caching for optimal performance
- **APCu**: When available, definition caching is enabled for additional speed

## Debugging

The `AutowireScanner` includes debug methods to help identify which classes are being registered and why some might be skipped. See the scanner class for debug utilities.
