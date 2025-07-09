import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useJobOverlayStore = defineStore('jobOverlay', () => {
  const overlayOpen = ref(false)
  const selectedId = ref<number | null>(null)
  const overlayOffset = ref(0)

  function openOverlay(id: number, offsetTop?: number) {
    selectedId.value = id
    overlayOpen.value = true
    overlayOffset.value = offsetTop ?? 0
  }

  function closeOverlay() {
    overlayOpen.value = false
    selectedId.value = null
    overlayOffset.value = 0
  }

  return {
    overlayOpen,
    selectedId,
    overlayOffset,
    openOverlay,
    closeOverlay,
  }
})