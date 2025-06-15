import { createVueApp, pinia, defineAsyncComponent } from '@/app/factory'
import type { ComponentConfig } from '@/types'

export async function ComponentMounter(configs: ComponentConfig[] = []) {
  await Promise.all(
    configs.map(async config => {
      const elements = document.querySelectorAll(config.selector)
      await Promise.all(Array.from(elements).map(async element => {
        try {
          const props = JSON.parse(element.getAttribute('data-props') || '{}')
          element.removeAttribute('data-props')
          if (config.onMount) {
            await config.onMount(element, props)
          } else {
            const component =
              typeof config.component === 'function'
                ? defineAsyncComponent(config.component)
                : config.component
            await createVueApp(component, props, pinia).mount(element)
          }
        } catch (error) {
          console.error(`Failed to mount component at ${config.selector}:`, error)
        }
      }))
    })
  )
}
