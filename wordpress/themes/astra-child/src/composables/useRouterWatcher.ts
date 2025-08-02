import { watch } from 'vue'
import { useRouter } from 'vue-router'
import { useJobOverlayStore } from '@/stores/JobOverlay'
import { RouterService } from '@/services/RouterService'
import type { Job } from '@/types'

/**
 * Syncs SingleOverlay state with the current route url.
 * Remembering browser history navigation while opening the overlay
 */
export function useRouterWatcher(jobsRef: { value: Job[] }) {
  const router = useRouter()
  const jobOverlay = useJobOverlayStore()

  watch(
    () => router.currentRoute.value.fullPath,
    (newPath) => {
      if (newPath.startsWith('/lowongan/')) {
        const slug = RouterService.getJobSlugFromRoute(newPath)
        if (slug) {
          const job = jobsRef.value.find(
            (j) => j.permalink && j.permalink.includes(`/lowongan/${slug}`)
          )
          if (job) {
            jobOverlay.openOverlay(slug)
          }
        }
      } else {
        jobOverlay.closeOverlay()
      }
    },
    { immediate: true }
  )
}