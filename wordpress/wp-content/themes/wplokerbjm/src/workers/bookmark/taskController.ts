import { retry, delay, withTimeout, Mutex, debounce } from "es-toolkit";
import type { CardJob, SyncToServer } from "@/types";
/**
* Task controller to manage sequential execution of operations and retries with timeout handling.
*/
export class BookmarkTaskController
{
    #retryTimer: AbortController | null = null;
    #operationMutex = new Mutex();
    public get isRetryScheduled(): boolean { return this.#retryTimer !== null; }

    public scheduleRetryTask = (delayMs: number, task: () => Promise<void>): void =>
    {
        if (this.#retryTimer) return;

        let controller: AbortController | null = new AbortController();
        this.#retryTimer = controller;
        const options: Parameters<typeof retry>[ 1 ] = {
            delay: 0,
            retries: 2,
            signal: controller.signal,
        };

        const executeTask = async (): Promise<void> =>
        {
            await delay(delayMs);
            if (controller?.signal.aborted) throw new Error("retry cancelled");
            await task();
        };

        retry(async () => await executeTask(), options).catch((e: unknown) =>
        {
            if (!controller?.signal.aborted) console.error("Scheduled retry task failed:", e);
        }).finally(() =>
        {
            if (this.#retryTimer === controller) this.#retryTimer = null;
        });
    }

    public cancelRetry = (): void =>
    {
        if (this.#retryTimer) this.#retryTimer.abort();
        this.#retryTimer = null;
    }

    /**
  * Run operation in sequence to avoid race conditions on IndexedDB and ensure predictable state updates. Each operation waits for the previous one to complete before starting.
  * A timeout is included to prevent the queue from getting stuck indefinitely due to unforeseen issues. If an operation fails or times out, it logs a warning but allows subsequent operations to continue.
  * @param operation The asynchronous operation to run in the queue.
  * @param timeoutMs The maximum time to wait for the operation to complete before timing out.
  * @returns A promise that resolves with the operation's result or rejects if it times out.
  */
    public runQueued = async <T>(
        operation: () => Promise<T>,
        timeoutMs: number = 10000,
    ): Promise<T> =>
    {
        await this.#operationMutex.acquire();
        return await withTimeout(operation, timeoutMs).catch((e: unknown) =>
        {
            console.warn("Operation failed or timed out:", e);
            throw e;
        }).finally(() =>
        {
            if (this.#operationMutex.isLocked) this.#operationMutex.release();
        });
    }
}

export class BookmarkSyncQueueTask
{
    #pendingSyncIds = new Set<CardJob[ 'id' ]>(); // unique IDs pending sync
    #debouncedSync = debounce(async () => await this.syncPending(), 2500);
    #syncCommand?: SyncToServer[ 'syncToServer' ];
    public async syncPending(): Promise<void>
    {
        const ids = [ ...this.#pendingSyncIds ];
        if (ids.length === 0) return;
        await this.#syncCommand?.(ids).catch((error) =>
            console.error("Failed to sync pending bookmarks:", error)
        ).finally(() =>
        {
            this.clearPendingSyncIds();
        });
    }

    public syncCommand(command: SyncToServer[ 'syncToServer' ])
    {
        this.#syncCommand = command;
    }

    /**
    * 
    * @param ids IDs to be queued for syncing, will be debounced to batch multiple rapid calls into one sync operation
    */
    public queueIdsForSync(ids: Parameters<SyncToServer[ 'syncToServer' ]>[ 0 ]): void
    {
        if (!ids || ids.length === 0) return;
        ids.forEach((id) => this.#pendingSyncIds.add(id));
        this.#debouncedSync?.();
    }

    public clearPendingSyncIds(): void
    {
        this.#pendingSyncIds.clear();
    }
}