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
    await Promise.all(
      configs.map(async (config) => {
        const elements = document.querySelectorAll(config.selector);
        await Promise.all(
          Array.from(elements).map(async (element) => {
            try {
              const props: any = JSON.parse(
                element.getAttribute(propAttr) || "{}"
              );
              const component =
                typeof config.component === "function"
                  ? defineAsyncComponent(config.component)
                  : config.component;
              this.appFactory.create(component, props).mount(element);
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
          })
        );
      })
    );
  }
}
