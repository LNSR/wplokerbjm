import { mount } from 'svelte';
import type { ComponentConfig } from '@/types';
import { parseProps, removePropsScriptFromElement } from '@/utils';

export class SvelteMounter {
  private mountedElements = new WeakSet<Element>();

  async mount(configs: ComponentConfig[]): Promise<void> {
    const mountPromises: Promise<void>[] = [];

    function safeMount(comp: any, element: Element, props: Record<string, unknown>) {
      const options: any = { target: element, props };
      try {
        mount(comp, options as any);
        return;
      } catch (mErr) {
        // Try instantiating directly as a constructor as a last resort
        try {
          new comp(options as any);
          return;
        } catch (newErr) {
          // rethrow original mount error for visibility
          (newErr as any).mountError = mErr;
          throw newErr;
        }
      }
    };

    for (const config of configs) {
      // Accept:
      //  - () => import('...')       (factory)
      //  - import('...')             (Promise)
      //  - await import('...')       (module object)
      // Reject raw/static component constructors passed directly.
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements) {
        if (this.mountedElements.has(element) || element.hasAttribute('svelte-mounted')) continue;

        const mountPromise = (async () => {
          try {
            const props = parseProps(element, 'data-props');

            try {
              const loader = (config.component as any);
              let compModule: any;

              if (typeof loader === 'function') {
                // Call as factory. If calling throws, it's likely a static constructor that
                // was passed directly (e.g. `MyComponent`) — reject that.
                try {
                  const maybe = loader();
                  compModule = await Promise.resolve(maybe);
                } catch (callErr) {
                  console.error(
                    `SvelteMounter: component for selector ${config.selector} appears to be a static constructor — ` +
                    `pass a dynamic import factory \`() => import('...')\`, an import() Promise, or the module object from ` +
                    `\`await import('...')\`.`
                  );
                  return;
                }
              } else if (loader && typeof loader.then === 'function') {
                // import(...) Promise
                compModule = await loader;
              } else {
                // module object from top-level await
                compModule = loader;
              }

              const comp = compModule && (compModule.default ?? compModule);

              if (!comp) {
                throw new Error('SvelteMounter: dynamic import did not resolve to a component');
              }

              await new Promise(resolve => requestAnimationFrame(resolve));
              safeMount(comp, element, props);
              this.mountedElements.add(element);
              try { element.setAttribute('svelte-mounted', '1'); } catch { }
              removePropsScriptFromElement(element, 'data-props');
            } catch (err) {
              console.error(`SvelteMounter: dynamic import or mount failed for ${config.selector}`, err);
            }
          } catch (err) {
            console.error(`SvelteMounter: failed to mount ${config.selector}`, err)
          }
        })();

        mountPromises.push(mountPromise);
      }
    }

    await Promise.all(mountPromises);
  }
}