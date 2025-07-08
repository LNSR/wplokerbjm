import '@assets/css/tailwind.css'
import { ComponentMounter } from '@/app/mounter'
import type { ComponentConfig } from '@/types'

async function mountHomepageComponents() {
  const homepageConfigs: ComponentConfig[] = [
    { selector: '#search-form', component: () => import('@/components/homepage/SearchForm.vue') },
    { selector: '#job-grid', component: () => import('@/components/homepage/JobGrid.vue') },
    { selector: '#job-carousel', component: () => import('@/components/homepage/JobCarousel.vue') },
    { selector: '#floating-action-button', component: () => import('@/components/FloatingActionButton.vue') },
    { selector: '#color-switch-button', component: () => import('@/components/ColorSwitchButton.vue') },
  ]
  await ComponentMounter(homepageConfigs)
}

document.addEventListener('DOMContentLoaded', async () => {
  await mountHomepageComponents()
})