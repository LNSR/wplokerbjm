import { debounce, delay, difference, retry, sortBy, Mutex, withTimeout } from "es-toolkit";
import { bookmarkIDB } from "@/services/IndexedDB";
import { SvelteSet, SvelteMap } from "svelte/reactivity";
import { APIServiceBrowser } from "@/services/graphql/APIService";
import type { CardJob } from "@/types";
import { generalJobStore } from "@/lib/stores/GeneralJob.svelte";
import { useRIC } from "$lib/utils/window.svelte";
import { BaseBroadcastChannel } from "@/services/BroadcastChannel";
import { on } from "svelte/events";

interface BookmarkBroadcastMessage
{
  type: "update" | "sync" | "reload";
  deleted?: number[];
  version?: string;
  tabStartedAt?: number;
}

class BookmarkManager
{

  public cacheJobs = new SvelteMap<number, CardJob>();
  public jobs = $derived(
    sortBy(Array.from(this.cacheJobs.values()), [
      (job: CardJob): number => - (Number(job.id) || 0),
    ]),
  );
  public isSyncing = $state(false);
  #warningFromAPI = $state<string>(""); // Specific warning for API issues
  public deletedJobs = $state<number[]>([]);
  public lastSyncTime = $state<number>(0);
  public debouncedSync: ReturnType<typeof debounce> | null = null;
  #pendingSyncIds = new SvelteSet<number>(); // debounces its pending sync

  public bookmarkBroadcastChannel = new class BookmarkBroadcastChannel extends BaseBroadcastChannel
  {
    public broadcastWarning? = $state<string>();
    public isOutdated = $state(false);
    public tabStartedAt = generalJobStore.svelteDate.getTime();

    constructor(private parent: BookmarkManager)
    {
      super("bookmark-sync");
    }

    public crossTabChannel(): void
    {
      if (!this.channel) return;

      this.channel.onmessage = (event: MessageEvent): void =>
      {
        const data = event.data as BookmarkBroadcastMessage;

        if (data.version !== undefined && data.version !== this.CURRENT_VERSION)
        {
          const incomingTabTs = typeof data.tabStartedAt === "number" ? data.tabStartedAt : 0;

          if (incomingTabTs > this.tabStartedAt)
          {
            this.isOutdated = true;
            this.broadcastWarning =
              "Versi baru tersedia di tab lain. Tutup tab lama dan muat ulang untuk melanjutkan.";
          }

          location.replace(location.href);
          return;
        }

        switch (data.type)
        {
          case "sync":
            void this.parent.loadFromStorage();
            this.parent.deletedJobs = data.deleted ?? [];
            break;

          case "reload":
            fetch(location.href, { cache: "reload" }).then(() => location.reload());
            break;

          case "update":
          default:
            void this.parent.loadFromStorage();
            BookmarkTaskController.scheduleRetryTask(1000, () =>
            {
              void this.parent.syncWithAPI();
            });
            break;
        }
      };

      on(document, "visibilitychange", () =>
      {
        if (document.visibilityState === "visible" && !this.isOutdated)
        {
          void this.parent.loadFromStorage();

          BookmarkTaskController.scheduleRetryTask(500, () =>
          {
            void this.parent.syncWithAPI();
          });
        }
      });
    }

    public markOutdated(): boolean
    {
      if (!this.isOutdated) return false;

      this.isOutdated = true;
      this.broadcastWarning =
        "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";

      return true;
    }
  }(this);

