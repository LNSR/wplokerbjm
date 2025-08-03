import { createRouter, createWebHistory, type Router } from "vue-router";
import JobGrid from "@/components/Homepage/JobGrid.vue";
import SingleOverlay from "@/components/Homepage/JobGrid/SingleOverlay.vue";
import { injectable } from "inversify";

@injectable()
export class AppRouter {
  public readonly router: Router;

  constructor() {
    this.router = this.createAppRouter();
  }

  private createAppRouter(): Router {
    const routes = [
      {
        path: "/",
        name: "Home",
        component: JobGrid,
      },
      {
        path: "/lowongan/:slug",
        name: "JobDetail",
        component: SingleOverlay,
        props: true,
      },
    ];
    return createRouter({
      history: createWebHistory(),
      routes,
    });
  }
}
