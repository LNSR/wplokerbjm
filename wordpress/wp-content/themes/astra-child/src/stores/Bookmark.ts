import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { JobService } from '@/services/APIService'
import { saveBookmarks, loadBookmarks, clearBookmarks } from '@/utils/indexedDB'
import type { CardJob } from '@/types/Component'
import { debounce } from '@/utils/debounce'

/**
 * 
 */
export const useSavedJobsStore = defineStore('savedJobs', () => {
  const jobs = ref<CardJob[]>([])
  const isInitialized = ref(false)
  const isSyncing = ref(false)
  const warning = ref('')
  const cache = new Map<number, CardJob>()
  const deletedJobs = ref<number[]>([])
  const lastSyncTime = ref<number>(0)

  // Cross-tab sync using BroadcastChannel
  const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('bookmark-sync') : null
  if (channel) {
    channel.onmessage = (event: MessageEvent): void => {
      const data = event.data
      if (data.type === 'sync') {
        loadFromStorage()
        deletedJobs.value = data.deleted
      } else {
        loadFromStorage()
        setTimeout(() => {
          syncWithAPI()
        }, 1000)
      }
    }
  }

  // Load from IndexedDB on init
  const loadFromStorage = async (): Promise<void> => {
    try {
      cache.clear()
      const stored = await loadBookmarks()
      stored.forEach(job => cache.set(job.id!, job))
      jobs.value = Array.from(cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
    } catch {
      jobs.value = []
    } finally {
      isInitialized.value = true
    }
  }

  // Save to IndexedDB
  const saveToStorage = async (): Promise<void> => {
    try {
      await saveBookmarks(Array.from(cache.values()).map(job => JSON.parse(JSON.stringify(job))))
    } catch (error) {
      console.error('Failed to save bookmarks to IndexedDB:', error)
    }
  }

  // Add job
  const addJob = async (id: number): Promise<void> => {
    try {
      const job: CardJob = { id, title: '' }
      const clonedJob = JSON.parse(JSON.stringify(job))
      cache.set(clonedJob.id!, clonedJob)
      jobs.value = Array.from(cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
      await saveToStorage()
      if (channel) channel.postMessage('update')
      debouncedSync()
    } catch (error) {
      console.error('Failed to add job:', error)
      warning.value = 'Gagal menambahkan bookmark. Silakan coba lagi.'
      // Revert cache
      cache.delete(id)
      jobs.value = Array.from(cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
    }
  }

  // Remove job
  const removeJob = async (id: number): Promise<void> => {
    try {
      cache.delete(id)
      jobs.value = Array.from(cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
      deletedJobs.value = deletedJobs.value.filter(i => i !== id)
      await saveToStorage()
      if (channel) channel.postMessage('update')
    } catch (error) {
      console.error('Failed to remove job:', error)
      warning.value = 'Gagal menghapus bookmark. Silakan coba lagi.'
    }
  }

  // Check if saved
  const isSaved = (id: number): boolean => jobs.value.some(job => job.id === id)

  // Get saved jobs
  const getSavedJobs = (): CardJob[] => jobs.value

  // Sync with API in background
  const syncWithAPI = async (): Promise<void> => {
    if (isSyncing.value) {
      warning.value = 'Data tidak sinkron. Tolong refresh ulang.'
      syncWithAPI()
      return
    }
    warning.value = ''
    isSyncing.value = true
    try {
      const previousIds = new Set(jobs.value.map(job => job.id || 0))
      const ids = jobs.value.map(job => job.id || 0)
      const fetchedJobs = await JobService.syncBookmark(ids)
      const plainFetchedJobs: CardJob[] = JSON.parse(JSON.stringify(fetchedJobs))
      cache.clear()
      plainFetchedJobs.forEach((job: CardJob) => cache.set(job.id!, job))
      jobs.value = Array.from(cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
      await saveToStorage()
      const currentIds = new Set(jobs.value.map(job => job.id || 0))
      deletedJobs.value = [...previousIds].filter(id => !currentIds.has(id))
      lastSyncTime.value = Date.now()
      if (channel) channel.postMessage({ type: 'sync', deleted: JSON.parse(JSON.stringify(deletedJobs.value)) })
    } catch (error) {
      console.error('Failed to sync bookmarks with API:', error)
    } finally {
      isSyncing.value = false
    }
  }

  // Debounced sync to handle rapid bookmarking - collects IDs over 3 seconds then syncs
  const debouncedSync = debounce(syncWithAPI, 3000)

  // Clear all jobs
  const clearAll = async (): Promise<void> => {
    try {
      jobs.value = []
      cache.clear()
      deletedJobs.value = []
      await clearBookmarks()
      if (channel) channel.postMessage('update')
    } catch (error) {
      console.error('Failed to clear all bookmarks:', error)
      warning.value = 'Gagal menghapus semua bookmark. Silakan coba lagi.'
    }
  }

  // Clear deleted jobs list
  const clearDeleted = (): void => {
    deletedJobs.value = []
  }

  // Initialize and sync in background
  const initialize = async (): Promise<void> => {
    await loadFromStorage()
    syncWithAPI()
  }

  initialize()

  return {
    jobs: computed(() => jobs.value),
    isInitialized: computed(() => isInitialized.value),
    warning: computed(() => warning.value),
    deletedJobs: computed(() => deletedJobs.value),
    lastSyncTime: computed(() => lastSyncTime.value),
    addJob,
    removeJob,
    isSaved,
    getSavedJobs,
    syncWithAPI,
    clearAll,
    clearDeleted,
    flushSync: (): void => debouncedSync.flush(),
    cancelSync: (): void => debouncedSync.cancel()
  }
})
