<template>
  <section class="relative">
    <h2 v-if="jobs.length" class="text-xl font-semibold !mb-6">{{ title }}</h2>
    <div v-if="jobs.length && searchStore.context !== 'latest'" class="text-base font-medium mb-4">
      {{ totalJobs }} lowongan ditemukan
    </div>
    <div class="relative flex">
      <div
        :class="[
          'transition-all duration-300 ',
          drawerOpen ? 'w-full lg:w-[calc(100%-420px)]' : 'w-full'
        ]"
      >
        <div
          v-if="jobs.length"
        >
          <transition-group name="jobcard-fade" tag="div"
            :class="[
              'grid gap-6 job-grid-transition',
              drawerOpen
                ? 'grid-cols-1 md:grid-cols-1 lg:grid-cols-1'
                : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3'
            ]"
          >
            <JobCard
              v-for="job in jobs"
              :key="job.permalink"
              :jobdata="job"
              variant="featured"
              :permalink="job.permalink ?? ''"
              :selected="selectedId === job.id"
              @click="(id, _event, offsetTop) => openDrawer(id, offsetTop)"
              style="cursor:pointer"
            />
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
      <!-- Right: Overlay Drawer (desktop only) -->
      <div
        v-if="drawerOpen && selectedId !== null && selectedId !== undefined"
        class="hidden md:block relative w-full"
      >
        <SingleOverlay
          :id="selectedId"
          :visible="drawerOpen"
          :offset="overlayOffset"
          @close="drawerOpen = false"
        />
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, defineAsyncComponent, watch, nextTick } from 'vue'
import { useSearchStore } from '@/stores/search'
import JobCard from '@/components/homepage/JobCard.vue'
const SingleOverlay = defineAsyncComponent(() => import('@/components/homepage/JobGrid/SingleOverlay.vue'))
import type { Job, SearchFilters } from '@/types'

const props = defineProps<{
  jobs?: Job[]
  maxNumPages?: number
  context?: 'search' | 'archive'
  filters?: Partial<SearchFilters>
  title?: string
  totalJobs?: number
}>()

const searchStore = useSearchStore()
const jobs = computed(() => searchStore.jobs)
const loading = computed(() => searchStore.loading)
const hasMore = computed(() => searchStore.hasMore)
const loadMore = searchStore.loadMore


const hydrated = ref(false)

onMounted(() => {
  if (!hydrated.value && props.jobs && props.jobs.length) {
    searchStore.jobs = [...props.jobs]
    hydrated.value = true
    if (props.maxNumPages) searchStore.maxNumPages = props.maxNumPages
    if (props.context) searchStore.context = props.context
    if (props.title) searchStore.title = props.title
    if (props.totalJobs !== undefined) searchStore.totalJobs = props.totalJobs
  }
  createObserver()
})

const totalJobs = computed(() => searchStore.totalJobs)
const title = computed(() => searchStore.title)

const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

function createObserver() {
  if (observer) observer.disconnect()
  observer = new window.IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && hasMore.value && !loading.value) {
        loadMore()
      }
    },
    { root: null, rootMargin: '0px', threshold: 0.1 }
  )
  if (sentinel.value) observer.observe(sentinel.value)
}

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})

// Drawer logic
const drawerOpen = ref(false)
const selectedId = ref<number | null>(null)
const overlayOffset = ref(0)
function openDrawer(id: number, offsetTop?: number) {
  selectedId.value = id
  drawerOpen.value = true
  overlayOffset.value = offsetTop ?? 0
}

// Recalculate offset when transitioning to single grid (drawer opens)
watch(drawerOpen, async (val) => {
  if (val && selectedId.value !== null) {
    await nextTick()
    setTimeout(() => {
      const cardEl = document.querySelector(`[data-job-id="${selectedId.value}"]`)
      const gridContainer = cardEl?.closest('.relative.flex')
      if (cardEl && gridContainer) {
        const cardRect = cardEl.getBoundingClientRect()
        const gridRect = gridContainer.getBoundingClientRect()
        overlayOffset.value = cardRect.top - gridRect.top
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0
        cardEl.scrollIntoView({
          behavior: isTouchDevice ? 'auto' : 'smooth',
          block: 'center'
        })
      }
    }, 500)
  }
})
</script>