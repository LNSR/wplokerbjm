import { createSubscriber, SvelteDate } from 'svelte/reactivity'

/**
 * A Svelte store that provides a reactive Date object which updates every minute.
 * it stores "interval" for global use to prevent multiple intervals being created when multiple components subscribe to it. 
 */
class TimeIntervalStore
{
    #svelteDate = new SvelteDate(); // non-reactive if used in non-tracking context
    #intervalId: ReturnType<typeof setInterval> | null = null;

    #subscribeToTime = createSubscriber((update) =>
    {
        this.#intervalId ??= setInterval(() =>
        {
            const now = Date.now();
            this.#svelteDate.setTime(now);
            update();
        }, 60000); // Update every 1 minute

        // clean up the interval when there's no subscriber left to prevent memory leaks
        return () =>
        {
            if (this.#intervalId)
            {
                clearInterval(this.#intervalId);
                this.#intervalId = null;
            }
        };
    });

    /**
     * Interval Hub
     */
    public get getNowReactiveDate()
    {
        this.#subscribeToTime();
        return this.#svelteDate;
    }
}

export const timeIntervalStore = new TimeIntervalStore();