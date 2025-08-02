import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useJobOverlayStore = defineStore('jobOverlay', () => {
  const overlayOpen = ref(false)
  const selectedSlug = ref<string | null>(null);

  function openOverlay(slug: string) {
    selectedSlug.value = slug;
    overlayOpen.value = true;
  }

  function closeOverlay() {
    overlayOpen.value = false;
    selectedSlug.value = null;
  }

  return {
    overlayOpen,
    selectedSlug,
    openOverlay,
    closeOverlay,
  }
})