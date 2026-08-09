# Concept: Hook System

**Purpose**: Understand attribute-driven WordPress hook registration with lazy DI resolution

## Core Idea

All WordPress hooks are registered via PHP 8 attributes (`#[Action]`, `#[Filter]`) on class methods and properties. At container build time, `WPHooksScanner` discovers annotated members. At runtime, `WPHooksContainerRegistry` registers them lazily — the service is resolved from the DI container **only when the hook fires**, not at registration time.

## Hook Lifecycle

```
Container Build           WordPress Fires Hook
     │                           │
WPHooksScanner              WPHooksContainerRegistry
discovers #[Action]   →    resolves service
  attributes              calls annotated method
     │                           │
ContainerLazyHookHandler            Service instantiated
 stored in registry        at most once/request
```

## Key Points

- `#[Action('hook_name', priority, args)]` replaces `add_action()`
- `#[Filter('hook_name', priority, args)]` replaces `add_filter()`
- Methods must be in **autowirable classes** (discovered by AutowireScanner)
- Attribute params: `executeIf`, `registerIf`, `tag` (static list or callable), `deferRegisterUntilHook`, `once`, `deferRegister`
- `deferRegister = true` skips registration by default (opt-in activation); `deferRegisterUntilHook` defers until a named trigger hook fires and implies defer
- `once` removes the registration after its first evaluation
- Dynamic removal: `unregisterByCallable()`, `unregisterByHook()`, `unregisterByClass()`, `unregisterByNamespace()`, `unregisterByTags()` plus wildcard `unregisterByHookPattern()` / `unregisterByTagPattern()`
- Meta/term hooks in `Cloudflare` self-unregister after first fire per request

## Attribute Examples

```php
#[Action('init')]
public function registerPostType(): void { /* ... */ }

#[Filter('the_content', 10, 1)]
public function modifyContent(string $content): string { return $content; }

#[Action('save_post', 10, 2, deferRegister: true)]
public function handleSave(int $postId, WP_Post $post): void { /* ... */ }

#[Filter('posts_search', acceptedArgs: 2, once: true)]
public function search(string $sql, object $query): string { return $sql; }
```

## 📂 Codebase References

**Attributes**:
- `server/Core/Container/Attributes/WPHooksAttributes.php` — `#[Action]` and `#[Filter]` definitions

**Discovery & Registration**:
- `server/Core/Container/Support/WPHooks/` — Scanner and Registry implementation
- `server/Core/Container/Definitions/Factory.php` — ContainerLazyHookHandler definitions

**Usage Examples**:
- `server/Models/Schema/PostTypes.php` — `#[Action('init')]` for post type registration
- `server/Services/WebHooks/Cloudflare.php` — Multi-attribute hooks with self-unregister
- `server/Services/GraphQL/GraphQLRegistration.php` — `#[Action('graphql_register_types')]`

## Related
- `concepts/architecture.md` — How hooks integrate with DI container
- `concepts/wphooks-architecture.md` — Two registration paths and the shared deferred pool
- `examples/adding-a-hook.md` — Code example of adding new hooks
- `lookup/hooks.md` — All registered hooks reference
