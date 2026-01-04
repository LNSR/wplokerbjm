import { debounce, bookmarkIDB, getThemeData } from '@/utils';
import { SvelteSet, SvelteMap } from 'svelte/reactivity'
import { APIService } from '@/services/APIService'
import type { CardJob, WPLokerBJMThemedData } from '@/types'

interface BookmarkBroadcastMessage {
    type: 'update' | 'sync' | 'reload'
    deleted?: number[]
    version?: WPLokerBJMThemedData['themeVersion']
}

export class BookmarkManager {
    // Current theme version from server (mtime of composer.json), used for cross-tab version checking
    private CURRENT_VERSION = getThemeData()?.themeVersion || 0
    public jobs = $state<CardJob[]>([])
    public isInitialized = $state(false)
    public isSyncing = $state(false)
    public warning = $state('')
    private cache = new SvelteMap<number, CardJob>()
    public deletedJobs = $state<number[]>([])
    public lastSyncTime = $state<number>(0)

    private channel: BroadcastChannel | null = null
    private debouncedSync: any
    private pendingSyncIds = new SvelteSet<number>()
    private _debouncedSaveCall: any = null
    private _pendingSavePromise: Promise<void> | null = null
    private _pendingSaveResolve: (() => void) | null = null
    private _pendingSaveReject: ((reason?: any) => void) | null = null
    private _saveInProgress = false
    private _retryTimer: ReturnType<typeof setTimeout> | null = null
    private operationQueue: Promise<any> = Promise.resolve()

    private async runQueued<T>(operation: () => Promise<T>): Promise<T> {
        await this.operationQueue
        this.operationQueue = operation()
        return this.operationQueue
    }

    constructor() {

        void this.initialize()
        this.crossTabChannel()
        this.debouncedSync = debounce(() => this.syncPending(), 1000)
    }

    /**
     * Setup cross-tab synchronization using BroadcastChannel.
     * 
     * This enables bookmarks to sync across multiple tabs/windows of the same origin.
     * - Only visible tabs process messages to avoid background interference.
     * - Version checking ensures only tabs with matching theme versions (from server) sync.
     * - If a newer version is detected, older tabs are forced to reload to prevent API conflicts.
     * - Messages include 'update' (for add/remove), 'sync' (for full sync), and 'reload' (force refresh).
     */
    public crossTabChannel(): void {
        if (typeof BroadcastChannel === 'undefined') {
            return
        }

        this.channel = new BroadcastChannel('bookmark-sync')
        this.channel.onmessage = (event: MessageEvent): void => {
            const data = event.data as BookmarkBroadcastMessage

            // Version mismatch: ignore messages from different versions
            // If incoming version is newer, force reload to update assets
            if (data.version !== undefined && data.version !== this.CURRENT_VERSION) {
                if (data.version > this.CURRENT_VERSION) {
                    location.replace(location.href) // Reload without history entry
                }
                return
            }

            // Handle different message types using type-safe switch
            switch (data.type) {
                case 'sync':
                    // Full sync: load from storage and update deleted jobs
                    void this.loadFromStorage()
                    this.deletedJobs = data.deleted ?? []
                    break
                case 'reload':
                    // Explicit reload from newer tabs
                    location.replace(location.href)
                    break
                case 'update':
                default:
                    // Update message: load from storage and schedule API sync retry
                    void this.loadFromStorage()
                    // schedule a single retry (avoid stacking many scheduled syncs)
                    if (!this._retryTimer) {
                        this._retryTimer = setTimeout(() => {
                            this._retryTimer = null
                            void this.syncWithAPI()
                        }, 1000)
                    }
                    break
            }
        }
    }

    private async loadFromStorage(): Promise<void> {
        try {
            this.cache.clear()
            const stored = await bookmarkIDB.loadBookmarks()
            stored.forEach((job) => this.cache.set(job.id!, job))
            this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
        } catch {
            this.jobs = []
        } finally {
            if (!this.isInitialized)
                this.isInitialized = true
        }
    }

