import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useJobOverlayStore = defineStore('jobOverlay', () => {
  const overlayOpen = ref(false)
  const selectedId = ref<number | null>(null)
  const selectedSlug = ref<string | null>(null)

  function openOverlay(id: number, slug?: string) {
    selectedId.value = id
    selectedSlug.value = slug ?? null
    overlayOpen.value = true
  }

  function closeOverlay() {
    overlayOpen.value = false
    selectedId.value = null
    selectedSlug.value = null
  }

  return {
    overlayOpen,
    selectedId,
    selectedSlug,
    openOverlay,
    closeOverlay,
  }
})