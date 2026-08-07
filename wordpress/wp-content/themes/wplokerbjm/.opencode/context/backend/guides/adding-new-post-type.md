<!-- Context: backend/guides | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Guide: Adding a New Post Type

**Purpose**: Register a custom post type following the established schema pattern
**Last Updated**: 2026-07-27

## Prerequisites
- Understand `#[Action('init')]` attribute pattern (see `examples/adding-a-hook.md`)
- Know the post type slug, labels, and capabilities needed

**Estimated time**: 15 min

## Steps

### 1. Define Post Type Constant
Add constant to `server/Models/Schema/PostTypes.php`:
```php
public const POST_TYPE_NEW = 'new_post_type';
```
**Implementation**: `server/Models/Schema/PostTypes.php`

### 2. Add Registration Method
Add a method with `#[Action('init')]` in the same file:
```php
#[Action('init')]
public function registerNewPostType(): void
{
    $labels = [
        'name'          => esc_html__('New Items', 'wplokerbjm'),
        'singular_name' => esc_html__('New Item', 'wplokerbjm'),
        'add_new_item'  => esc_html__('Add New Item', 'wplokerbjm'),
        // ... add all 25+ labels for full WP admin integration
    ];

    $args = [
        'label'     => esc_html__('New Items', 'wplokerbjm'),
        'labels'    => $labels,
        'public'    => true,
        'show_in_rest' => true,       // Required for headless/GraphQL
        'supports'  => ['title', 'editor', 'thumbnail'],
        'taxonomies'=> [Taxonomies::KATEGORI_LOWONGAN],
        'rewrite'   => ['slug' => self::POST_TYPE_NEW, 'with_front' => false],
    ];

    register_post_type(self::POST_TYPE_NEW, $args);
}
```
**Expected**: Post type appears in WP admin and REST API.

### 3. Update Container Definitions
If the post type needs custom behavior, add to `Factory::getDefinitions()` in:
```
server/Core/Container/Definitions/Factory.php
```

### 4. Add Custom Fields (Optional)
Use Meta Box GUI or add schema to `server/Models/Schema/CustomFields.php`:
```php
public const NEW_FIELD = 'new_custom_field';
```

### 5. Add GraphQL Support (Optional)
If the post type should be queryable via GraphQL, add object types and resolvers:
- Add type to `server/Services/GraphQL/GraphQLRegistration.php`
- Add resolver to `server/Controllers/GraphQL/Resolvers/`
- See `guides/graphql-resolvers.md` for details

## Verification
```bash
# Check if post type registered in REST API
curl -s https://site.local/wp-json/wp/v2/types | grep new_post_type

# Run tests
composer test
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Post type not showing | Check `#[Action('init')]` attribute; rebuild container cache |
| REST not accessible | Verify `'show_in_rest' => true` in args |
| Labels not translated | Ensure `esc_html__()` with 'wplokerbjm' text domain |

## 📂 Codebase References

**Schema Definition**:
- `server/Models/Schema/PostTypes.php` — Existing `lowongan` post type (reference pattern)

**Container Definitions**:
- `server/Core/Container/Definitions/Factory.php` — Post type service registrations

**Cron for Post Type**:
- `server/Core/Cron/Posts/` — WP-Cron jobs for post lifecycle management

## Related
- `concepts/hook-system.md` — Attribute hook mechanism
- `guides/working-with-taxonomies.md` — Attach taxonomies to new post type
- `guides/graphql-resolvers.md` — Expose post type via GraphQL
