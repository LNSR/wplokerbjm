---
name: svelte
description: Best practices and conventions for using Svelte 5 in this workspace, including core reactivity patterns, documentation retrieval, templates, events, styling, and more.
---

# Svelte 5 Best Practices (Skill Summary)

## Core Reactivity (Runes)

- **`$state`**: Use for reactive state that drives updates (template expressions, `$effect`, `$derived`).
  - Prefer `$state.raw(...)` for large objects that are only reassigned (e.g., API responses).

- **`$derived`**: Compute values from state.
  - Use `$derived(expression)` instead of `$effect` for derived values.
  - Use `$derived.by(...)` when the expression needs a function.
  - Writable; assigning to a derived value is allowed.
- **`$effect`**: Effects are an escape hatch and should mostly be avoided.
  - Use it only for side effects that can't be expressed with derived state, binding attributes.
  - Don’t update state inside `$effect` unless absolutely necessary.
  - Prefer [`{@attach ...}`](references/@attach.md) for syncing with external libraries, Contextual operation to specific to targeted DOM instead of `$effect`.
  - If you need to log values for debugging purposes, use [`$inspect`](references/$inspect.md)
  - If you need to observe something external to Svelte, use [`createSubscriber`](references/svelte-reactivity.md)

- **`$props`**: Treat props as dynamic.
  - Use `$derived` for values derived from props to ensure updates when props change.

- **Debugging**: Use **`$inspect.trace(label)`** in `$effect` / `$derived.by` to trace dependencies and update triggers.

## Reference Documentation (mcp_svelte_get-documentation)

- Use the **`mcp_svelte_get-documentation`** tool to fetch official Svelte 5 docs for a given topic.
- Example: `npx @sveltejs/mcp get-documentation "$state,$derived,$effect"`
- In this workspace, call `mcp_svelte_get-documentation` from the agent to retrieve the latest documentation section content.

## Templates & Events

- Event listeners use attributes starting with `on` (e.g., `onclick={...}`) and support shorthand and spread.
- Prefer `<svelte:window>` and `<svelte:document>` for global event listeners, not `onMount` or `$effect`.

## Snippets & Reuse

- Use `{#snippet ...}{/snippet}` and [**`{@render ...}`**](references/@render.md) for reusable markup.
  - Snippets declared at top-level are available in `<script>`.
  - Snippets without state can be exported from a `<script module>`.

## Looping

- Use **keyed `each` blocks** for performance:
  - The key must uniquely identify each item (not the index).
  - Avoid destructuring items if you need to mutate them (e.g., `bind:value={item.count}`).

## Styling

- Use CSS custom properties for JS-driven styles:
  - Example: `<div style:--columns={columns}>` then use `var(--columns)` in `<style>`.

- To style child components, prefer CSS custom properties passed as attributes.
  - If you must target library components, use `:global` selectors.

## Context & State Sharing

- Use **`createContext`** instead of `setContext`/`getContext` for type safety and scoped state.
- Prefer component-local state or context over shared module state to avoid SSR leaks.

## Bindings & Form Components

- Use **`$bindable`** only when upward data flow is intentional and narrowly scoped.
- Prefer `bind:` on native elements and use **function bindings** when you need validation or transformation.
- Use `bind:group` for related radio/checkbox inputs and `bind:files` for file upload inputs.
- Treat file bindings carefully in SSR code paths; leave file state uninitialized unless the component needs a default.

## Attachments & External Libraries

- Prefer [**`{@attach ...}`**](references/@attach.md) over `use:action` when syncing DOM or third-party libraries to reactive state.
- Use attachment factories for reusable integrations, and convert legacy actions only when the library requires it.
- Keep attachment setup cheap; move expensive work behind a child effect only when re-running is unavoidable.

## Accessibility & Head

- Prefer semantic HTML, keyboard-friendly controls, and explicit focus management over visual-only interactions.
- Use `<svelte:head>` for page titles, meta tags, and SEO-critical markup.
- Treat compiler accessibility warnings as real issues to fix, not noise to suppress.

## Testing & Verification

- Use `svelte-check` / the workspace `bun run check` task to catch type, template, and component errors early.
- Add Vitest for logic-heavy units and Playwright when behavior crosses real DOM interactions.
- Verify changes at the smallest meaningful scope first, then widen only if the touched slice passes.

## SSR & Runtime Boundaries

- Avoid shared module state for request-specific or user-specific data.
- Keep browser-only APIs out of server execution paths; use event handlers, `<svelte:window>`, or `<svelte:document>` where appropriate.
- Use `$state.raw` for large reassigned payloads, especially API responses or other data that is not mutated deeply.

## TypeScript & Component Types

- Type wrapper components with `svelte/elements` attribute types when forwarding props.
- Add explicit typing for reusable props, snippets, and component APIs instead of relying on inference alone.
- Keep `svelte-check` clean; template or typing warnings usually indicate a real regression.

## Async / Advanced

- If using Svelte `>=5.36` with `experimental.async` enabled, you can use **await expressions** and **hydratable** directly in components.

## Legacy / Deprecated Practices

Avoid these in new code (prefer modern runes & APIs):

- `export let` / `$$props` / `$$restProps` → use `$props()`
- `$:` reactive statements → use `$derived` or `$effect`
- `<slot>` / `$$slots` / `<svelte:fragment>` → use snippets and `{@render}`
- `<svelte:component this={...}>` → `<DynamicComponent>`
- `<svelte:self>` → `import Self from './Self.svelte'` and `<Self>`
- `use:action` → `{@attach ...}`
- `class:` directives → clsx-style arrays/objects in `class` attributes

---

> This file is the consolidated Svelte 5 skill reference for this workspace. Keep it up to date with the latest Svelte best practices and runes-based conventions.
