import { ref, watch, onMounted } from 'vue'
import { debounce } from '@/utils'
import type { SingleOverlayResponse } from '@/types'
import { useApi } from '../useAPI'
import { useRoute } from 'vue-router'
import { AuthService } from '@/services/AuthService'

export function useSingleOverlay(props: { slug?: string; visible?: boolean }) {
  const data = ref<SingleOverlayResponse | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const isLoggedIn = ref(false)
  const editPostId = ref<number | null>(null)

  const { fetchSingleOverlay } = useApi()
  const route = useRoute()

  async function fetchPostIdBySlug(slug: string): Promise<number | null> {
    try {
      const res = await fetch(`/wp-json/wp/v2/lowongan?slug=${encodeURIComponent(slug)}`)
      if (!res.ok) return null
      const data = await res.json()
      if (Array.isArray(data) && data.length > 0 && data[0].id) {
        return data[0].id
      }
      return null
    } catch {
      console.error('Error fetching post ID by slug:', slug)
      return null
    }
  }

  async function useSingleOverlayAPI(slug: string) {
    loading.value = true
    error.value = null
    try {
      data.value = await fetchSingleOverlay(slug)
    } catch (err) {
      console.error('Error fetching single overlay:', err)
      error.value = 'Failed to load job details. Please try again later.'
      data.value = null
    } finally {
      loading.value = false
    }
  }

  const debouncedUseSingleOverlayAPI = debounce(useSingleOverlayAPI, 300)

  function fetchJob() {
    if (props.visible && props.slug) {
      debouncedUseSingleOverlayAPI(props.slug)
    } else if (route.params['slug']) {
      const slugParam = Array.isArray(route.params['slug']) ? route.params['slug'][0] : route.params['slug']
      if (slugParam) {
        debouncedUseSingleOverlayAPI(slugParam)
      }
    }
  }

  onMounted(async () => {
    isLoggedIn.value = AuthService.isUserLoggedIn()
    if (isLoggedIn.value && props.slug) {
      editPostId.value = await fetchPostIdBySlug(props.slug)
    }
    fetchJob()
  })

  watch(
    () => props.slug,
    async (newSlug) => {
      if (isLoggedIn.value && newSlug) {
        editPostId.value = await fetchPostIdBySlug(newSlug)
      } else {
        editPostId.value = null
      }
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
    fetchPostIdBySlug,
  }
}