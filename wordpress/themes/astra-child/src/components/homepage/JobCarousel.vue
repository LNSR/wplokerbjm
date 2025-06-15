<script setup lang="ts">
import { ref } from 'vue'
import type { Job } from '@/types/job'
import { useJobCarousel } from '@/composables/useSwiper'
import { useApi } from '@/composables/useApi'

const jobs = ref<Job[]>([])
const loaded = ref(false)
const { loading, fetchCarousel } = useApi()

// Use the new composable logic for mounting and loading
useJobCarousel({
  jobs,
  loaded,
  fetchCarousel,
  loading,
})
</script>
<template>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold !mt-4 !mb-0">Lowongan Darurat</h2>
    <div class="hidden sm:flex gap-1">
      <button type="button" class="job-carousel-prev rounded-xs hover:bg-gray-100 transition">
        <!-- Left Arrow SVG -->
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
          <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <button type="button" class="job-carousel-next rounded-xs hover:bg-gray-100 transition">
        <!-- Right Arrow SVG -->
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
          <path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>
  <div v-if="loading" class="text-center py-8">Memuat...</div>
  <div v-else-if="jobs.length" class="swiper job-carousel invisible" id="job-carousel">
    <div class="swiper-wrapper">
      <!-- Virtual slides will be dynamically created by Swiper -->
    </div>
    <div class="flex justify-center !mt-24">
      <div class="swiper-pagination"></div>
    </div>
  </div>
  <p v-else class="text-center text-gray-500">Belum ada lowongan unggulan.</p>
</template>