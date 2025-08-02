import { defineAsyncComponent } from "vue";
import type { ComponentConfig } from "@/types";
import { inject, injectable } from "inversify";
import { VueAppFactory } from "./Factory";

type MounterOptions = {
  propAttribute?: string;
  onError?: (error: unknown, element: Element, config: ComponentConfig) => void;
};

@injectable()
export class ComponentMounter {
  constructor(@inject(VueAppFactory) private appFactory: VueAppFactory) {}

  async mount(configs: ComponentConfig[] = [], options: MounterOptions = {}) {
    const propAttr = options.propAttribute || "data-props";

    for (const config of configs) {
      const elements = document.querySelectorAll(config.selector);

      for (const element of Array.from(elements)) {
        try {
          const props: any = JSON.parse(element.getAttribute(propAttr) || "{}");
          const component =
            typeof config.component === "function"
              ? defineAsyncComponent(config.component)
              : config.component;

          this.appFactory.create(component, props).mount(element);

          if (element.hasAttribute(propAttr)) {
            element.removeAttribute(propAttr);
          }
          
        } catch (error) {
          if (options.onError) {
            options.onError(error, element, config);
          } else {
            console.error(
              `Failed to mount component at ${config.selector}:`,
              error
            );
          }
        }
      }
    }
  }
}
