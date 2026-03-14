import {
  debounce,
  bookmarkIDB,
  type DebouncedFunction,
} from "@/utils";
import { SvelteSet, SvelteMap, SvelteDate } from "svelte/reactivity";
import { APIService } from "@/services/APIService";
import type { CardJob } from "@/types";
import { browser, version } from "$app/environment";

interface BookmarkBroadcastMessage {
  type: "update" | "sync" | "reload";
  deleted?: number[];
  version?: string;
  tabStartedAt?: number;
}
export class BookmarkManager {
  private CURRENT_VERSION = $derived(version);
  public jobs = $state<CardJob[]>([]);
  public isInitialized = $state(false);
  public isSyncing = $state(false);
  public warning = $state("");
  public isOutdated = $state(false);
  private cache = new SvelteMap<number, CardJob>();
  public deletedJobs = $state<number[]>([]);
  public lastSyncTime = $state<number>(0);

  private channel: BroadcastChannel | null = null;
  private readonly tabStartedAt = Date.now();
  private debouncedSync: DebouncedFunction | null = null;
  private pendingSyncIds = new SvelteSet<number>();
  #debouncedSaveCall: any = null;
  #pendingSavePromise: Promise<void> | null = null;
  #pendingSaveResolve: (() => void) | null = null;
  #pendingSaveReject: ((reason?: any) => void) | null = null;
  #saveInProgress = false;
  #retryTimer: ReturnType<typeof setTimeout> | null = null;
  private operationQueue: Promise<any> = Promise.resolve();

  constructor() {
    this.initialize();
    this.crossTabChannel();
    this.debouncedSync = debounce(() => this.syncPending(), 1000);
  }

