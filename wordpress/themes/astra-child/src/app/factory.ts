import { createApp, type App, defineAsyncComponent } from 'vue'
import { createPinia } from 'pinia'
import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'

export const pinia = createPinia()
export { defineAsyncComponent }

export function createVueApp(
  component: any,
  props: any = {},
  pinia: Pinia,
  router?: Router
): App {
  const app = createApp(component, props)
  app.use(pinia)
  if (router) app.use(router)
  app.config.errorHandler = (err, info) => {
    console.error('Vue Error:', err, info)
  }
  return app
}