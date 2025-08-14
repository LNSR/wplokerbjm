import "reflect-metadata";
import { Container } from "inversify";
import { createPinia } from "pinia";
import { AppRouter } from "@/app";
import { createApp } from "vue";
import { SocialMediaService } from "@/services/SosialMediaService";
import { MounterService } from "@/services/MounterService";
import { VueAppFactory } from "@/app/Factory";
import { ComponentMounter } from "@/app/Mounter";
import { ApiClient } from "@/api/Client";
import { MountApp } from "@/app/entry/componentLoader";
const container = new Container({ autobind: true });

container.bind("CreateApp").toConstantValue(createApp);
container
  .bind("Pinia")
  .toDynamicValue(() => createPinia())
  .inSingletonScope();
container.bind("SocialMediaService").to(SocialMediaService).inSingletonScope();
container.bind("AppRouter").to(AppRouter).inSingletonScope();
container.bind("VueAppFactory").to(VueAppFactory).inSingletonScope();
container.bind("ComponentMounter").to(ComponentMounter).inSingletonScope();
container.bind("MounterService").to(MounterService).inSingletonScope();
container.bind("ApiClient").to(ApiClient).inSingletonScope();
container.bind("MountApp").to(MountApp).inSingletonScope();

export {
  container,
  MountApp,
  ComponentMounter,
  SocialMediaService,
  MounterService,
  AppRouter,
  ApiClient,
};
