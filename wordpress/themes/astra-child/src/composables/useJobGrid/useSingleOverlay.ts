import { ref } from 'vue'
import { debounce } from '@/utils'
import type { SingleOverlayResponse } from '@/types'
import { useApi } from '../useApi'

export function useSingleOverlay() {
  const data = ref<SingleOverlayResponse | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const { fetchSingleOverlay } = useApi()


  async function useSingleOverlayAPI(id: number) {
    loading.value = true
    error.value = null
    try {
      data.value = await fetchSingleOverlay(id)
    } finally {
      loading.value = false
    }
  }

  const debouncedUseSingleOverlayAPI = debounce(useSingleOverlayAPI, 300)

  return {
    data,
    loading,
    error,
    useSingleOverlayAPI: debouncedUseSingleOverlayAPI,
  }
}