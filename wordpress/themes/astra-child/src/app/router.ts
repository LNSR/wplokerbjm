import { createRouter, createWebHistory } from 'vue-router'


const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/components/homepage/JobGrid.vue'),
  },
  {
    path: '/lowongan/:slug',
    name: 'JobDetail',
    component: () => import('@/components/homepage/JobGrid/SingleOverlay.vue'),
    props: true,
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.afterEach((to) => {
  if (window.dataLayer && typeof window.dataLayer.push === 'function') {
    window.dataLayer.push({
      event: 'page_view',
      page_path: to.fullPath,
      page_location: window.location.href,
      page_title: document.title,
    });
  }

  if (typeof window.gtag === 'function') {
    window.gtag('event', 'page_view', {
      page_path: to.fullPath,
      page_location: window.location.href,
      page_title: document.title,
    })
  }
})