  /**
   * Run operation in sequence to avoid race conditions on IndexedDB and ensure predictable state updates. Each operation waits for the previous one to complete before starting.
   * A timeout is included to prevent the queue from getting stuck indefinitely due to unforeseen issues. If an operation fails or times out, it logs a warning but allows subsequent operations to continue.
   * @param operation The asynchronous operation to run in the queue.
   * @param timeoutMs The maximum time to wait for the operation to complete before timing out.
   * @returns A promise that resolves with the operation's result or rejects if it times out.
   */
  private async runQueued<T>(
    operation: () => Promise<T>,
    timeoutMs: number = 10000,
  ): Promise<T> {
    try {
      await this.operationQueue;
    } catch (err) {
      // swallow previous rejection so a failed operation doesn't block the queue forever
      console.warn("Previous queued operation failed, continuing", err);
    }

    let timeoutHandle: ReturnType<typeof setTimeout> | null = null;
    const opPromise = (async () => {
      try {
        return await Promise.race([
          operation(),
          new Promise<T>((_, reject) => {
            timeoutHandle = setTimeout(() => {
              reject(new Error("Queued operation timed out"));
            }, timeoutMs);
          }),
        ]);
      } finally {
        if (timeoutHandle) clearTimeout(timeoutHandle);
      }
    })();

    // keep the internal queue reference safe-from-rejection so future ops aren't blocked
    this.operationQueue = opPromise.then(
      () => { },
      (err) => {
        console.warn("Queued operation failed or timed out:", err);
      },
    );

    return opPromise;
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
    if (!browser) {
      return;
    }

    this.channel = new BroadcastChannel("bookmark-sync");
    this.channel.onmessage = (event: MessageEvent): void => {
      const data = event.data as BookmarkBroadcastMessage;

      // Version mismatch: ignore messages from different builds/versions.
      // If the incoming message is from a newer tab instance, mark this tab as outdated.
      if (data.version !== undefined && data.version !== this.CURRENT_VERSION) {
        const incomingTabTs = typeof data.tabStartedAt === "number" ? data.tabStartedAt : 0;
        if (incomingTabTs > this.tabStartedAt) {
          // Prevent new data mutations in outdated tabs and instruct user to refresh.
          this.isOutdated = true;
          this.warning =
            "Versi baru tersedia di tab lain. Tutup tab lama dan muat ulang untuk melanjutkan.";
        }
        return;
      }

      // Handle different message types using type-safe switch
      switch (data.type) {
        case "sync":
          // Full sync: load from storage and update deleted jobs
          void this.loadFromStorage();
          this.deletedJobs = data.deleted ?? [];
          break;
        case "reload":
          // Explicit reload from newer tabs: force a refresh to align versions
          if (browser) location.replace(location.href);
          break;
        case "update":
        default:
          // Update message: load from storage and schedule API sync retry
          void this.loadFromStorage();
          // schedule a single retry (avoid stacking many scheduled syncs)
          if (!this.#retryTimer) {
            this.#retryTimer = setTimeout(() => {
              this.#retryTimer = null;
              void this.syncWithAPI();
            }, 1000);
          }
          break;
      }
    };

    // Add visibility change listener: sync data when tab becomes visible again
    // This ensures we don't miss broadcasts while in the background
    if (browser) {
      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible" && !this.isOutdated) {
          void this.loadFromStorage();
          // Schedule a sync to pick up any changes from the API while we were away
          if (!this.#retryTimer) {
            this.#retryTimer = setTimeout(() => {
              this.#retryTimer = null;
              void this.syncWithAPI();
            }, 500);
          }
        }
      });
    }
  }

  private async loadFromStorage(): Promise<void> {
    try {
      this.cache.clear();
      const stored = await bookmarkIDB.loadBookmarks();
      stored.forEach((job) => this.cache.set(Number(job.id)!, job));
      this.jobs = Array.from(this.cache.values()).sort(
        (a, b) => (Number(b.id) || 0) - (Number(a.id) || 0),
      );
    } catch {
      this.jobs = [];
    } finally {
      if (!this.isInitialized) this.isInitialized = true;
    }
  }

  // immediate low-level save that actually writes to IndexedDB
  private async saveToStorageImmediate(): Promise<void> {
    if (this.#saveInProgress) {
      // if a save is already in progress, avoid overlapping writes
      return;
    }
    this.#saveInProgress = true;
    try {
      // Avoid deep clone to save memory on mobile; assume jobs are serializable
      await bookmarkIDB.saveBookmarks(Array.from(this.cache.values()));
    } catch (error) {
      console.error("Failed to save bookmarks to IndexedDB:", error);
      throw error;
    } finally {
      this.#saveInProgress = false;
    }
  }

  /**
   * Public save method used by callers. This coalesces frequent save requests
   * into a single physical write using a debounced function while returning
   * a Promise that resolves when the actual write completes. Callers can
   * await this method to be notified when the save finished.
   */
  private async saveToStorage(): Promise<void> {
    if (!this.#debouncedSaveCall) {
      this.#debouncedSaveCall = debounce(async () => {
        try {
          await this.saveToStorageImmediate();
          // resolve any pending promise waiting on this save
          if (this.#pendingSaveResolve) {
            this.#pendingSaveResolve();
          }
        } catch (error) {
          // forward the error to awaiting callers so callers can revert
          if (this.#pendingSaveReject) this.#pendingSaveReject(error);
        } finally {
          this.#pendingSaveResolve = null;
          this.#pendingSaveReject = null;
          this.#pendingSavePromise = null;
        }
      }, 300);
    }

    if (!this.#pendingSavePromise) {
      this.#pendingSavePromise = new Promise<void>((resolve, reject) => {
        this.#pendingSaveResolve = resolve;
        this.#pendingSaveReject = reject;
      });
    }

    // schedule (or reschedule) the debounced save
    this.#debouncedSaveCall();
    return this.#pendingSavePromise!;
  }

  public async addJob(id: number): Promise<void> {
    if (this.isOutdated) {
      this.warning =
        "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";
      return;
    }

    return this.runQueued(async () => {
      try {
        const job: CardJob = { id, title: "" };
        const clonedJob = JSON.parse(JSON.stringify(job)) as CardJob;
        this.cache.set(clonedJob.id!, clonedJob);
        this.jobs = Array.from(this.cache.values()).sort(
          (a, b) => (b.id || 0) - (a.id || 0),
        );
        // Persist sync results immediately to ensure authoritative data is saved
        await bookmarkIDB.addBookmark(clonedJob);
        if (this.channel)
          this.channel.postMessage({
            type: "update",
            version: this.CURRENT_VERSION,
          } as BookmarkBroadcastMessage); // Broadcast update with version for cross-tab sync
        // Sync only the new job to get full data
        this.pendingSyncIds.add(id);
        this.debouncedSync?.();
      } catch (error) {
        console.error("Failed to add job:", error);
        this.warning = "Gagal menambahkan bookmark. Silakan coba lagi.";
        // Revert cache
        this.cache.delete(id);
        this.jobs = Array.from(this.cache.values()).sort(
          (a, b) => (b.id || 0) - (a.id || 0),
        );
      }
    });
  }

  public async removeJob(id: number): Promise<void> {
    if (this.isOutdated) {
      this.warning =
        "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";
      return;
    }

    return this.runQueued(async () => {
      try {
        this.cache.delete(id);
        this.jobs = Array.from(this.cache.values()).sort(
          (a, b) => (b.id || 0) - (a.id || 0),
        );
        this.deletedJobs = this.deletedJobs.filter((i) => i !== id);
        // Persist deletions immediately to avoid accidental reappearance
        await bookmarkIDB.removeBookmark(id);
        if (this.channel)
          this.channel.postMessage({
            type: "update",
            version: this.CURRENT_VERSION,
          } as BookmarkBroadcastMessage); // Broadcast update with version for cross-tab sync
      } catch (error) {
        console.error("Failed to remove job:", error);
        this.warning = "Gagal menghapus bookmark. Silakan coba lagi.";
      }
    });
  }

  /**
   * Toggle saved state for a job id. If saved, remove it; otherwise add it.
   */
  public async toggleSave(id: number): Promise<void> {
    if (this.isOutdated) {
      this.warning =
        "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";
      return;
    }

    return this.runQueued(async () => {
      if (this.isSaved(id)) {
        await this.removeJob(id);
      } else {
        await this.addJob(id);
      }
    });
  }

  public isSaved(id: number): boolean {
    return this.jobs.some((job) => job.id === id);
  }

  public async syncWithAPI(idsToSync?: number[]): Promise<void> {
    if (this.isOutdated) {
      this.warning =
        "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";
      return;
    }

    // Only sync when running in browser and tab is visible to avoid background interference
    if (!browser || document.visibilityState !== "visible") return;

    return this.runQueued(async () => {
      if (this.isSyncing) {
        // if already syncing, set a warning and ensure we only schedule one retry
        this.warning = "Data tidak sinkron. Tolong refresh ulang.";
        if (!this.#retryTimer) {
          this.#retryTimer = setTimeout(() => {
            this.#retryTimer = null;
            void this.syncWithAPI(idsToSync);
          }, 3000);
        }
        return;
      }
      // clear any pending retry as we're starting a real sync now
      if (this.#retryTimer) {
        clearTimeout(this.#retryTimer);
        this.#retryTimer = null;
      }
      this.warning = "";
      // Only set global syncing for full sync, not individual job refresh
      if (!idsToSync) {
        this.isSyncing = true;
      }

      const performSync = async () => {
        const ids = idsToSync || this.jobs.map((job) => Number(job.id) || 0);
        if (ids.length === 0) return;

        const fetchedJobs = await APIService.syncBookmarkGraphQL(ids);
        const plainFetchedJobs: CardJob[] = JSON.parse(
          JSON.stringify(fetchedJobs),
        );

        // Update cache with fetched jobs
        plainFetchedJobs.forEach((job: CardJob) =>
          this.cache.set(Number(job.id)!, job),
        );

        // Remove jobs that were requested but not returned to prevent stuck skeletons
        const returnedIds = new SvelteSet(
          plainFetchedJobs.map((j) => Number(j.id)),
        );
        const requestedIds =
          idsToSync || this.jobs.map((j) => Number(j.id) || 0);
        requestedIds.forEach((id) => {
          if (!returnedIds.has(id)) {
            this.cache.delete(id);
          }
        });

        this.jobs = Array.from(this.cache.values()).sort(
          (a, b) => (Number(b.id) || 0) - (Number(a.id) || 0),
        );
        await this.saveToStorage();

        // Handle full sync metadata
        if (!idsToSync) {
          const previousIds = new SvelteSet<number>(
            this.jobs.map((job) => Number(job.id) || 0),
          );
          const currentIds = new SvelteSet<number>(
            this.jobs.map((job) => Number(job.id) || 0),
          );
          this.deletedJobs = Array.from(previousIds).filter(
            (id) => !currentIds.has(id),
          );
          this.lastSyncTime = SvelteDate.now();
          if (this.channel) {
            this.channel.postMessage({
              type: "sync",
              deleted: JSON.parse(JSON.stringify(this.deletedJobs)),
              version: this.CURRENT_VERSION,
              tabStartedAt: this.tabStartedAt,
            } as BookmarkBroadcastMessage); // Broadcast full sync with version
          }
        }
      };

      try {
        await performSync();
      } catch (error) {
        console.error("Failed to sync bookmarks with API:", error);
        // If syncing specific ids failed, remove the placeholders to prevent stuck skeletons
        if (idsToSync) {
          idsToSync.forEach((id) => this.cache.delete(id));
          this.jobs = Array.from(this.cache.values()).sort(
            (a, b) => (b.id || 0) - (a.id || 0),
          );
        }
      } finally {
        // Only reset global syncing for full sync
        if (!idsToSync) {
          this.isSyncing = false;
        }
      }
    }, 60000);
  }

  private async syncPending(): Promise<void> {
    const ids = Array.from(this.pendingSyncIds);
    this.pendingSyncIds.clear();
    if (ids.length > 0) {
      await this.syncWithAPI(ids);
    }
  }

  public flushSync(): void {
    if (this.debouncedSync) this.debouncedSync.flush();
  }

  /**
   * Cancel any pending scheduled sync.
   */
  public cancelSync(): void {
    if (this.debouncedSync) this.debouncedSync.cancel();
  }

  public async clearAll(): Promise<void> {
    return this.runQueued(async () => {
      try {
        this.jobs = [];
        this.cache.clear();
        this.deletedJobs = [];
        await bookmarkIDB.clearBookmarks();
        if (this.channel)
          this.channel.postMessage({
            type: "update",
            version: this.CURRENT_VERSION,
            tabStartedAt: this.tabStartedAt,
          } as BookmarkBroadcastMessage); // Broadcast clear with version
      } catch (error) {
        console.error("Failed to clear all bookmarks:", error);
        this.warning = "Gagal menghapus semua bookmark. Silakan coba lagi.";
      }
    });
  }

  public clearDeleted(): void {
    this.deletedJobs = [];
  }

  private initialize(): void {
    if (!browser) {
      this.isInitialized = false;
      return;
    }

    requestIdleCallback(() => {
      this.loadFromStorage();
      // Only sync if data is stale (older than 5 minutes) or no sync has been done
      const now = SvelteDate.now();
      const isStale = now - this.lastSyncTime > 5 * 60 * 1000; // 5 minutes
      if (isStale || this.lastSyncTime === 0) {
        // Run initial sync outside the queue to avoid blocking user interactions
        void this.syncWithAPI();
      }
    });
  }
}

export const bookmarkStore = new BookmarkManager();
