import { ref } from 'vue'
import { JobService } from '@/services/JobService'
import { debounce } from '@/utils'
import type { SingleOverlayResponse } from '@/types'

export function useSingleOverlay() {
  const data = ref<SingleOverlayResponse | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSingleOverlay(id: number) {
    loading.value = true
    error.value = null
    try {
      data.value = await JobService.fetchSingleOverlay(id)
    } catch (e: unknown) {
      error.value = (e as Error)?.message || 'Failed to load job overlay'
      data.value = null
    } finally {
      loading.value = false
    }
  }

  const debouncedFetchSingleOverlay = debounce(fetchSingleOverlay, 300)

  return {
    data,
    loading,
    error,
    fetchSingleOverlay: debouncedFetchSingleOverlay,
  }
}