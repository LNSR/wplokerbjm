import { type App, type createApp } from "vue";
import { type Pinia } from "pinia";
import { injectable, inject } from "inversify";
import type { AppRouter } from "./Router";

@injectable()
export class VueAppFactory {

  private app: App | null = null;

  constructor(
    @inject("CreateApp") private readonly createAppFn: typeof createApp,
    @inject("Pinia") private readonly pinia: Pinia,
    @inject("AppRouter") private readonly AppRouter: AppRouter
  ) { }

  /*
   * Create (or return cached) root app used only for sharing the app context
   * (pinia, router, plugins) across programmatic mounts. The root app is not
   * mounted to the DOM here; we use its `_context` on VNodes.
   */
  getOrCreateRootApp(): App {
    if (!this.app) {
      this.app = this.createAppFn({});
      this.app!.use(this.pinia);
      if (this.AppRouter) this.app!.use(this.AppRouter.createAppRouter());
      this.app!.config.errorHandler = (err: unknown, _instance: unknown, info: string): void => {
        console.error("Root App Error:", err, info);
      };
    }
    return this.app!;
  }
}
