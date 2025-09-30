import { ref, onUnmounted, type Ref } from 'vue'
import { TimeService } from '@/services/TimeService'

export function useTimeAgo(postTime: string | undefined): {
  timeAgo: Ref<string>;
} {
  const timeAgo = ref('')
  let timeoutId: ReturnType<typeof setTimeout> | null = null

  function updateTimeAgo(): void {
    const { text, nextUpdate } = TimeService.getTimeAgo(postTime)
    timeAgo.value = text

    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = setTimeout(updateTimeAgo, nextUpdate)
  }

  updateTimeAgo()

  // * Note: onUnmounted is included for proper cleanup in components like JobCard.vue and JobDetail.vue,
  // * where useTimeAgo is called during setup and components unmount frequently (e.g., during live search).
  // * However, this causes a Vue warning in BookmarkedModal.vue because useTimeAgo is called inside
  // * a computed property (displayedSavedJobs), which runs outside of setup and doesn't have its own lifecycle.
  // * The warning is harmless, and onUnmounted still works for the modal's overall unmounting.
  // * If the warning becomes an issue, we can remove onUnmounted and accept minor timeout buildup.
  onUnmounted(() => {
    if (timeoutId) clearTimeout(timeoutId)
  })

  return { timeAgo }
}