    // immediate low-level save that actually writes to IndexedDB
    private async saveToStorageImmediate(): Promise<void> {
        if (this._saveInProgress) {
            // if a save is already in progress, avoid overlapping writes
            return
        }
        this._saveInProgress = true
        try {
            // Avoid deep clone to save memory on mobile; assume jobs are serializable
            await bookmarkIDB.saveBookmarks(Array.from(this.cache.values()))
        } catch (error) {
            console.error('Failed to save bookmarks to IndexedDB:', error)
            throw error
        } finally {
            this._saveInProgress = false
        }
    }

    /**
     * Public save method used by callers. This coalesces frequent save requests
     * into a single physical write using a debounced function while returning
     * a Promise that resolves when the actual write completes. Callers can
     * await this method to be notified when the save finished.
     */
    private async saveToStorage(): Promise<void> {
        // Lazily create the debounced function so it has access to instance methods
        if (!this._debouncedSaveCall) {
            this._debouncedSaveCall = debounce(async () => {
                try {
                    await this.saveToStorageImmediate()
                    // resolve any pending promise waiting on this save
                    if (this._pendingSaveResolve) {
                        this._pendingSaveResolve()
                    }
                } catch (error) {
                    // forward the error to awaiting callers so callers can revert
                    if (this._pendingSaveReject) this._pendingSaveReject(error)
                } finally {
                    this._pendingSaveResolve = null
                    this._pendingSaveReject = null
                    this._pendingSavePromise = null
                }
            }, 300)
        }

        if (!this._pendingSavePromise) {
            this._pendingSavePromise = new Promise<void>((resolve, reject) => {
                this._pendingSaveResolve = resolve
                this._pendingSaveReject = reject
            })
        }

        // schedule (or reschedule) the debounced save
        this._debouncedSaveCall()
        return this._pendingSavePromise!
    }

    public async addJob(id: number): Promise<void> {
        return this.runQueued(async () => {
            try {
                const job: CardJob = { id, title: '' }
                const clonedJob = JSON.parse(JSON.stringify(job)) as CardJob
                this.cache.set(clonedJob.id!, clonedJob)
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
                // Persist sync results immediately to ensure authoritative data is saved
                await bookmarkIDB.addBookmark(clonedJob)
                if (this.channel) this.channel.postMessage({ type: 'update', version: this.CURRENT_VERSION } as BookmarkBroadcastMessage) // Broadcast update with version for cross-tab sync
                // Sync only the new job to get full data
                this.pendingSyncIds.add(id)
                this.debouncedSync()
            } catch (error) {
                console.error('Failed to add job:', error)
                this.warning = 'Gagal menambahkan bookmark. Silakan coba lagi.'
                // Revert cache
                this.cache.delete(id)
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
            }
        })
    }

    public async removeJob(id: number): Promise<void> {
        return this.runQueued(async () => {
            try {
                this.cache.delete(id)
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
                this.deletedJobs = this.deletedJobs.filter((i) => i !== id)
                // Persist deletions immediately to avoid accidental reappearance
                await bookmarkIDB.removeBookmark(id)
                if (this.channel) this.channel.postMessage({ type: 'update', version: this.CURRENT_VERSION } as BookmarkBroadcastMessage) // Broadcast update with version for cross-tab sync
            } catch (error) {
                console.error('Failed to remove job:', error)
                this.warning = 'Gagal menghapus bookmark. Silakan coba lagi.'
            }
        })
    }

    /**
     * Toggle saved state for a job id. If saved, remove it; otherwise add it.
     */
    public async toggleSave(id: number): Promise<void> {
        return this.runQueued(async () => {
            if (this.isSaved(id)) {
                await this.removeJob(id)
            } else {
                await this.addJob(id)
            }
        })
    }

    public isSaved(id: number): boolean {
        return this.jobs.some((job) => job.id === id)
    }

