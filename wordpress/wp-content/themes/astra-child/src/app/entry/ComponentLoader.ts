import { ComponentMounter } from "@/inversify.config.ts";
import { inject, injectable } from "inversify";
import type { ComponentConfig } from "@/types";
const Homepage = () => import("@/pages/homepage.vue");
const SingleLowongan = () => import("@/pages/single-lowongan.vue");
const PasangIklanLoker = () => import("@/pages/pasang-iklan-loker.vue");

@injectable()
export class MountApp {
  constructor(@inject("ComponentMounter") private mounter: ComponentMounter) {}

  /**
   * Prepare the mounts and delegate to the ComponentMounter.
   * Components are provided as factories to allow dynamic splitting.
   */
  public async mountComponents(): Promise<void> {
    const appConfigs: ComponentConfig[] = [
      { selector: "#homepage", component: Homepage },
      { selector: "#archive", component: Homepage },
      { selector: "#single-lowongan", component: SingleLowongan },
      { selector: "#pasang-iklan-loker", component: PasangIklanLoker },
    ];

    await this.mounter.mount(appConfigs);
  }
}