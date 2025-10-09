import { toStore, type Readable } from 'svelte/store'
import { debounce } from '@/utils/debounce'
import { SvelteMap, SvelteSet } from 'svelte/reactivity'
import { saveBookmarks, loadBookmarks, clearBookmarks } from '@/utils'
import { APIService } from '@/services/APIService'
import type { CardJob } from '@/types/Component'

export class BookmarkManager {
    public jobs = $state<CardJob[]>([])
    public isInitialized = $state(false)
    public isSyncing = $state(false)
    public warning = $state('')
    private cache = new SvelteMap<number, CardJob>()
    public deletedJobs = $state<number[]>([])
    public lastSyncTime = $state<number>(0)

    private channel: BroadcastChannel | null = null
    private debouncedSync: any
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

    public readonly store: Readable<{
        jobs: CardJob[]
        isInitialized: boolean
        isSyncing: boolean
        warning: string
        deletedJobs: number[]
        lastSyncTime: number
    }>

    constructor() {
        // Readable view for components
        this.store = toStore(() => ({
            jobs: this.jobs,
            isInitialized: this.isInitialized,
            isSyncing: this.isSyncing,
            warning: this.warning,
            deletedJobs: this.deletedJobs,
            lastSyncTime: this.lastSyncTime,
        }))

        // Debounced sync
        this.debouncedSync = debounce(this.syncWithAPI.bind(this), 3000)

        // Cross-tab sync
        if (typeof BroadcastChannel !== 'undefined') {
            this.channel = new BroadcastChannel('bookmark-sync')
            this.channel.onmessage = (event: MessageEvent): void => {
                const data = event.data
                if (data && data.type === 'sync') {
                    void this.loadFromStorage()
                    this.deletedJobs = data.deleted ?? []
                } else {
                    void this.loadFromStorage()
                    // schedule a single retry (avoid stacking many scheduled syncs)
                    if (!this._retryTimer) {
                        this._retryTimer = setTimeout(() => {
                            this._retryTimer = null
                            void this.syncWithAPI()
                        }, 1000)
                    }
                }
            }
        }

        // Initialize only in browser
        if (typeof window !== 'undefined') {
            void this.initialize()
        } else {
            // mark initialized on server to avoid hanging consumers
            this.isInitialized = false
        }
    }

