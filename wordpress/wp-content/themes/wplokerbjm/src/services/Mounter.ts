import { mount, type Component } from 'svelte';
import type { ComponentConfig } from '@/types';
import { parseProps } from '@/utils';
/**
 * SvelteMounter is responsible for mounting Svelte components onto specified DOM elements.
 * @deprecated Migrated to SvelteKit, useful only for plain Svelte.
 */
export class SvelteMounter {
  private static targetProps = 'id="__wplokerbjm_data-props"';
  static mount(configs: ComponentConfig[]): void {
    for (const config of configs) {
      if (config.selector === undefined) {
        console.error('selector is undefined for SvelteMounter config', config);
        continue;
      }
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements) {
        try {
          const props = parseProps(element, SvelteMounter.targetProps);
          const comp = config.component as Component;

          const options: { target: Element; props: Record<string, unknown> } = { target: element, props };
          requestAnimationFrame(() => {
            element.replaceChildren();
            mount(comp, options);
          });
        } catch {
          console.error(`SvelteMounter: failed to mount ${config.selector}`);
        }
      }
    }
  }
}