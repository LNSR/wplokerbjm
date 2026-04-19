import { retry, delay, withTimeout, Mutex } from "es-toolkit";
/**
* Task controller to manage sequential execution of operations and retries with timeout handling.
*/
export class TaskController
{
    #retryTimer: AbortController | null = null;
    #operationMutex = new Mutex();
    public get isRetryScheduled(): boolean
    {
        return this.#retryTimer !== null;
    }

    public scheduleRetryTask = (delayMs: number, task: () => Promise<void>): void =>
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
                await task();
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
        const acquireLock = async () =>
        {
            await this.#operationMutex.acquire();
            return {
                [ Symbol.dispose ]: () =>
                {
                    this.#operationMutex.isLocked && this.#operationMutex.release();
                },
            };
        }

        using _lock = await acquireLock();

        return await withTimeout(operation, timeoutMs).catch((e: unknown) =>
        {
            console.warn("Operation failed or timed out:", e);
            throw e;
        });
    }
}