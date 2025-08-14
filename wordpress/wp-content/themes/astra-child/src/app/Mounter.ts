import type { ComponentConfig } from "@/types";
import { inject, injectable } from "inversify";
import { VueAppFactory } from "./Factory";
import { MounterService } from "@/services/MounterService";

type MounterOptions = {
  propAttribute?: string;
  onError?: (error: unknown, element: Element, config: ComponentConfig) => void;
};

// Module-level WeakSet to track which Elements we've mounted in this page session.
// Using WeakSet avoids leaking memory (entries are removed when elements are GC'd).
const mountedElements = new WeakSet<Element>();

@injectable()
export class ComponentMounter {
  constructor(
    @inject("VueAppFactory") private appFactory: VueAppFactory,
    @inject("MounterService") private mounterService: MounterService
  ) { }

  async mount(configs: ComponentConfig[] = [], options: MounterOptions = {}) {
    const propAttr = options.propAttribute || "data-props";
    const rootApp = this.appFactory.getOrCreateRootApp();

    const { getResolvePromise } = this.mounterService.createResolveCache((c) =>
      this.mounterService.resolveComponentValue(c)
    );

    const mountPromises: Promise<void>[] = [];

    const mountElement = (
      element: Element,
      config: ComponentConfig,
      resolvedPromise: Promise<any>
    ) =>
      this.mounterService.mountElement(
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
