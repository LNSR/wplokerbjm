# Backend Context Navigation

**Purpose**: Core development patterns, architecture, and references for the WPLokerBJM headless WordPress theme backend.

---

## Structure

```
backend/
├── navigation.md
├── concepts/
│   ├── architecture.md
│   ├── hook-system.md
│   ├── hook-provider-layer.md
│   ├── wphooks-architecture.md
│   ├── autoloading.md
│   └── caching.md
├── examples/
│   ├── adding-a-hook.md
│   ├── adding-a-service.md
│   └── rest-endpoint.md
├── guides/
│   ├── adding-new-post-type.md
│   ├── working-with-taxonomies.md
│   ├── wphooks-lifecycle.md
│   └── graphql-resolvers.md
├── lookup/
│   ├── namespaces.md
│   ├── hooks.md
│   ├── wphooks-api.md
│   └── dependencies.md
└── errors/
    ├── common-issues.md
    └── hook-attribute-on-magic-method.md
```

---

## Quick Navigation

### Concepts
| File | Description | Priority |
|------|-------------|----------|
| `concepts/architecture.md` | DI container, bootstrap, directory layout | critical |
| `concepts/hook-system.md` | Attribute-driven hooks, scanner, registry | critical |
| `concepts/hook-provider-layer.md` | Plan core for gates, dynamic names, tag closures | high |
| `concepts/wphooks-architecture.md` | Scanner, plans, lazy/runtime registries, invokers | high |
| `concepts/autoloading.md` | Nette RobotLoader integration | high |
| `concepts/caching.md` | Redis cache, Cloudflare purge, keys | high |

### Examples
| File | Description | Priority |
|------|-------------|----------|
| `examples/adding-a-hook.md` | Adding `#[Action]` / `#[Filter]` attributes | critical |
| `examples/adding-a-service.md` | Creating + registering a new service | high |
| `examples/rest-endpoint.md` | REST endpoint pattern (LowonganIngest) | high |

### Guides
| File | Description | Priority |
|------|-------------|----------|
| `guides/adding-new-post-type.md` | Register custom post types | high |
| `guides/working-with-taxonomies.md` | Taxonomy registration + queries | high |
| `guides/wphooks-lifecycle.md` | Discovery, registration, activation, execution, teardown | high |
| `guides/graphql-resolvers.md` | Adding GraphQL resolvers | high |

### Lookup
| File | Description | Priority |
|------|-------------|----------|
| `lookup/namespaces.md` | All namespaces map (33 namespaces) | high |
| `lookup/hooks.md` | Registered actions + filters | high |
| `lookup/wphooks-api.md` | WPHooks classes, selectors, invokers, utilities | high |
| `lookup/dependencies.md` | Composer packages | medium |

### Errors
| File | Description | Priority |
|------|-------------|----------|
| `errors/common-issues.md` | Bootstrap, cache, container errors | high |
| `errors/hook-attribute-on-magic-method.md` | Magic-method hook attributes, scanner contract | medium |

---

## Loading Strategy

**For new feature work**:  
1. `concepts/architecture.md` — understand bootstrap flow  
2. `examples/adding-a-hook.md` — learn attribute pattern  
3. `concepts/wphooks-architecture.md` — understand the two registration paths  
4. `lookup/wphooks-api.md` — locate registries and selectors  
5. `lookup/namespaces.md` — find where to place code

**For debugging**:  
1. `errors/common-issues.md` — check known issues  
2. `concepts/hook-system.md` — verify hook registration  
3. `guides/wphooks-lifecycle.md` — trace gates, deferred activation, and teardown  
4. `concepts/caching.md` — check cache invalidation

**For onboarding**:  
1. `concepts/architecture.md` → `concepts/hook-system.md` → `concepts/wphooks-architecture.md`  
2. Then browse `guides/wphooks-lifecycle.md`, `lookup/wphooks-api.md`, and examples/