    private async loadFromStorage(): Promise<void> {
        try {
            this.cache.clear()
            const stored = await loadBookmarks()
            stored.forEach((job) => this.cache.set(job.id!, job))
            this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
        } catch {
            this.jobs = []
        } finally {
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
            await saveBookmarks(Array.from(this.cache.values()).map((job) => JSON.parse(JSON.stringify(job))))
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

    // Force any queued debounced save to run immediately and return a Promise that
    // resolves when it's finished.
    public async flushSave(): Promise<void> {
        if (this._debouncedSaveCall && typeof this._debouncedSaveCall.flush === 'function') {
            this._debouncedSaveCall.flush()
        }
        return this._pendingSavePromise ?? Promise.resolve()
    }

    // Cancel any queued save and resolve pending promises so callers don't hang.
    public cancelSave(): void {
        if (this._debouncedSaveCall && typeof this._debouncedSaveCall.cancel === 'function') {
            this._debouncedSaveCall.cancel()
        }
        if (this._pendingSaveResolve) {
            this._pendingSaveResolve()
            this._pendingSaveResolve = null
            this._pendingSavePromise = null
        }
    }

    /**
     * Force an immediate save. If a debounced save was scheduled it will be
     * flushed; otherwise write immediately. Returns a Promise that resolves
     * when the physical write completes or rejects on error.
     */
    public async saveToStorageNow(): Promise<void> {
        // If a debounced call exists, flush it (this will call saveToStorageImmediate)
        if (this._debouncedSaveCall && typeof this._debouncedSaveCall.flush === 'function') {
            try {
                this._debouncedSaveCall.flush()
            } catch (e) {
                // ignore; flush will be handled below
            }
            // If flush scheduled a pending promise, return it so callers can await completion
            if (this._pendingSavePromise) return this._pendingSavePromise
            // otherwise fallthrough to immediate save
        }

        // No debounced save scheduled — if another save is currently in
        // progress wait for it to complete and then perform an immediate
        // write so callers can be sure the deletion persisted.
        while (this._saveInProgress) {
            await new Promise((r) => setTimeout(r, 40))
        }
        return this.saveToStorageImmediate()
    }

    public async addJob(id: number): Promise<void> {
        return this.runQueued(async () => {
            try {
                const job: CardJob = { id, title: '' }
                const clonedJob = JSON.parse(JSON.stringify(job)) as CardJob
                this.cache.set(clonedJob.id!, clonedJob)
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
                // Persist sync results immediately to ensure authoritative data is saved
                await this.saveToStorageNow()
                if (this.channel) this.channel.postMessage('update')
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
                await this.saveToStorageNow()
                if (this.channel) this.channel.postMessage('update')
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
    public removeBookmark(id: number): Promise<void> {
        return this.removeJob(id)
    }

    public clearAllBookmarks(): Promise<void> {
        return this.clearAll()
    }

    public sync(): Promise<void> {
        return this.syncWithAPI()
    }

    public isSaved(id: number): boolean {
        return this.jobs.some((job) => job.id === id)
    }

    public getSavedJobs(): CardJob[] {
        return this.jobs
    }

    public async syncWithAPI(): Promise<void> {
        return this.runQueued(async () => {
            if (this.isSyncing) {
                // if already syncing, set a warning and ensure we only schedule one retry
                this.warning = 'Data tidak sinkron. Tolong refresh ulang.'
                if (!this._retryTimer) {
                    this._retryTimer = setTimeout(() => {
                        this._retryTimer = null
                        void this.syncWithAPI()
                    }, 1000)
                }
                return
            }
            // clear any pending retry as we're starting a real sync now
            if (this._retryTimer) {
                clearTimeout(this._retryTimer)
                this._retryTimer = null
            }
            this.warning = ''
            this.isSyncing = true
            try {
                const previousIds = new SvelteSet<number>(this.jobs.map((job) => job.id || 0))
                const ids = this.jobs.map((job) => job.id || 0)
                const fetchedJobs = await APIService.syncBookmark(ids)
                const plainFetchedJobs: CardJob[] = JSON.parse(JSON.stringify(fetchedJobs))
                this.cache.clear()
                plainFetchedJobs.forEach((job: CardJob) => this.cache.set(job.id!, job))
                this.jobs = Array.from(this.cache.values()).sort((a, b) => (b.id || 0) - (a.id || 0))
                await this.saveToStorage()
                const currentIds = new SvelteSet<number>(this.jobs.map((job) => job.id || 0))
                this.deletedJobs = Array.from(previousIds).filter((id) => !currentIds.has(id))
                this.lastSyncTime = Date.now()
                if (this.channel)
                    this.channel.postMessage({ type: 'sync', deleted: JSON.parse(JSON.stringify(this.deletedJobs)) })
            } catch (error) {
                console.error('Failed to sync bookmarks with API:', error)
            } finally {
                this.isSyncing = false
            }
        })
    }

    public flushSync(): void {
        if (this.debouncedSync && typeof this.debouncedSync.flush === 'function') this.debouncedSync.flush()
    }

    public cancelSync(): void {
        if (this.debouncedSync && typeof this.debouncedSync.cancel === 'function') this.debouncedSync.cancel()
    }

    public async clearAll(): Promise<void> {
        return this.runQueued(async () => {
            try {
                this.jobs = []
                this.cache.clear()
                this.deletedJobs = []
                await clearBookmarks()
                if (this.channel) this.channel.postMessage('update')
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
        await this.loadFromStorage()
        await this.syncWithAPI()
    }

    // Allow components to subscribe to the readable snapshot
    public subscribe(run: (v: { jobs: CardJob[]; isInitialized: boolean; warning: string; deletedJobs: number[]; lastSyncTime: number }) => void) {
        return this.store.subscribe(run)
    }
}

export const bookmarkStore = new BookmarkManager()
