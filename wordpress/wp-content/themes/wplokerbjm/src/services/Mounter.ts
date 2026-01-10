import { mount } from 'svelte';
import type { ComponentConfig } from '@/types';
import { parseProps, removePropsScriptFromElement } from '@/utils';

class SvelteMounter {
  private mountedElements = new WeakSet<Element>();
  private targetProps = 'id="__wplokerbjm_data-props"';
  mount(configs: ComponentConfig[]): void {
    for (const config of configs) {
      if (config.selector === undefined) {
        console.error('selector is undefined for SvelteMounter config', config);
        return;
      }
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements) {
        if (this.mountedElements.has(element)) continue;

        try {
          const props = parseProps(document, this.targetProps);
          const comp = config.component;

          const options: any = { target: element, props };

          requestAnimationFrame(() => {
            element.replaceChildren();
            mount(comp, options);
            removePropsScriptFromElement(document, this.targetProps);
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