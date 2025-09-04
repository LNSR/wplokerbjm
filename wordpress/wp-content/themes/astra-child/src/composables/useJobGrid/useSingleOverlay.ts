import { ref, watch, onMounted, type Ref } from 'vue'
import { debounce } from '@/utils'
import type { SingleOverlayResponse } from '@/types'
import { useApi } from '../useAPI'
import { useRoute } from 'vue-router'
import { AuthService } from '@/services/AuthService'

export function useSingleOverlay(props: { slug?: string; visible?: boolean }): {
  data: Ref<SingleOverlayResponse | null>;
  loading: Ref<boolean>;
  error: Ref<string | null>;
  isLoggedIn: Ref<boolean>;
  editPostId: Ref<number | null>;
  useSingleOverlayAPI: (slug: string) => Promise<void>;
} {
  const data = ref<SingleOverlayResponse | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const isLoggedIn = ref(false)
  const editPostId = ref<number | null>(null)

  const { fetchSingleOverlay } = useApi()
  const route = useRoute()


  async function useSingleOverlayAPI(slug: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      data.value = await fetchSingleOverlay(slug)
      if (isLoggedIn.value) {
        editPostId.value = data.value?.id ?? null
      }
    } catch (err) {
      console.error('Error fetching single overlay:', err)
      error.value = 'Failed to load job details. Please try again later.'
      data.value = null
      editPostId.value = null
    } finally {
      loading.value = false
    }
  }

  const debouncedUseSingleOverlayAPI = debounce(useSingleOverlayAPI, 300)

  function fetchJob(): void {
    if (props.visible && props.slug) {
      debouncedUseSingleOverlayAPI(props.slug)
    } else if (route.params['slug']) {
      const slugParam = Array.isArray(route.params['slug']) ? route.params['slug'][0] : route.params['slug']
      if (slugParam) {
        debouncedUseSingleOverlayAPI(slugParam)
      }
    }
  }

  onMounted(() => {
    isLoggedIn.value = AuthService.isUserLoggedIn()
    fetchJob()
  })

  watch(
    () => props.slug,
    () => {
      fetchJob()
    },
    { immediate: true }
  )

  watch(
    () => props.visible,
    fetchJob
  )

  return {
    data,
    loading,
    error,
    isLoggedIn,
    editPostId,
    useSingleOverlayAPI: debouncedUseSingleOverlayAPI,
  }
}