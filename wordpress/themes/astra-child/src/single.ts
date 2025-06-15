import '@assets/css/tailwind.css'
import { ComponentMounter } from '@/app/mounter'
import FloatingActionButton from '@/components/FloatingActionButton.vue'
import ColorSwitchButton from '@/components/ColorSwitchButton.vue'
import type { ComponentConfig } from '@/types'

async function mountSingleComponents() {
  const singleConfigs: ComponentConfig[] = [
    { selector: '#floating-action-button', component: FloatingActionButton },
    { selector: '#color-switch-button', component: ColorSwitchButton },
  ]
  await ComponentMounter(singleConfigs)
}
document.addEventListener('DOMContentLoaded', async () => {
  await mountSingleComponents()
})