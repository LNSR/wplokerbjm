import { useSavedJobsStore } from '@/stores'
import type { CardJob, DisplayedJob } from '@/types/Component'
import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { useTimeAgo } from '@/composables/useTime'
import { useDeadline } from '@/composables/useDeadline'
import { useStatusJob } from '@/composables/useStatusJob'

export const useBookmark = (): {
  isSaved: (id: number) => boolean
  toggleSave: (id: number) => Promise<void>
  removeBookmark: (id: number) => Promise<void>
  clearAllBookmarks: () => Promise<void>
  sync: () => Promise<void>
  getSavedJobs: () => CardJob[]
} => {
  const store = useSavedJobsStore()

  const toggleSave = async (id: number): Promise<void> => {
    if (store.isSaved(id)) {
      await store.removeJob(id)
    } else {
      await store.addJob(id)
    }
  }

  const removeBookmark = async (id: number): Promise<void> => {
    await store.removeJob(id)
  }

  const clearAllBookmarks = async (): Promise<void> => {
    await store.clearAll()
  }

  const sync = (): Promise<void> => store.syncWithAPI()

  return {
    isSaved: store.isSaved,
    toggleSave,
    removeBookmark,
    clearAllBookmarks,
    sync,
    getSavedJobs: store.getSavedJobs
  }
}

export function useBookmarkedModal(): {
  loading: Ref<boolean>
  error: Ref<string>
  warning: Ref<string>
  savedJobs: ComputedRef<CardJob[]>
  deletedJobs: Ref<number[]>
  showCopySuccess: Ref<boolean>
  isOffline: Ref<boolean>
  lastSyncTime: Ref<number>
  showDeleteConfirm: Ref<boolean>
  removingIds: Ref<Set<number>>
  fetchJobs: (forceRefresh?: boolean) => Promise<void>
  flushSync: () => void
  handleRefresh: () => Promise<void>
  handleDeleteAll: () => void
  confirmDeleteAll: () => Promise<void>
  cancelDeleteAll: () => void
  removeBookmark: (id: number) => Promise<void>
  handleClearDeleted: () => void
  displayedSavedJobs: ComputedRef<DisplayedJob[]>
  formattedLastSync: ComputedRef<string>
} {
  const store = useSavedJobsStore()

  const loading = ref<boolean>(false)
  const error = ref<string>('')
  const warning = computed(() => store.warning)
  const savedJobs = computed(() => store.jobs)
  const deletedJobs = computed(() => store.deletedJobs)
  const lastSyncTime = computed(() => store.lastSyncTime)
  const showCopySuccess = ref<boolean>(false)
  const isOffline = ref<boolean>(false)
  const showDeleteConfirm = ref<boolean>(false)
  const removingIds = ref<Set<number>>(new Set())
  const STALE_THRESHOLD = 5 * 60 * 1000

  const fetchJobs = async (forceRefresh = false): Promise<void> => {
    if (!forceRefresh && store.jobs.length === 0) {
      return
    }
    const now = Date.now()
    const isStale = now - lastSyncTime.value > STALE_THRESHOLD
    if (!forceRefresh && !isStale && store.jobs.length > 0) return
    loading.value = true
    error.value = ''
    isOffline.value = false
    try {
      await store.syncWithAPI()
    } catch {
      error.value = 'Gagal memuat data. Silakan coba lagi.'
      isOffline.value = !navigator.onLine
    } finally {
      loading.value = false
    }
  }

  const handleRefresh = async (): Promise<void> => fetchJobs(true)
  const handleDeleteAll = (): void => { showDeleteConfirm.value = true }

  const confirmDeleteAll = async (): Promise<void> => {
    showDeleteConfirm.value = false
    savedJobs.value.forEach(job => { if (job.id) removingIds.value.add(job.id) })
    await new Promise(resolve => setTimeout(resolve, 300))
    loading.value = true
    try {
      await store.clearAll()
      removingIds.value.clear()
    } catch {
      error.value = 'Gagal menghapus semua bookmark. Silakan coba lagi.'
      removingIds.value.clear()
    } finally {
      loading.value = false
    }
  }

  const cancelDeleteAll = (): void => { showDeleteConfirm.value = false }

  const removeBookmark = async (id: number): Promise<void> => {
    removingIds.value.add(id)
    await new Promise(resolve => setTimeout(resolve, 300))
    await store.removeJob(id)
    removingIds.value.delete(id)
  }

  const handleClearDeleted = (): void => { store.clearDeleted() }

  const displayedSavedJobs = computed(() =>
    savedJobs.value.map(job => ({
      ...job,
      timeAgo: useTimeAgo(job.post_time).timeAgo.value,
      deadlineInfo: job.deadline ? useDeadline(job.deadline) : { text: '', style: '' },
      statusInfo: job.statusjob ? useStatusJob(Number(job.statusjob)) : { label: '', color: '' }
    }))
  )

  const formattedLastSync = computed(() => {
    const val = lastSyncTime.value
    const n = Number(val)
    if (!n || Number.isNaN(n)) return ''
    try {
      return new Date(n).toLocaleString('en-GB', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true
      })
    } catch {
      return ''
    }
  })

  return {
    loading,
    error,
    warning,
    savedJobs,
    deletedJobs,
    showCopySuccess,
    isOffline,
    lastSyncTime,
    showDeleteConfirm,
    removingIds,
    fetchJobs,
    handleRefresh,
    handleDeleteAll,
    confirmDeleteAll,
    cancelDeleteAll,
    flushSync: () => store.flushSync(),
    removeBookmark,
    handleClearDeleted,
    displayedSavedJobs,
    formattedLastSync
  }
}