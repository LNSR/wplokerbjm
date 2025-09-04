import { type createRouter, type createWebHistory, type Router } from "vue-router";
import { inject, injectable } from "inversify";
const Homepage = (): Promise<typeof import("@/pages/homepage.vue")> => import("@/pages/homepage.vue");
const SingleLowongan = (): Promise<typeof import("@/pages/single-lowongan.vue")> => import("@/pages/single-lowongan.vue");
const PasangIklanLoker = (): Promise<typeof import("@/pages/pasang-iklan-loker.vue")> => import("@/pages/pasang-iklan-loker.vue");

@injectable()
export class AppRouter {
  @inject("CreateRouter") private readonly createRouter!: typeof createRouter;
  @inject("CreateWebHistory") private readonly createWebHistory!: typeof createWebHistory;

  createAppRouter(): Router {
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
    return this.createRouter({ history: this.createWebHistory(), routes });
  }
}