    public async syncWithAPI(idsToSync?: number[]): Promise<void> {
        // Only sync when tab is visible to avoid background interference
        if (document.visibilityState !== 'visible') return

        return this.runQueued(async () => {
            if (this.isSyncing) {
                // if already syncing, set a warning and ensure we only schedule one retry
                this.warning = 'Data tidak sinkron. Tolong refresh ulang.'
                if (!this._retryTimer) {
                    this._retryTimer = setTimeout(() => {
                        this._retryTimer = null
                        void this.syncWithAPI(idsToSync)
                    }, 3000)
                }
                return
            }
            // clear any pending retry as we're starting a real sync now
            if (this._retryTimer) {
                clearTimeout(this._retryTimer)
                this._retryTimer = null
            }
            this.warning = ''
            // Only set global syncing for full sync, not individual job refresh
            if (!idsToSync) {
                this.isSyncing = true
            }

            const performSync = async () => {
                const ids = idsToSync || this.jobs.map((job) => job.id || 0)
                if (ids.length === 0) return

                const fetchedJobs = await APIService.syncBookmark(ids)
                const plainFetchedJobs: CardJob[] = JSON.parse(JSON.stringify(fetchedJobs))

                // Update cache with fetched jobs
                plainFetchedJobs.forEach((job: CardJob) => this.cache.set(job.id!, job))
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
                await this.saveToStorage()

                // Handle full sync metadata
                if (!idsToSync) {
                    const previousIds = new SvelteSet<number>(this.jobs.map((job) => job.id || 0))
                    const currentIds = new SvelteSet<number>(this.jobs.map((job) => job.id || 0))
                    this.deletedJobs = Array.from(previousIds).filter((id) => !currentIds.has(id))
                    this.lastSyncTime = Date.now()
                    if (this.channel) {
                        this.channel.postMessage({ type: 'sync', deleted: JSON.parse(JSON.stringify(this.deletedJobs)), version: this.CURRENT_VERSION } as BookmarkBroadcastMessage) // Broadcast full sync with version
                    }
                }
            }

            try {
                await performSync()
            } catch (error) {
                console.error('Failed to sync bookmarks with API:', error)
            } finally {
                // Only reset global syncing for full sync
                if (!idsToSync) {
                    this.isSyncing = false
                }
            }
        })
    }

    private async syncPending(): Promise<void> {
        const ids = Array.from(this.pendingSyncIds)
        this.pendingSyncIds.clear()
        if (ids.length > 0) {
            await this.syncWithAPI(ids)
        }
    }

    public flushSync(): void {
        if (this.debouncedSync && typeof this.debouncedSync.flush === 'function') this.debouncedSync.flush()
    }

    public async clearAll(): Promise<void> {
        return this.runQueued(async () => {
            try {
                this.jobs = []
                this.cache.clear()
                this.deletedJobs = []
                await bookmarkIDB.clearBookmarks()
                if (this.channel) this.channel.postMessage({ type: 'update', version: this.CURRENT_VERSION } as BookmarkBroadcastMessage) // Broadcast clear with version
            } catch (error) {
                console.error('Failed to clear all bookmarks:', error)
                this.warning = 'Gagal menghapus semua bookmark. Silakan coba lagi.'
            }
        })
    }

    public clearDeleted(): void {
        this.deletedJobs = []
    }

    private async initialize(): Promise<void> {
        if (typeof window === 'undefined') {
            this.isInitialized = false
            return
        }

        await this.loadFromStorage()
        // Only sync if data is stale (older than 5 minutes) or no sync has been done
        const now = Date.now()
        const isStale = now - this.lastSyncTime > 5 * 60 * 1000 // 5 minutes
        if (isStale || this.lastSyncTime === 0) {
            // Run initial sync outside the queue to avoid blocking user interactions
            void this.syncWithAPI()
        }
    }
}

export const bookmarkStore = new BookmarkManager()
