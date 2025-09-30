import type { ComponentConfig } from "@/types";
import { nextTick, createVNode, render, type VNode, type Component, type DefineComponent, type App, type AppContext } from "vue";

/**
 * Service that encapsulates component resolution, caching, and mounting.
 */
export class MounterService {
  /**
   * Normalize and resolve a component value which might be:
   * - a sync component object
   * - a dynamic import Promise (import(...))
   * - a factory function returning a Promise (() => import(...))
   */
  static async resolveComponentValue(value: unknown): Promise<Component | DefineComponent | null> {
    // The incoming value may be:
    // - a sync component (Component | DefineComponent)
    // - a dynamic import Promise (Promise<Module>)
    // - a factory function returning a Promise (() => Promise<Module> | Component)
    let comp: unknown = value;

    // If it's a factory function (likely returning a Promise or a sync component), call it
    if (typeof comp === "function" && (comp as Function).prototype === undefined) {
      const maybe = (comp as Function)();
      // If it returned a Promise-like, await it
      if (maybe && typeof (maybe as { then?: unknown }).then === "function") {
        const mod = await (maybe as Promise<unknown>);
        // prefer default export if present
        if (mod && typeof mod === 'object' && 'default' in (mod as object)) {
          comp = (mod as { default: Component | DefineComponent }).default;
        } else {
          comp = mod;
        }
      } else {
        // returned a sync component
        comp = maybe;
      }
    } else if (comp && typeof (comp as { then?: unknown }).then === "function") {
      const mod = await (comp as Promise<unknown>);
      if (mod && typeof mod === 'object' && 'default' in (mod as object)) {
        comp = (mod as { default: Component | DefineComponent }).default;
      } else {
        comp = mod;
      }
    }

    // coerce to Component-compatible type or null
    if (!comp) return null;
    return comp as Component | DefineComponent;
  }

  /**
   * Create a cached resolver for component values. The cache stores Promises
   * and automatically deletes entries when the promise rejects so retries are possible.
   */
  static createResolveCache(resolveFn?: (v: unknown) => Promise<Component | DefineComponent | null>): { getResolvePromise: (component: unknown) => Promise<Component | DefineComponent | null> } {
    const cache = new Map<unknown, Promise<Component | DefineComponent | null>>();

    const resolver = resolveFn ? resolveFn : (v: unknown): Promise<Component | DefineComponent | null> => MounterService.resolveComponentValue(v);

    const getResolvePromise = (component: unknown): Promise<Component | DefineComponent | null> => {
      let p = cache.get(component);
      if (!p) {
        p = (async (): Promise<Component | DefineComponent | null> => {
          try {
            return await resolver(component);
          } catch (err) {
            // delete on failure so callers can retry
            cache.delete(component);
            throw err;
          }
        })();
        cache.set(component, p);
      }
      return p;
    };

    return { getResolvePromise } as const;
  }

