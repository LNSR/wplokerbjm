import "@assets/css/tailwind.css";
import { container } from "@/inversify.config.ts";
import { type ComponentMounter } from "@/app";
import { inject } from "inversify";
import type { ComponentConfig } from "@/types";


const Homepage = (): Promise<typeof import("@/pages/homepage.vue")> => import("@/pages/homepage.vue");
const SingleLowongan = (): Promise<typeof import("@/pages/single-lowongan.vue")> => import("@/pages/single-lowongan.vue");
const PasangIklanLoker = (): Promise<typeof import("@/pages/pasang-iklan-loker.vue")> => import("@/pages/pasang-iklan-loker.vue");
class MainApp {
  constructor(
    @inject("ComponentMounter") private readonly mountApp: ComponentMounter
  ) { }


  mountComponents(): void {
    const appConfigs: ComponentConfig[] = [
      { selector: "#homepage", component: Homepage },
      { selector: "#archive", component: Homepage },
      { selector: "#single-lowongan", component: SingleLowongan },
      { selector: "#pasang-iklan-loker", component: PasangIklanLoker },
    ];
    this.mountApp.mount(appConfigs);
  }
}
const app = container.get<MainApp>(MainApp);
app.mountComponents();