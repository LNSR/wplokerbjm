import "@assets/css/tailwind.css";
import { container } from "@/inversify.config.ts";
import { type ComponentMounter } from "@/app";
import { inject } from "inversify";
import type { ComponentConfig } from "@/types";

class MainApp {
  constructor(
    @inject("ComponentMounter") private readonly mountApp: ComponentMounter
  ) { }


  mountComponents(): void {
    const appConfigs: ComponentConfig[] = [
      { selector: "#homepage", component: (): Promise<typeof import("@/pages/homepage.vue")> => import("@/pages/homepage.vue") },
      { selector: "#archive", component: (): Promise<typeof import("@/pages/homepage.vue")> => import("@/pages/homepage.vue") },
      { selector: "#single-lowongan", component: (): Promise<typeof import("@/pages/single-lowongan.vue")> => import("@/pages/single-lowongan.vue") },
      { selector: "#pasang-iklan-loker", component: (): Promise<typeof import("@/pages/pasang-iklan-loker.vue")> => import("@/pages/pasang-iklan-loker.vue") },
    ];
    this.mountApp.mount(appConfigs);
  }
}
container.get<MainApp>(MainApp).mountComponents();