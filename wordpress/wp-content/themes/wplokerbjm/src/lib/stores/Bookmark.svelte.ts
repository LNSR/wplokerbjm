import { debounce } from "es-toolkit";
import { SvelteMap, SvelteSet } from "svelte/reactivity";
import { BookmarkIDB } from "@/services/IndexedDB";
import { APIServiceBrowser } from "@/services/graphql/APIService";
import type { CardJob } from "@/types";
import { BaseBroadcastChannel } from "@/services/BroadcastChannel";
import typia from "typia";
import { TaskController } from "@/utils/mutex";
import { useRIC } from "@/utils/window";

interface BookmarkBroadcastMessage
{
  type: "sync" | "reload";
  deleted?: number[];
  version: string;
  tabStartedAt: number;
  broadcasterIdentifierId: string;
}

interface SyncResult { success: boolean; type: "partial" | "full" };


class BookmarkManager
{

  //* === Private instance fields ===
  #bookmarkTaskController: TaskController;
  #bookmarkRepository: BookmarkRepository;
  #bookmarkBroadcastChannel: BookmarkBroadcastChannel;
  #bookmarkSyncEngine: SyncOperation;

  //* === Public getter state ===
  public get jobs()
  {
    return Array.from(this.#bookmarkRepository.cacheJobs.values()).toSorted((a, b) => Number(b.id) - Number(a.id));
  }
  public get outdatedStatus() { return this.#bookmarkBroadcastChannel.isOutdated; }
  public get globalWarning() { return (this.#bookmarkSyncEngine.warningFromSync || this.#storeWarning || this.#bookmarkBroadcastChannel.broadcastWarning) || undefined; }
  public get isSyncingStatus() { return this.#bookmarkSyncEngine.isSyncing; }

  //* === Public instance fields ===
  public get expiredJobIds() { return this.#bookmarkRepository.expiredJobs; }
  public get lastSyncTime() { return this.#bookmarkSyncEngine.lastSyncTime; }

  //* === Bookmark store fields ===
  #storeWarning = $state<string>("");


  constructor(
    bookmarkRepository: BookmarkRepository,
    bookmarkTaskController: TaskController,
    bookmarkBroadcastChannel?: BookmarkBroadcastChannel,
    bookmarkSyncEngine?: SyncOperation,
  )
  {
    //* === Initialize dependencies ===
    this.#bookmarkTaskController = bookmarkTaskController;
    this.#bookmarkRepository = bookmarkRepository;
    this.#bookmarkBroadcastChannel = bookmarkBroadcastChannel ?? new BookmarkBroadcastChannel(
      this.#loadFromStorageIDB.bind(this),
      bookmarkRepository,
    );
    this.#bookmarkSyncEngine = bookmarkSyncEngine ?? new SyncOperation(
      bookmarkRepository,
      bookmarkTaskController,
      bookmarkBroadcastChannel ?? this.#bookmarkBroadcastChannel,
    );

    //* === Actions ===
    this.#init();
  }

  //* === Public methods for UI components to interact with bookmark data ===
  public async addJob(id: CardJob[ 'id' ]): Promise<void>
  {
    if (this.#bookmarkBroadcastChannel.isOutdated) return;

    return this.#bookmarkTaskController.runQueued(async () =>
    {
      try
      {
        const job: Pick<CardJob, "id" | "title"> = { id, title: "" }; // skeleton job with minimal data to show loading state
        this.#bookmarkSyncEngine.queueIdsForSync([ id ]); // queue for sync to get real data and update cache/IndexedDB
        await this.#bookmarkRepository.addBookmark(job as CardJob);
      } catch (error)
      {
        console.error("Failed to add job:", error);
        this.#storeWarning = "Gagal menambahkan bookmark. Silakan coba lagi.";
      }
    });
  }


  public async clearAll(): Promise<void>
  {
    if (this.#bookmarkBroadcastChannel.isOutdated) return;
    return this.#bookmarkTaskController.runQueued(async () =>
    {
      try
      {
        await this.#bookmarkRepository.clearAll();
      } catch (error)
      {
        console.error("Failed to clear all bookmarks:", error);
        this.#storeWarning = "Gagal menghapus semua bookmark. Silakan coba lagi.";
      }
    });
  }

  public async removeJob(id: CardJob[ 'id' ]): Promise<void>
  {
    if (this.#bookmarkBroadcastChannel.isOutdated) return;

    return this.#bookmarkTaskController.runQueued(async () =>
    {
      try
      {
        await this.#bookmarkRepository.removeBookmark(id);
        this.#bookmarkBroadcastChannel.postMessage("sync", true); // notify other tabs to sync and update their cache with the new job
      } catch (error)
      {
        console.error("Failed to remove job:", error);
        this.#storeWarning = "Gagal menghapus bookmark. Silakan coba lagi.";
      }
    });
  }

  /*
   * Global/full refresh bookmark, will perform full sync with API and update all tabs
   * Since it's public facing, no need to worry about API hammering and let max-age or CDN caching handle it
   */
  public async refreshBookmark(): ReturnType<SyncOperation[ "syncToServer" ]>
  {
    const result = await this.#bookmarkSyncEngine.syncToServer();
    if (result?.success && result.type === "full") this.#bookmarkBroadcastChannel.postMessage("sync", true);
    return result;
  }

  public clearAllExpiredJobs(): void
  {
    this.#bookmarkRepository.clearExpiredJobs(undefined, true);
  }

  public removeExpiredJob(id: CardJob[ 'id' ]): void
  {
    this.#bookmarkRepository.clearExpiredJobs(id);
  }

  //* === Internal helper methods across bookmark management and shared workers ===

  /**
   * Emergency method to reset warnings from sync and broadcast channel
   */
  // public resetWarning = (): void =>
  // {
  //   this.#bookmarkSyncEngine.warningFromSync = "";
  //   this.#bookmarkBroadcastChannel.broadcastWarning = "";
  // }

  /**
   * Load bookmarks from IndexedDB into the cache, used for initial load and after receiving sync messages from other tabs. This method is queued to prevent multiple simultaneous loads and ensure data consistency. If loading fails, it will clear the cache to prevent showing stale data and log the error for debugging.
   */
  #loadFromStorageIDB(): ReturnType<BookmarkRepository[ "loadFromStorageIDB" ]>
  {
    return this.#bookmarkTaskController.runQueued(async () =>
    {
      await this.#bookmarkRepository.loadFromStorageIDB();
    });
  }

  /**
   * @internal
   */
  #init(): void
  {
    if (typeof window === "undefined") return;
    void this.#loadFromStorageIDB().catch((error: unknown) =>
    {
      console.error("Failed to load bookmarks from IndexedDB:", error);
    });
  }
}


class BookmarkBroadcastChannel extends BaseBroadcastChannel
{

  public broadcastWarning? = $state<string>();
  public isOutdated = $state(false);
  #tabStartedAt = new Date().getTime();
  #tabInstanceID = `${this.currentVersion}-${this.#tabStartedAt}`;
  #warningOutdated = "Versi lama terdeteksi. Tutup tab lain dan muat ulang untuk melanjutkan.";
  // * === Injected dependencies ===
  #reloadStorageIDB: BookmarkRepository[ "loadFromStorageIDB" ];
  #expiredJobs: BookmarkRepository[ 'expiredJobs' ];
  constructor(
    reloadStorageIDB: BookmarkRepository[ "loadFromStorageIDB" ],
    bookmarkRepository: BookmarkRepository,
  )
  {
    super("bookmark-sync"); // channel name
    //* === Injected dependencies ===
    this.#reloadStorageIDB = reloadStorageIDB;
    this.#expiredJobs = bookmarkRepository.expiredJobs;

    //* === Actions ===
    this.#init();

  }

  public postMessage(type: BookmarkBroadcastMessage[ 'type' ], withDelete: boolean = false): void
  {
    if (!this.getChannel) return;
    const message: BookmarkBroadcastMessage = {
      type,
      ...(withDelete && { deleted: [ ...this.#expiredJobs ] }),
      version: this.currentVersion,
      tabStartedAt: this.#tabStartedAt,
      broadcasterIdentifierId: this.#tabInstanceID,
    };
    this.getChannel.postMessage(message);
  }

  /**
   * Handle incoming messages based on their type to perform appropriate actions 
   * @param data The message data received from other tabs
   */
  #handleMessageType(data: BookmarkBroadcastMessage): void
  {
    if (data.broadcasterIdentifierId === this.#tabInstanceID) return; // ignore own messages 

    const refreshStoreOnMessage = async () =>
    {
      data.deleted && data.deleted.forEach((id) => this.#expiredJobs.add(id)); // update expired jobs with deleted IDs from other tab
      await this.#reloadStorageIDB();
    }

    switch (data.type)
    {
      //! this reload every tab
      case "reload":
        this.#reloadPage();
        break;
      case "sync":
        refreshStoreOnMessage();
        break;
    }
  }
  #reloadPage(): void
  {
    fetch(location.href, { cache: "reload" }).then(() => location.reload());
  }

  /**
  * Sets up cross-tab communication by listening for messages from other tabs
  */
  #setupCrossTabCommunication(): void
  {
    if (!this.getChannel) return;

    /**
     * Every postMessage call will trigger version check
     */
    this.getChannel.onmessage ??= (event: MessageEvent): void =>
    {
      const checkData = typia.validateEquals<BookmarkBroadcastMessage>(event.data);
      if (!checkData.success)
      {
        console.warn("Received invalid message on bookmark channel:", checkData.errors);
      }

      const data: BookmarkBroadcastMessage = event.data;

      if (data.version !== this.currentVersion)
      {
        // different tab with different version
        const incomingTabTs = data.tabStartedAt;

        // if different(fresh) tab has newer timestamp, mark current tab as outdated and reload
        if (incomingTabTs > this.#tabStartedAt)
        {
          this.#markOutdated();
        }

        return;
      }

      this.#handleMessageType(data);
    };
  }

  #initEventListener(): void
  {
    const handler = () =>
    {
      if (document.visibilityState === "visible" && !this.isOutdated) void this.#reloadStorageIDB();
    };

    document.addEventListener("visibilitychange", handler);
  }

  #init(): void
  {
    if (typeof window === "undefined") return;
    this.#setupCrossTabCommunication();
    this.#initEventListener();
  }

  /**
   * Marks the current tab as outdated if it receives a message from another tab with a different version
   */
  #markOutdated(): void
  {
    this.isOutdated ||= true;
    this.broadcastWarning = this.#warningOutdated;
    setTimeout(() => this.#reloadPage(), 6000);
  }

};

class BookmarkRepository
{
  // fow now direct access on module scope
  public bookmarkIDB: BookmarkIDB;
  public cacheJobs = new SvelteMap<number, CardJob>();
  public expiredJobs = new SvelteSet<CardJob[ 'id' ]>();
  constructor(bookmarkIDB: BookmarkIDB = new BookmarkIDB('JobBookmarks', 1, 'bookmarks'))
  {
    this.bookmarkIDB = bookmarkIDB;
  }

  public async clearAll(): Promise<void>
  {
    await this.bookmarkIDB.clearBookmarks().then(() =>
    {
      this.cacheJobs.clear();
      this.expiredJobs.clear();
    }).catch((error) =>
    {
      console.error("Failed to clear bookmarks from IndexedDB:", error);
    });
  }

  public clearExpiredJobs(id?: CardJob[ 'id' ], clearAll: boolean = false): void 
  {
    if (clearAll) this.expiredJobs.clear();
    if (id) this.expiredJobs.delete(id);
  }

  /**
   */
  public async addBookmark(job: CardJob): Promise<void>
  {
    this.cacheJobs.set(Number(job.id), job);
    await this.bookmarkIDB.addBookmark(job).catch((error) =>
    {
      console.error("Failed to add bookmark to IndexedDB:", error);
      this.cacheJobs.delete(Number(job.id)); // rollback cache if IDB operation fails to prevent stale data
    });
  }

  public async removeBookmark(id: CardJob[ 'id' ]): Promise<void>
  {
    this.cacheJobs.delete(Number(id));
    this.expiredJobs.add(id); // temporarly mark as expired so other tabs can know it's deleted
    await this.bookmarkIDB.removeBookmark(Number(id)).catch((error) =>
    {
      console.error("Failed to remove bookmark from IndexedDB:", error);
      this.expiredJobs.delete(id);
    }).finally(() =>
    {
      this.expiredJobs.delete(id);
    });
  }

  /**
   * Load bookmarks from IndexedDB into the cache, used for initial load and after receiving sync messages from other tabs. This method is queued to prevent multiple simultaneous loads and ensure data consistency. If loading fails, it will clear the cache to prevent showing stale data and log the error for debugging.
   */
  public async loadFromStorageIDB(): Promise<void>
  {
    if (typeof window === "undefined") return;
    await this.bookmarkIDB.loadBookmarks().then((jobs) =>
    {
      if (this.cacheJobs.size) this.cacheJobs.clear();
      jobs.forEach((job) => this.cacheJobs.set(Number(job.id), job));
    }).catch((error) =>
    {
      console.error("Failed to load bookmarks from IndexedDB:", error);
      this.cacheJobs.clear(); // clear cache to prevent showing stale data if loading fails
    });
  }
}

class SyncOperation
{

  public isSyncing = $state(false);
  public warningFromSync = $state<string>(""); // Specific warning for API issues
  public lastSyncTime = new Date();
  #pendingSyncIds = new Set<number>(); // unique IDs pending sync
  #debouncedSync = debounce(() => this.#syncPending(), 2200);
  #localStorageKey = "bookmarkLastSync";
  //* === Injected dependencies ===
  #bookmarkRepository: Pick<BookmarkRepository, "cacheJobs" | "expiredJobs" | "bookmarkIDB">;
  #bookmarkTaskController: TaskController;
  #bookmarkBroadcastChannel: Pick<BookmarkBroadcastChannel, "isOutdated">;
  get #getLatestJobIds(): CardJob[ 'id' ][] { return [ ...this.#bookmarkRepository.cacheJobs.keys() ]; }

  constructor(
    bookmarkRepository: BookmarkRepository,
    bookmarkTaskController: TaskController,
    bookmarkBroadcastChannel: BookmarkBroadcastChannel,
  )
  {
    this.#bookmarkRepository = bookmarkRepository;
    this.#bookmarkTaskController = bookmarkTaskController;
    this.#bookmarkBroadcastChannel = bookmarkBroadcastChannel;

    //* === Actions ===
    this.#init();
  }

  /**
   * @param idsToSync debounced sync from pending IDs
   * @internal queueing mechanism to prevent multiple simultaneous syncs
   * * if idsToSync not provided, global full sync will be performed by taking snapshot of current job IDs in cache
   * ! Every sync will trigger version check, so if tab is outdated it will stop syncing and show warning instead of doing unnecessary API calls
   */
  public async syncToServer(idsToSync?: CardJob[ 'id' ][]): Promise<void | SyncResult>
  {
    if (this.#bookmarkBroadcastChannel.isOutdated || document.visibilityState !== "visible") return;

    const syncError = (error: unknown, idsToSync?: CardJob[ 'id' ][]): void =>
    {
      console.error("Failed to sync bookmarks with API:", error);
      this.warningFromSync = "Gagal menyinkronkan bookmark. Silakan coba lagi.";
      // If specific IDs failed to sync, remove them from cache to prevent stuck loading states and mark as expired for user feedback
      if (idsToSync) idsToSync.forEach((id) => this.#bookmarkRepository.cacheJobs.delete(id));
    };

    return this.#bookmarkTaskController.runQueued(async () =>
    {
      if (this.isSyncing) return await this.#scheduleSyncRetry(idsToSync);

      // clear any pending retry as we're starting a real sync now
      this.#bookmarkTaskController.cancelRetry();

      if (!idsToSync) this.isSyncing = true;

      return await this.#executeSync(idsToSync).then((): SyncResult =>
      {

        return {
          success: true,
          type: idsToSync ? "partial" : "full",
        };

      }).catch((error: unknown): SyncResult =>
      {

        syncError(error, idsToSync);
        return {
          success: false,
          type: idsToSync ? "partial" : "full",
        };

      }).finally(() =>
      {
        if (!idsToSync) this.isSyncing = false;
      });
    }, 60000);
  }

  /**
  * 
  * @param ids IDs to be queued for syncing, will be debounced to batch multiple rapid calls into one sync operation
  */
  public queueIdsForSync(ids: Parameters<typeof this.syncToServer>[ 0 ]): void
  {
    if (!ids || ids.length === 0) return;
    ids.forEach((id) => this.#pendingSyncIds.add(id));
    this.#debouncedSync?.();
  }

  /**
   * Perform the actual sync operation for pending IDs, @see SyncOperation.queueIdsForSync
   */
  #syncPending(): void
  {
    const ids = [ ...this.#pendingSyncIds ];
    if (ids.length === 0) return;
    this.syncToServer(ids).then(() =>
    {
      this.#saveLastSyncTimeToLocalStorage();
    }).catch((error) =>
      console.error("Failed to sync pending bookmarks:", error)
    ).finally(() =>
    {
      this.#pendingSyncIds.clear();
    });
  }

  /**
   * Helper of @see syncToServer to perform the actual sync logic
   */
  async #executeSync(idsToSync?: Parameters<typeof this.syncToServer>[ 0 ]): Promise<void>
  {
    const snapshotBeforeSync = new Set(idsToSync ?? this.#getLatestJobIds); // requestedIds to server or snapshot if full sync
    if (snapshotBeforeSync.size === 0) return; // no jobs to sync, skip API call

    const fetchedJobs = await APIServiceBrowser.syncBookmarkGraphQL([ ...snapshotBeforeSync ]);
    if (fetchedJobs.length === 0) return;

    this.#reconcileCacheWithServer(snapshotBeforeSync, fetchedJobs);

    // persist the successfully synced jobs to IndexedDB, jobs repo are reactive so it will reflect the latest state after reconciliation
    const jobToPersist = this.#bookmarkRepository.cacheJobs.values();
    await this.#bookmarkRepository.bookmarkIDB.saveBookmarks([ ...jobToPersist ]);

    // Handle global full sync metadata broadcast
    if (!idsToSync) this.#prepareGlobalSync(snapshotBeforeSync);
  }

  /**
   * Prepare the result of a global full sync
   * @param snapshotBeforeSync Metadata of the sync operation
   */
  #prepareGlobalSync(snapshotBeforeSync: Set<CardJob[ 'id' ]>): void
  {
    this.#pendingSyncIds.clear();
    //* get latest snapshot from reactive source after reconciliation
    const snapshotAfterSync = new Set(this.#getLatestJobIds);
    const diff = snapshotBeforeSync.difference(snapshotAfterSync);
    diff.forEach((id) => this.#bookmarkRepository.expiredJobs.add(id));
    this.#saveLastSyncTimeToLocalStorage();
  }

  /**
   * @param requestedIds IDs that were requested to be synced with the server, used to compare with server response and determine if any jobs were deleted on the server and need to be removed from cache and marked as expired for user feedback
   * @param fetchedJobs Jobs returned from the server for the requested IDs, used to update the cache with the latest data from the server and ensure consistency across tabs after sync operations
   */
  #reconcileCacheWithServer(requestedIds: Set<CardJob[ 'id' ]>, fetchedJobs: CardJob[]): void
  {

    const serverReturnedIds = new Set(fetchedJobs.map((job) => Number(job.id)));
    const diff = requestedIds.difference(serverReturnedIds); // IDs that were requested but not returned by server, likely deleted on server
    diff.forEach((id) =>
    {
      this.#bookmarkRepository.cacheJobs.delete(Number(id));
      this.#bookmarkRepository.expiredJobs.add(Number(id));
    });

    // Update cache with fetched jobs
    fetchedJobs.forEach((job: CardJob) =>
      this.#bookmarkRepository.cacheJobs.set(Number(job.id), job),
    );
  }

  /**
   * @param idsToSync IDs that failed to sync, will be retried after a delay with debouncing to prevent multiple rapid retries
   */
  #scheduleSyncRetry(idsToSync: Parameters<typeof this.syncToServer>[ 0 ]): void
  {
    if (this.#bookmarkTaskController.isRetryScheduled) return;
    this.warningFromSync = "Sedang mencoba menyinkronkan ulang.";
    this.#bookmarkTaskController.scheduleRetryTask(3000, async () =>
    {
      await this.syncToServer(idsToSync);
    });
  }

  #saveLastSyncTimeToLocalStorage(): void
  {
    this.lastSyncTime.setTime(Date.now());
    useRIC(() => localStorage.setItem(this.#localStorageKey, this.lastSyncTime.getTime().toString()), { timeout: 2000, fallbackDelay: 2000, fallback: "timeout" });

  }

  #init(): void
  {
    if (typeof window === "undefined") return;
    const localStorageSyncTime = Number(localStorage.getItem(this.#localStorageKey));
    localStorageSyncTime && this.lastSyncTime.setTime(localStorageSyncTime);
  }

};

export const bookmarkStore = new BookmarkManager(
  new BookmarkRepository(new BookmarkIDB('JobBookmarks', 1, 'bookmarks')),
  new TaskController(),
);