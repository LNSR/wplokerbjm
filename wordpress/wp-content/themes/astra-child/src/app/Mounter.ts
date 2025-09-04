import { inject, injectable } from "inversify";
import { VueAppFactory } from "./Factory";
import { MounterService } from "@/services/MounterService";
import { type ComponentConfig } from "@/types";
import { type Component } from "vue";

type MounterOptions = {
  propAttribute?: string;
  onError?: (error: unknown, element: Element, config: ComponentConfig) => void;
};
// Using WeakSet avoids leaking memory (entries are removed when elements are GC'd).
const mountedElements = new WeakSet<Element>();

@injectable()
export class ComponentMounter {
  constructor(
    @inject("VueAppFactory") private readonly appFactory: VueAppFactory,
  ) { }

  async mount(configs: ComponentConfig[] = [], options: MounterOptions = {}): Promise<void> {
    const propAttr = options.propAttribute || "data-props";
    const rootApp = this.appFactory.getOrCreateRootApp();

    const { getResolvePromise } = MounterService.createResolveCache((c) =>
      MounterService.resolveComponentValue(c)
    );

    const mountPromises: Promise<void>[] = [];

    const mountElement = (
      element: Element,
      config: ComponentConfig,
      resolvedPromise: Promise<Component | null>
    ): Promise<void> =>
      MounterService.mountElement(
        element,
        config,
        resolvedPromise,
        rootApp,
        propAttr,
        options.onError
      );

    for (const config of configs) {
      const elements = document.querySelectorAll(config.selector);

      for (const element of elements as unknown as Element[]) {
        if (mountedElements.has(element) || element.hasAttribute("component-mounted")) continue;

        const resolvedPromise = getResolvePromise(config.component);

        // Only mark as mounted if the mount completes successfully
        const p = mountElement(element, config, resolvedPromise).then(() => {
          mountedElements.add(element);
        });

        mountPromises.push(p);
      }
    }

    await Promise.all(mountPromises);
  }
}
