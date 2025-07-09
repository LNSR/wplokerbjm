import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useSearchStore } from '@/stores/search'
import { useJobOverlayStore } from '@/stores/job-overlay'
import { useRouter, useRoute } from 'vue-router'
import type { Job, SearchFilters } from '@/types'

export function useJobGrid(props: {
  jobs?: Job[]
  maxNumPages?: number
  context?: 'search' | 'archive'
  filters?: Partial<SearchFilters>
  title?: string
  totalJobs?: number
}) {
  const searchStore = useSearchStore()
  const jobs = computed(() => searchStore.jobs)
  const loading = computed(() => searchStore.loading)
  const hasMore = computed(() => searchStore.hasMore)
  const loadMore = searchStore.loadMore

  const router = useRouter()
  const route = useRoute()

  const hydrated = ref(false)
  const sentinel = ref<HTMLElement | null>(null)
  let observer: IntersectionObserver | null = null

  const jobOverlay = useJobOverlayStore()
  const overlayOpen = computed(() => jobOverlay.overlayOpen)
  const selectedId = computed(() => jobOverlay.selectedId)
  const overlayOffset = computed(() => jobOverlay.overlayOffset)

  const scrollBehavior = ref<'auto' | 'smooth'>('auto')

  const totalJobs = computed(() => searchStore.totalJobs)
  const title = computed(() => searchStore.title)

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

  function updateOverlayOffset(id: number) {
    const cardEl = document.querySelector(`[data-job-id="${id}"]`)
    const gridContainer = cardEl?.closest('.relative.flex')
    if (cardEl && gridContainer) {
      const cardRect = cardEl.getBoundingClientRect()
      const gridRect = gridContainer.getBoundingClientRect()
      jobOverlay.overlayOffset = cardRect.top - gridRect.top
    }
  }

  function openOverlay(id: number, offsetTop?: number) {
    jobOverlay.openOverlay(id, offsetTop)
    scrollBehavior.value = 'smooth'

    const job = jobs.value.find(j => j.id === id)
    if (job && job.permalink && window.innerWidth >= 768) {
      const url = new URL(job.permalink, window.location.origin)
      router.replace(url.pathname + url.search + url.hash)
    }

    nextTick(() => {
      setTimeout(() => {
        updateOverlayOffset(id)
        const cardEl = document.querySelector(`[data-job-id="${id}"]`)
        cardEl?.scrollIntoView({
          behavior: scrollBehavior.value,
          block: 'start'
        })
        scrollBehavior.value = 'auto'
      }, 400)
    })
  }

  function handleOverlayClose() {
    jobOverlay.closeOverlay()
    if (window.innerWidth >= 768) {
      router.push('/')
    }
  }

  function handleJobClick(job: Job) {
    if (!job.permalink) return
    if (window.innerWidth >= 768) {
      openOverlay(job.id)
    } else {
      try {
        const url = new URL(job.permalink, window.location.origin)
        if (url.host === window.location.host) {
          window.location.assign(url.pathname + url.search + url.hash)
        } else {
          window.location.href = job.permalink
        }
      } catch {
        window.location.href = job.permalink
      }
    }
  }

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

  onBeforeUnmount(() => {
    if (observer) observer.disconnect()
  })

  watch(overlayOpen, async (val) => {
    if (val && selectedId.value !== null) {
      await nextTick()
      setTimeout(() => {
        updateOverlayOffset(selectedId.value!)
      }, 400)
    }
  })

  watch(
    () => route.fullPath,
    (newPath) => {
      if (!newPath.includes('/lowongan/')) {
        jobOverlay.closeOverlay()
      }
    }
  )

  watch(
    () => searchStore.jobs,
    () => {
      if (overlayOpen.value) {
        jobOverlay.closeOverlay()
      }
    }
  )

  return {
    jobs,
    loading,
    hasMore,
    loadMore,
    searchStore,
    hydrated,
    sentinel,
    overlayOpen,
    selectedId,
    overlayOffset,
    scrollBehavior,
    totalJobs,
    title,
    handleOverlayClose,
    handleJobClick,
    createObserver,
    updateOverlayOffset
  }
}