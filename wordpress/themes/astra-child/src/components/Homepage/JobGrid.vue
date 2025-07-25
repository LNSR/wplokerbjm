<template>
  <section class="relative">
    <h2 v-if="jobs.length" class="text-xl font-semibold !mb-6">{{ title }}</h2>
    <div v-if="jobs.length && searchStore.context !== 'latest'" class="text-base font-medium mb-4">
      {{ totalJobs }} lowongan ditemukan
    </div>
    <div class="relative flex">
      <div :class="[
        'transition-all duration-300',
        overlayOpen ? 'w-full lg:w-[calc(100%-420px)]' : 'w-full'
      ]">
        <div v-if="jobs.length">
          <transition-group name="jobcard-fade" tag="div" :class="[
            'grid gap-6 job-grid-transition',
            overlayOpen
              ? 'grid-cols-1 md:grid-cols-1 lg:grid-cols-1'
              : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3'
          ]">
            <JobCard v-for="job in jobs" :key="job.permalink" :jobdata="job" variant="featured"
              :permalink="job.permalink ?? ''" :selected="selectedId === job.id" @click="() => handleJobClick(job)"
              style="cursor:pointer" />
          </transition-group>
        </div>
        <div v-else class="text-center py-12">
          <h2 class="text-2xl font-semibold !mb-6">Tidak ada lowongan ditemukan.</h2>
          <p>Coba gunakan kata kunci atau filter lain.</p>
        </div>
        <div v-show="loading" class="flex justify-center mt-8">
          <span class="sr-only">Memuat...</span>
          <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
        </div>
        <div ref="sentinel" style="height: 1px"></div>
      </div>
      <div v-if="overlayOpen && selectedId !== null && selectedId !== undefined" class="hidden md:block w-full"
        :class="[
          overlayOpen ? 'sticky top-0 self-start' : 'relative'
        ]"
        :style="{ top: wpAdminBarOffset }"
      >
        <SingleOverlay :id="selectedId" :visible="overlayOpen" @close="handleOverlayClose" />
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { Job, SearchFilters, SearchContext } from '@/types'
import { useJobGrid } from '@/composables/useJobGrid'
import JobCard from '@/components/Homepage/JobCard.vue'
import SingleOverlay from '@/components/Homepage/JobGrid/SingleOverlay.vue'
const props = defineProps<{
  jobs?: Job[]
  maxNumPages?: number
  context?: SearchContext
  filters?: Partial<SearchFilters>
  title?: string
  totalJobs?: number
}>()
const {
  jobs,
  loading,
  sentinel,
  overlayOpen,
  selectedId,
  totalJobs,
  title,
  handleOverlayClose,
  handleJobClick,
  searchStore,
  wpAdminBarOffset
} = useJobGrid(props)

</script>
