import type { App } from 'vue'
import { type Pinia } from 'pinia'
import { injectable, inject } from 'inversify'
import type { AppRouter } from './Router'

@injectable()
export class VueAppFactory {
  constructor(
    @inject('CreateApp') private createApp: (component: any, props?: any) => App,
    @inject('Pinia') private pinia: Pinia,
    @inject('AppRouter') private AppRouter?: AppRouter 
  ) {}

  create(component: any, props: any): App {
    const app = this.createApp(component, props)
    app.use(this.pinia)
    if (this.AppRouter) app.use(this.AppRouter.router)
    app.config.errorHandler = (err, info) => {
      console.error('Vue Error:', err, info)
    }
    return app
  }
}