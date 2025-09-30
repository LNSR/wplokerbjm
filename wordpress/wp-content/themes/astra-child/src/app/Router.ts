import { type createRouter, type createWebHistory, type Router } from "vue-router";
import { inject, injectable } from "inversify";

@injectable()
export class AppRouter {
  @inject("CreateRouter") private readonly createRouter!: typeof createRouter;
  @inject("CreateWebHistory") private readonly createWebHistory!: typeof createWebHistory;

  createAppRouter(): Router {
    const routes = [
      { path: "/", name: "Home", component: (): Promise<typeof import("@/pages/homepage.vue")> => import("@/pages/homepage.vue") },
      {
        path: "/lowongan/:slug",
        name: "JobDetail",
        component: (): Promise<typeof import("@/pages/single-lowongan.vue")> => import("@/pages/single-lowongan.vue"),
        props: true,
      },
      {
        path: "/pasang-iklan-loker",
        name: "PasangIklan",
        component: (): Promise<typeof import("@/pages/pasang-iklan-loker.vue")> => import("@/pages/pasang-iklan-loker.vue"),
      },
    ];
    return this.createRouter({ history: this.createWebHistory(), routes });
  }
}
