import { ref, onMounted, onUnmounted, watch, type Ref } from 'vue'
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

  onMounted(updateTimeAgo)
  onUnmounted(() => {
    if (timeoutId) clearTimeout(timeoutId)
  })

  watch(() => postTime, () => {
    updateTimeAgo()
  })

  return { timeAgo }
}