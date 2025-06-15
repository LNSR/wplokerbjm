import { createApp, type App, defineAsyncComponent } from 'vue'
import { createPinia } from 'pinia'
import type { Pinia } from 'pinia'

export const pinia = createPinia()
export { defineAsyncComponent }

export function createVueApp(component: any, props: any = {}, pinia: Pinia): App {
  const app = createApp(component, props)
  app.use(pinia)
  app.config.errorHandler = (err, info) => {
    console.error('Vue Error:', err, info)
  }
  return app
}