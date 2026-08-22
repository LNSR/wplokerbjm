<!-- Context: backend/guides | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Guide: Adding GraphQL Resolvers

**Purpose**: Add new GraphQL queries, mutations, and custom types via WPGraphQL
**Last Updated**: 2026-07-27

## Prerequisites
- WPGraphQL plugin active
- Understand resolver pattern from `examples/adding-a-service.md`
- Familiar with `GraphQLRegistration` class structure

**Estimated time**: 25 min

## Steps

### 1. Create Resolver Class
Create in `server/Controllers/GraphQL/Resolvers/`:
```php
<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;

class NewFeatureResolver
{
    public function __construct(
        private readonly YourService $service,
    ) {}

    public function resolveNewQuery(array $args): array
    {
        $id = $args['id'] ?? 0;
        return $this->service->getData($id);
    }
}
```

### 2. Wire into GraphQLRegistration
Inject resolver into `GraphQLRegistration` constructor:
```php
public function __construct(
    // ... existing resolvers
    private readonly NewFeatureResolver $newFeatureResolver,
) {}
```

### 3. Register GraphQL Types
Add to `registerObjectTypes()`:
```php
register_graphql_object_type('NewFeature', [
    'description' => 'New feature data',
    'fields' => [
        'id'    => ['type' => 'Int'],
        'title' => ['type' => 'String'],
        'data'  => ['type' => 'JSON'],
    ],
]);
```

### 4. Register Field with Resolver
Add to `registerFields()`:
```php
register_graphql_field('RootQuery', 'newFeature', [
    'type'        => ['list_of' => 'NewFeature'],
    'description' => 'Get new feature data',
    'args'        => [
        'id' => ['type' => 'Int', 'description' => 'Feature ID'],
    ],
    'resolve'     => fn(...$args) => $this->newFeatureResolver->resolveNewQuery(...$args),
]);
```

### 5. Add Caching
Use `Cache` class for performance:
```php
public function resolveNewQuery(array $args): array
{
    $cacheKey = CacheKey::GRAPHQL_JOB_CARD_PREFIX . 'new_feature_' . ($args['id'] ?? 'all');
    $cached = Cache::get($cacheKey);
    if ($cached !== false) return $cached;

    $data = $this->service->getData($args['id'] ?? 0);
    Cache::set($cacheKey, $data, HOUR_IN_SECONDS);
    return $data;
}
```

## Key Patterns

- **Resolver location**: `Controllers/GraphQL/Resolvers/` — one class per domain
- **Type registration**: `GraphQLRegistration::registerTypes()` via `#[Action('graphql_register_types')]`
- **Argument passing**: Use arrow functions `fn(...$args) => $this->resolver->method(...$args)`
- **Custom scalar JSON**: Registered once in `registerScalars()`; use for flexible data shapes
- **DI injection**: All resolvers are constructor-injected into `GraphQLRegistration`

## Existing Query Fields

| Field | Resolver | Returns |
|-------|----------|---------|
| `taxonomyTerms` | `TaxonomyResolver` | Grouped taxonomy terms |
| `carousel` | `JobsDataResolver` | Carousel jobs |
| `loadMore` | `JobsDataResolver` | Paginated jobs with filters |
| `jobDetail` | `JobsDataResolver` | Single job by slug |
| `jobSchema` | `JobsDataResolver` | Schema.org JSON-LD |
| `searchJobs` | `JobsDataResolver` | Full-text search |
| `themeData` | `ThemeDataResolver` | Logo, nonce, site icon |
| `jwt` (mutation) | `JWTDataResolver` | Auth token/validation |

## 📂 Codebase References

**Registration**:
- `server/Services/GraphQL/GraphQLRegistration.php` — All types + fields (521 lines)
- `server/Services/GraphQL/GraphQLJobData.php` — Shared data methods

**Resolvers**:
- `server/Controllers/GraphQL/Resolvers/JobsDataResolver.php` — Job queries
- `server/Controllers/GraphQL/Resolvers/TaxonomyResolver.php` — Taxonomy queries
- `server/Controllers/GraphQL/Resolvers/ThemeDataResolver.php` — Theme data
- `server/Controllers/GraphQL/Resolvers/Auth/JWTDataResolver.php` — JWT auth

## Related
- `concepts/caching.md` — Cache GraphQL responses
- `guides/adding-new-post-type.md` — Expose new post types via GraphQL
- `guides/working-with-taxonomies.md` — Expose taxonomies via GraphQL