  public globalWarning = $derived(
    (this.#warningFromAPI || this.bookmarkBroadcastChannel.broadcastWarning) || ""
  );

  /**
 * Place at onMount so class only loaded on browser environment
 */
  public init(): void
  {
    this.bookmarkBroadcastChannel.crossTabChannel();
    this.debouncedSync = debounce(() => this.syncPending(), 1000);

    useRIC(() =>
    {
      this.loadFromStorage();
      // Only sync if data is stale (older than 5 minutes) or no sync has been done
      const now = generalJobStore.svelteDate.getTime();
      const isStale = now - this.lastSyncTime > 5 * 60 * 1000; // 5 minutes
      if (isStale || this.lastSyncTime === 0)
      {
        // Run initial sync outside the queue to avoid blocking user interactions
        void this.syncWithAPI();
      }
    }, { fallbackDelay: 0 });
  }

  public async addJob(id: number): Promise<void>
  {
    if (this.bookmarkBroadcastChannel.markOutdated()) return;

    return BookmarkTaskController.runQueued(async () =>
    {
      try
      {
        const job = { id, title: "" } as CardJob; // skeleton job with minimal data to show loading state
        this.cacheJobs.set(job.id!, job);
        await bookmarkIDB.addBookmark(job);
        this.bookmarkBroadcastChannel.channel?.postMessage({
          type: "update",
          version: this.bookmarkBroadcastChannel.CURRENT_VERSION,
          tabStartedAt: this.bookmarkBroadcastChannel.tabStartedAt,
        });
        this.#pendingSyncIds.add(id);
        this.debouncedSync?.();
      } catch (error)
      {
        console.error("Failed to add job:", error);
        this.#warningFromAPI = "Gagal menambahkan bookmark. Silakan coba lagi.";
        this.cacheJobs.delete(id);
      }
    });
  }


  public async clearAll(): Promise<void>
  {
    return BookmarkTaskController.runQueued(async () =>
    {
      try
      {
        this.cacheJobs.clear();
        this.deletedJobs = [];
        await bookmarkIDB.clearBookmarks();
        this.bookmarkBroadcastChannel.channel?.postMessage({
          type: "update",
          version: this.bookmarkBroadcastChannel.CURRENT_VERSION,
          tabStartedAt: this.bookmarkBroadcastChannel.tabStartedAt,
        });
      } catch (error)
      {
        console.error("Failed to clear all bookmarks:", error);
        this.#warningFromAPI = "Gagal menghapus semua bookmark. Silakan coba lagi.";
      }
    });
  }

  public async removeJob(id: number): Promise<void>
  {
    if (this.bookmarkBroadcastChannel.markOutdated()) return;

    return BookmarkTaskController.runQueued(async () =>
    {
      try
      {
        this.cacheJobs.delete(id);
        this.deletedJobs = this.deletedJobs.filter((jobId) => jobId !== id);
        await bookmarkIDB.removeBookmark(id);
        this.bookmarkBroadcastChannel.channel?.postMessage({
          type: "update",
          version: this.bookmarkBroadcastChannel.CURRENT_VERSION,
          tabStartedAt: this.bookmarkBroadcastChannel.tabStartedAt,
        });
      } catch (error)
      {
        console.error("Failed to remove job:", error);
        this.#warningFromAPI = "Gagal menghapus bookmark. Silakan coba lagi.";
      }
    });
  }

  public resetWarning(): void
  {
    this.#warningFromAPI = "";
    this.bookmarkBroadcastChannel.isOutdated = false;
    this.bookmarkBroadcastChannel.broadcastWarning = "";
  }

  public async syncWithAPI(idsToSync?: number[]): Promise<void>
  {
    if (this.bookmarkBroadcastChannel.markOutdated()) return;

    // Only sync when running in browser and tab is visible to avoid background interference
    if (document.visibilityState !== "visible") return;

    const performSync = async () =>
    {
      const previousIds = !idsToSync
        ? this.jobs.map((job) => Number(job.id) || 0)
        : [];
      const ids = idsToSync || previousIds;
      if (ids.length === 0) return;

      const fetchedJobs = await APIServiceBrowser.syncBookmarkGraphQL(ids);

      // Update cache with fetched jobs
      fetchedJobs.forEach((job: CardJob) =>
        this.cacheJobs.set(Number(job.id)!, job),
      );

      // Remove jobs that were requested but not returned to prevent stuck skeletons
      const returnedIds = fetchedJobs.map((j) => Number(j.id));
      const requestedIds =
        idsToSync || this.jobs.map((j) => Number(j.id) || 0);
      difference(requestedIds, returnedIds).forEach((id) =>
      {
        this.cacheJobs.delete(id);
      });

      await bookmarkIDB.saveBookmarks(Array.from(this.cacheJobs.values()));

      // Handle full sync metadata
      if (!idsToSync)
      {
        const currentIds = this.jobs.map((job) => Number(job.id) || 0);
        this.deletedJobs = difference(previousIds, currentIds);
        this.lastSyncTime = generalJobStore.svelteDate.getTime();
        this.bookmarkBroadcastChannel.channel?.postMessage({
          type: "sync",
          deleted: $state.snapshot(this.deletedJobs),
          version: this.bookmarkBroadcastChannel.CURRENT_VERSION,
          tabStartedAt: this.bookmarkBroadcastChannel.tabStartedAt,
        });
      }
    };

    const isGlobalSyncing = (): boolean =>
    {
      if (!this.isSyncing) return false;
      // if already syncing, set a warning and ensure we only schedule one retry
      this.#warningFromAPI = "Data tidak sinkron. Tolong refresh ulang.";
      if (!BookmarkTaskController.isRetryScheduled)
      {
        BookmarkTaskController.scheduleRetryTask(3000, () =>
        {
          void this.syncWithAPI(idsToSync);
        });
      }
      return true;
    }

    const syncError = (error: unknown, idsToSync?: number[]): void =>
    {
      console.error("Failed to sync bookmarks with API:", error);
      this.#warningFromAPI = "Gagal menyinkronkan bookmark. Silakan coba lagi.";
      if (idsToSync) idsToSync.forEach((id) => this.cacheJobs.delete(id));
    }

    return BookmarkTaskController.runQueued(async () =>
    {
      if (isGlobalSyncing()) return;

      // clear any pending retry as we're starting a real sync now
      BookmarkTaskController.cancelRetry();
      this.resetWarning();

      if (!idsToSync) this.isSyncing = true;

      try
      {
        await performSync();
      } catch (error)
      {
        syncError(error, idsToSync);
      } finally
      {
        // Only reset global syncing for full sync
        if (!idsToSync) this.isSyncing = false;
      }
    }, 60000);
  }

  public async loadFromStorage(): Promise<void>
  {
    return BookmarkTaskController.runQueued(async () =>
    {
      try
      {
        this.cacheJobs.clear();
        const stored = await bookmarkIDB.loadBookmarks();
        stored.forEach((job) => this.cacheJobs.set(Number(job.id)!, job));
      } catch
      {
        this.cacheJobs.clear();
      }
    }
    );
  }


  private async syncPending(): Promise<void>
  {
    const ids = Array.from(this.#pendingSyncIds);
    this.#pendingSyncIds.clear();
    if (ids.length > 0)
    {
      await this.syncWithAPI(ids);
    }
  }
}

/**
* Task controller to manage sequential execution of bookmark operations and retries with timeout handling.
*/
class BookmarkTaskController
{
  static #retryTimer: AbortController | null = null;
  static #operationMutex = new Mutex();
  public static get isRetryScheduled(): boolean
  {
    return this.#retryTimer !== null;
  }

  public static scheduleRetryTask(delayMs: number, task: () => void): void
  {
    if (this.#retryTimer) return;

    const controller = new AbortController();
    this.#retryTimer = controller;

    retry(
      async () =>
      {
        await delay(delayMs);
        if (controller.signal.aborted)
        {
          throw new Error("retry cancelled");
        }
        task();
      },
      {
        delay: 0,
        retries: 2,
        signal: controller.signal,
      },
    ).catch((e: unknown) =>
    {
      console.error("Scheduled retry task failed:", e);
    }).finally(() =>
    {
      if (this.#retryTimer === controller)
      {
        this.#retryTimer = null;
      }
    });
  }

  public static cancelRetry(): void
  {
    if (this.#retryTimer) this.#retryTimer.abort();
    this.#retryTimer?.abort();
    this.#retryTimer = null;
  }

  /**
* Run operation in sequence to avoid race conditions on IndexedDB and ensure predictable state updates. Each operation waits for the previous one to complete before starting.
* A timeout is included to prevent the queue from getting stuck indefinitely due to unforeseen issues. If an operation fails or times out, it logs a warning but allows subsequent operations to continue.
* @param operation The asynchronous operation to run in the queue.
* @param timeoutMs The maximum time to wait for the operation to complete before timing out.
* @returns A promise that resolves with the operation's result or rejects if it times out.
*/
  public static async runQueued<T>(
    operation: () => Promise<T>,
    timeoutMs: number = 10000,
  ): Promise<T>
  {
    await this.#operationMutex.acquire();
    try
    {
      return await withTimeout(operation, timeoutMs);
    } catch (err)
    {
      console.warn("Queued operation failed or timed out:", err);
      throw err;
    } finally
    {
      this.#operationMutex.release();
    }
  }
}

export const bookmarkStore = new BookmarkManager();