  static placeholderRemoval(element: Element): { removePlaceholderImmediate: () => void; removePlaceholderWithFade: () => Promise<void>; restoreUserState: () => void } {
    const previousActive = (typeof document !== 'undefined' && document.activeElement) ? document.activeElement as HTMLElement | null : null;
    const previousScroll = (typeof window !== 'undefined') ? { x: window.scrollX || 0, y: window.scrollY || 0 } : { x: 0, y: 0 };

    const restoreUserState = (): void => {
      try {
        if (previousActive && typeof previousActive.focus === 'function') {
          previousActive.focus();
        }
      } catch {
        // ignore focus restore failures
      }

      try {
        if (typeof window !== 'undefined' && (previousScroll.x || previousScroll.y)) {
          window.scrollTo(previousScroll.x, previousScroll.y);
        }
      } catch {
        // ignore scroll restore failures
      }
    };

    const removePlaceholderImmediate = (): void => {
      try {
        const placeholder = element.querySelector('.component-placeholder') as HTMLElement | null;
        if (placeholder) {
          try {
            placeholder.setAttribute('aria-busy', 'false');
          } catch { /* ignore */ }
        }

        if (placeholder && placeholder.parentElement === element) {
          placeholder.remove();
        }

        restoreUserState();
      } catch {
        // ignore
      }
    };

    const removePlaceholderWithFade = async (): Promise<void> => {
      try {
        const placeholder = element.querySelector('.component-placeholder') as HTMLElement | null;
        if (!placeholder || placeholder.parentElement !== element) return;

        try { placeholder.setAttribute('aria-busy', 'false'); } catch { /* ignore */ }

        // trigger CSS fade
        placeholder.classList.add('component-placeholder--fadeout');

        // Wait for transitionend or fallback 400ms
        await new Promise<void>((resolve) => {
          let resolved = false;
          const onEnd = (): void => {
            if (resolved) return;
            resolved = true;
            placeholder.removeEventListener('transitionend', onEnd);
            resolve();
          };
          placeholder.addEventListener('transitionend', onEnd);
          // fallback
          setTimeout((): void => {
            if (resolved) return;
            resolved = true;
            placeholder.removeEventListener('transitionend', onEnd);
            resolve();
          }, 400);
        });

        if (placeholder.parentElement === element) placeholder.remove();

        // restore focus/scroll after fade removal
        restoreUserState();
      } catch {
        try { removePlaceholderImmediate(); } catch { /* ignore */ }
      }
    };

    return { removePlaceholderImmediate, removePlaceholderWithFade, restoreUserState } as const;
  }

  static parseProps(element: Element, propAttr: string): Record<string, unknown> {
    const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`) as HTMLScriptElement | null;
    let props: Record<string, unknown> = {};

    if (scriptElement) {
      const raw = scriptElement.textContent || scriptElement.innerHTML || "";
      try {
        props = raw ? JSON.parse(raw) : {};
      } catch {
        props = {};
      }
    }

    type ImportMetaLike = { env?: { DEV?: boolean } };
    const isDev = typeof import.meta !== 'undefined' && Boolean((import.meta as unknown as ImportMetaLike).env?.DEV);
    if (!isDev) {
      if (scriptElement && scriptElement.parentElement) {
        try { scriptElement.remove(); } catch { /* ignore */ }
      }
    }

    return props;
  }

  /**
   * Mount a resolved component VNode into an element using the provided app context.
   */
  static async mountElement(
    element: Element,
    config: ComponentConfig,
    resolvedPromise: Promise<Component | DefineComponent | null>,
    rootApp: App<Element>,
    propAttr: string,
    onError?: (error: unknown, element: Element, config: ComponentConfig) => void
  ): Promise<void> {
    // Fade-out + fallback removal behavior
    const FALLBACK_REMOVE_MS = 7000; // ms before we forcibly remove placeholder
    let fallbackTimer: number | undefined;

    const { removePlaceholderImmediate, removePlaceholderWithFade } = MounterService.placeholderRemoval(element);

    try {
      fallbackTimer = window.setTimeout(() => {
        removePlaceholderImmediate();
      }, FALLBACK_REMOVE_MS);

      const resolved = await resolvedPromise;
      if (!resolved) throw new Error("Component resolved to falsy value");

      const props = MounterService.parseProps(element, propAttr);

      const vnode: VNode = createVNode(resolved as Component, props);
      // attach root app context so the programmatic mount shares plugins/pinia/router
      (vnode as VNode & { appContext?: AppContext | null }).appContext = (rootApp as unknown as { _context?: AppContext | null })._context ?? null;
      render(vnode, element);

      element.setAttribute("component-mounted", "1");

      await nextTick();

      // remove placeholder with fade-out for smooth UX
      await removePlaceholderWithFade();

      if (fallbackTimer !== undefined) {
        clearTimeout(fallbackTimer);
      }
    } catch (error) {
      if (fallbackTimer !== undefined) {
        clearTimeout(fallbackTimer);
      }

      // ensure placeholder removed so it can't trap the user
      removePlaceholderImmediate();

      if (onError) {
        onError(error, element, config);
      }
    }
  }
}
