<!-- Context: backend/guides | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Guide: Working with Taxonomies

**Purpose**: Register, query, and manage custom taxonomies in the WPLokerBJM theme
**Last Updated**: 2026-07-27

## Prerequisites
- Understand `#[Action('init')]` attribute (see `examples/adding-a-hook.md`)
- Know existing taxonomies: `perusahaan`, `kategori_lowongan`, `lokasi_pekerjaan`, `jenis_pekerjaan`, `gender`, `pendidikan`

**Estimated time**: 20 min

## Steps

### 1. Register New Taxonomy
Add to `server/Models/Schema/Taxonomies.php`:
```php
public const NEW_TAXONOMY = 'new_taxonomy_slug';

#[Action('init')]
public function registerNewTaxonomy(): void
{
    $labels = [
        'name'              => esc_html__('New Taxonomy', 'wplokerbjm'),
        'singular_name'     => esc_html__('New Taxonomy', 'wplokerbjm'),
        'add_new_item'      => esc_html__('Add New', 'wplokerbjm'),
        // ... full label set
    ];
    $args = [
        'label'       => esc_html__('New Taxonomy', 'wplokerbjm'),
        'labels'      => $labels,
        'public'      => true,
        'hierarchical'=> true,
        'show_in_rest'=> true,         // Required for headless
        'capabilities'=> [
            'manage_terms' => 'manage_categories',
            'edit_terms'   => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts',
        ],
    ];
    register_taxonomy(self::NEW_TAXONOMY, [PostTypes::POST_TYPE_LOWONGAN], $args);
}
```
**Implementation**: `server/Models/Schema/Taxonomies.php`

### 2. Update Post Types
Add new taxonomy to the post type's `taxonomies` array:
```php
// In PostTypes.php registerLowonganPostType():
'taxonomies' => [Taxonomies::JENIS_PEKERJAAN, ..., Taxonomies::NEW_TAXONOMY],
```

### 3. Query Taxonomy Terms
Use `TaxonomyQuery` for cached queries:
```php
use WPLokerBJM\QueryBuilders\TaxonomyQuery;

// Get all terms for a taxonomy
$terms = TaxonomyQuery::getTerms(Taxonomies::NEW_TAXONOMY);

// Get grouped options (used by REST ingest)
$options = TaxonomyQuery::getTaxonomyOptions([Taxonomies::NEW_TAXONOMY]);
```
**Implementation**: `server/QueryBuilders/TaxonomyQuery.php`

### 4. Use in Repository
For complex queries, use `TaxonomyRepository`:
```php
use WPLokerBJM\Repositories\TaxonomyRepository;

$repo = new TaxonomyRepository();
$posts = $repo->getPostsByTerm(Taxonomies::NEW_TAXONOMY, $termSlug);
```
**Implementation**: `server/Repositories/TaxonomyRepository.php`

### 5. Add GraphQL Fields (Optional)
Add taxonomy terms to GraphQL in `GraphQLRegistration`:
```php
register_graphql_field('RootQuery', 'newTaxonomyTerms', [
    'type'    => 'JSON',
    'resolve' => fn() => $this->taxonomyResolver->resolveNewTerms(),
]);
```

## Key Points

- **6 existing taxonomies**: All registered via `#[Action('init')]` in `Taxonomies.php`
- **Agent taxonomies**: `kategori_lowongan`, `lokasi_pekerjaan`, `jenis_pekerjaan`, `gender`, `pendidikan` — available for automated ingest
- **Perusahaan is reserved**: Human-curated only, excluded from agent options
- **Meta Box integration**: Taxonomy config stored in Meta Box GUI; code serves as blueprint
- **Cron cleanup**: `server/Core/Cron/Taxonomy/` has scheduled taxonomy maintenance

## 📂 Codebase References

**Schema**:
- `server/Models/Schema/Taxonomies.php` — All 6 taxonomy registrations
- `server/Models/Schema/PostTypes.php` — Taxonomy-to-post-type mappings

**Data Access**:
- `server/QueryBuilders/TaxonomyQuery.php` — Cached taxonomy queries
- `server/Repositories/TaxonomyRepository.php` — Repository layer

**Cron**:
- `server/Core/Cron/Taxonomy/` — Scheduled taxonomy cleanup jobs

## Related
- `guides/adding-new-post-type.md` — Attach taxonomies to new post types
- `guides/graphql-resolvers.md` — Expose taxonomies via GraphQL
- `concepts/caching.md` — Taxonomy cache keys
