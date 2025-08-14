import { type App } from "vue";
import { type Pinia } from "pinia";
import { injectable, inject } from "inversify";
import type { AppRouter } from "./Router";

@injectable()
export class VueAppFactory {
  private rootApp?: App;

  constructor(
    @inject("CreateApp")
    private createApp: (component: any, props?: any) => App,
    @inject("Pinia") private pinia: Pinia,
    @inject("AppRouter") private AppRouter?: AppRouter
  ) {}

  /**
   * Create (or return cached) root app used only for sharing the app context
   * (pinia, router, plugins) across programmatic mounts. The root app is not
   * mounted to the DOM here; we use its `_context` on VNodes.
   */
  getOrCreateRootApp(): App {
    if (!this.rootApp) {
      this.rootApp = this.createApp({});
      this.rootApp.use(this.pinia);
      if (this.AppRouter) this.rootApp.use(this.AppRouter.router);
      this.rootApp.config.errorHandler = (err, info) => {
        console.error("Root App Error:", err, info);
      };
    }
    return this.rootApp;
  }
}
