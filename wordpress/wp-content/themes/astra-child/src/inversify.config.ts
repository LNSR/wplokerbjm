import "reflect-metadata";
import { Container } from "inversify";
import { createPinia } from "pinia";
import { createApp } from "vue";
import { VueAppFactory, ComponentMounter, AppRouter } from "@/app";
import { ApiClient } from "@/api";
import { createRouter, createWebHistory } from "vue-router";
const container = new Container({ autobind: true });

container.bind("CreateApp").toConstantValue(createApp);
container
  .bind("Pinia")
  .toDynamicValue(() => createPinia())
  .inSingletonScope();
container.bind("AppRouter").to(AppRouter).inSingletonScope();
container.bind("VueAppFactory").to(VueAppFactory).inSingletonScope();
container.bind("ComponentMounter").to(ComponentMounter).inSingletonScope();
container.bind("ApiClient").to(ApiClient).inTransientScope();
container.bind("CreateRouter").toConstantValue(createRouter);
container.bind("CreateWebHistory").toConstantValue(createWebHistory);

export {
  container,
};
