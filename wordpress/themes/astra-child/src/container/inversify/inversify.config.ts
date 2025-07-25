import 'reflect-metadata'
import { Container } from 'inversify'
import { createPinia } from 'pinia'
import { AppRouter } from '@/app/Router'
import { createApp } from 'vue'

const container = new Container({ autobind: true })

container.bind('CreateApp').toConstantValue(createApp)
container.bind('Pinia').toConstantValue(createPinia())
container.bind('AppRouter').toDynamicValue(() => new AppRouter()).inSingletonScope()

export { container }