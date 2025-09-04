import { watch } from "vue";
import { useRouter } from "vue-router";
import { useJobOverlayStore } from "@/stores";
import { RouterService } from '@/services/RouterService'
import type { CardJob } from "@/types";

/**
 * Syncs SingleOverlay state with the current route params.
 * Opens the overlay when route.params.slug exists. The overlay can later
 * resolve the selected job when jobs hydrate.
 */
export function useRouterOverlayWatcher(jobsRef: { value: CardJob[] }): void {
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
            jobOverlay.openOverlay(slug, job)
          }
        }
      } else {
        jobOverlay.closeOverlay()
      }
    },
    { immediate: true }
  )
}
