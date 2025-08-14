import { createRouter, createWebHistory, type Router } from "vue-router";
import { injectable } from "inversify";
const Homepage = () => import("@/pages/homepage.vue");
const SingleLowongan = () =>  import("@/pages/single-lowongan.vue");
const PasangIklanLoker = () => import("@/pages/pasang-iklan-loker.vue");
@injectable()
export class AppRouter {
  public readonly router: Router;

  constructor() {
    this.router = this.createAppRouter();
  }

  private createAppRouter(): Router {
    const routes = [
      { path: "/", name: "Home", component: Homepage },
      {
        path: "/lowongan/:slug",
        name: "JobDetail",
        component: SingleLowongan,
        props: true,
      },
      {
        path: "/pasang-iklan-loker",
        name: "PasangIklan",
        component: PasangIklanLoker,
      },
    ];
    return createRouter({ history: createWebHistory(), routes });
  }
}
