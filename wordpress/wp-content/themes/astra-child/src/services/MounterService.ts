import type { ComponentConfig } from "@/types";
import { injectable } from "inversify";
import { nextTick, createVNode, render, type VNode } from "vue";

@injectable()
/**
 * Service that encapsulates component resolution, caching, and mounting.
 * Converted from standalone functions into an injectable class to support
 * DI and easier testing.
 */
export class MounterService {
  /**
   * Normalize and resolve a component value which might be:
   * - a sync component object
   * - a dynamic import Promise (import(...))
   * - a factory function returning a Promise (() => import(...))
   */
  async resolveComponentValue(value: unknown) {
    let comp: any = value as any;

    // If it's a factory function (likely returning a Promise), call it
    if (typeof comp === "function" && comp.prototype === undefined) {
      const maybe = comp();
      if (maybe && typeof (maybe as any).then === "function") {
        const mod = await maybe;
        comp = mod && (mod as any).default ? (mod as any).default : mod;
      } else {
        // returned a sync component
        comp = maybe;
      }
    } else if (comp && typeof (comp as any).then === "function") {
      // it's a Promise (import(...))
      const mod = await (comp as any);
      comp = mod && (mod as any).default ? (mod as any).default : mod;
    }

    return comp;
  }

  /**
   * Create a cached resolver for component values. The cache stores Promises
   * and automatically deletes entries when the promise rejects so retries are possible.
   */
  createResolveCache(resolveFn?: (v: unknown) => Promise<any>) {
    const cache = new Map<unknown, Promise<any>>();

    const resolver = resolveFn ? resolveFn : (v: unknown) => this.resolveComponentValue(v);

    const getResolvePromise = (component: unknown) => {
      let p = cache.get(component);
      if (!p) {
        p = (async () => {
          try {
            return await resolver(component);
          } catch (err) {
            // remove from cache so future attempts can retry
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
  
  placeHolderRemoval(element: Element) {
    // Save user state so we can restore after placeholder removed
    const previousActive = (typeof document !== 'undefined' && document.activeElement) ? document.activeElement as HTMLElement | null : null;
    const previousScroll = (typeof window !== 'undefined') ? { x: window.scrollX || 0, y: window.scrollY || 0 } : { x: 0, y: 0 };

    const restoreUserState = () => {
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

    const removePlaceholderImmediate = () => {
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

        // restore focus/scroll after removal to avoid trapping keyboard users
        restoreUserState();
      } catch (err) {
        console.error('Failed to remove placeholder (immediate)', err);
      }
    };

    const removePlaceholderWithFade = async () => {
      try {
        const placeholder = element.querySelector('.component-placeholder') as HTMLElement | null;
        if (!placeholder || placeholder.parentElement !== element) return;

        // mark aria busy -> false so screen readers are informed before removal
        try { placeholder.setAttribute('aria-busy', 'false'); } catch { /* ignore */ }

        // trigger CSS fade
        placeholder.classList.add('component-placeholder--fadeout');

        // Wait for transitionend or fallback 400ms
        await new Promise((resolve) => {
          let resolved = false;
          const onEnd = () => {
            if (resolved) return;
            resolved = true;
            placeholder.removeEventListener('transitionend', onEnd);
            resolve(null);
          };
          placeholder.addEventListener('transitionend', onEnd);
          // fallback
          setTimeout(() => {
            if (resolved) return;
            resolved = true;
            placeholder.removeEventListener('transitionend', onEnd);
            resolve(null);
          }, 400);
        });

        if (placeholder.parentElement === element) placeholder.remove();

        // restore focus/scroll after fade removal
        restoreUserState();
      } catch {
        // best-effort: attempt immediate removal; ignore nested errors
        try { removePlaceholderImmediate(); } catch { /* ignore */ }
      }
    };

    return { removePlaceholderImmediate, removePlaceholderWithFade, restoreUserState } as const;
  }


  /**
   * Mount a resolved component VNode into an element using the provided app context.
   */
  async mountElement(
    element: Element,
    config: ComponentConfig,
    resolvedPromise: Promise<any>,
    rootApp: any,
    propAttr: string,
    onError?: (error: unknown, element: Element, config: ComponentConfig) => void
  ): Promise<void> {
    // Fade-out + fallback removal behavior
    const FALLBACK_REMOVE_MS = 7000; // ms before we forcibly remove placeholder
    let fallbackTimer: number | undefined;

  const { removePlaceholderImmediate, removePlaceholderWithFade } = this.placeHolderRemoval(element);

    try {
      // start fallback timer (in case mount never completes)
      fallbackTimer = window.setTimeout(() => {
        console.warn(`Mount timeout (${FALLBACK_REMOVE_MS}ms) for ${config.selector} — removing placeholder.`);
        removePlaceholderImmediate();
      }, FALLBACK_REMOVE_MS);

      const resolved = await resolvedPromise;
      if (!resolved) throw new Error("Component resolved to falsy value");

      const props: any = element.hasAttribute(propAttr)
        ? JSON.parse(element.getAttribute(propAttr) || "{}")
        : {};

      const vnode: VNode = createVNode(resolved, props as Record<string, unknown>);
      (vnode as any).appContext = (rootApp as any)._context;
      render(vnode, element);

      element.setAttribute("component-mounted", "1");

      await nextTick();

      if (element.hasAttribute(propAttr)) {
        element.removeAttribute(propAttr);
      }

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
      } else {
        console.error(`Failed to mount component at ${config.selector}:`, error);
      }
    }
  }
}
