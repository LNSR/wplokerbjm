import '@assets/css/tailwind.css'
import { ComponentMounter } from '@/app/mounter'
import SearchForm from '@/components/homepage/SearchForm.vue'
import JobGrid from '@/components/homepage/JobGrid.vue'
import JobCarousel from '@/components/homepage/JobCarousel.vue'
import FloatingActionButton from '@/components/FloatingActionButton.vue'
import ColorSwitchButton from '@/components/ColorSwitchButton.vue'
import type { ComponentConfig } from '@/types'

async function mountHomepageComponents() {
  const homepageConfigs: ComponentConfig[] = [
    { selector: '#search-form', component: SearchForm },
    { selector: '#job-grid', component: JobGrid },
    { selector: '#job-carousel', component: JobCarousel },
    { selector: '#floating-action-button', component: FloatingActionButton },
    { selector: '#color-switch-button', component: ColorSwitchButton },
  ]
  await ComponentMounter(homepageConfigs)
}

document.addEventListener('DOMContentLoaded', async () => {
  await mountHomepageComponents()
})