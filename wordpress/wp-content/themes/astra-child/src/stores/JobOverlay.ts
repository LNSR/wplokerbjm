import { defineStore } from "pinia";
import { ref } from "vue";
import type { Job } from "@/types";
export const useJobOverlayStore = defineStore("jobOverlay", () => {
  const overlayOpen = ref(false);
  const selectedSlug = ref<string | null>(null);
  const selectedJob = ref<Job | null>(null);

  function openOverlay(slug: string, job?: Job): void {
    selectedSlug.value = slug;
    if (job) {
      selectedJob.value = job;
    } else {
      selectedJob.value = null;
    }
    overlayOpen.value = true;
  }

  function closeOverlay(): void {
    overlayOpen.value = false;
    selectedSlug.value = null;
    selectedJob.value = null;
  }

  return {
    overlayOpen,
    selectedSlug,
    selectedJob,
    openOverlay,
    closeOverlay
  };
});
