import { mount, type Component } from 'svelte';
import type { ComponentConfig } from '@/types';
import { parseProps, removePropsScriptFromElement } from '@/utils';

class SvelteMounter {
  private targetProps = 'id="__wplokerbjm_data-props"';
  mount(configs: ComponentConfig[]): void {
    for (const config of configs) {
      if (config.selector === undefined) {
        console.error('selector is undefined for SvelteMounter config', config);
        return;
      }
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements) {
        try {
          const props = parseProps(element, this.targetProps);
          const comp = config.component as unknown as Component;

          const options: any = { target: element, props };
          requestAnimationFrame(() => {
            mount(comp, options);
          });
        } catch {
          console.error(`SvelteMounter: failed to mount ${config.selector}`);
        } finally {
          setTimeout(() => {
            removePropsScriptFromElement(element, this.targetProps);
          }, 200);
        }
      }
    }
  }
}

export const svelteMounter = new SvelteMounter();