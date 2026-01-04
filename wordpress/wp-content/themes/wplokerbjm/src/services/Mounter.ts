import { mount } from 'svelte';
import type { ComponentConfig } from '@/types';
import { parseProps, removePropsScriptFromElement } from '@/utils';

class SvelteMounter {
  private mountedElements = new WeakSet<Element>();

  mount(configs: ComponentConfig[]): void {
    for (const config of configs) {
      if (config.selector === undefined) {
        console.error('selector is undefined for SvelteMounter config', config);
        return;
      }
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements) {
        if (this.mountedElements.has(element) || element.hasAttribute('svelte-mounted')) continue;

        try {
          const props = parseProps(element, 'id="__wplokerbjm_data-props"');
          const comp = config.component;

          const options: any = { target: element, props };

          requestAnimationFrame(() => {
            mount(comp, options);
            setTimeout(() => {
              removePropsScriptFromElement(element, 'id="__wplokerbjm_data-props"');
            }, 1000);
            this.mountedElements.add(element);
            element.setAttribute('svelte-mounted', 'true');
          });
        } catch {
          console.error(`SvelteMounter: failed to mount ${config.selector}`);
        }
      }
    }
  }
}

export const svelteMounter = new SvelteMounter();