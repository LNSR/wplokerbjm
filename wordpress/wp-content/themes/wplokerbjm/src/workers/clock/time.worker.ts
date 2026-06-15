/// <reference lib="webworker" />

class TimeInterval
{
    static #timeoutId: ReturnType<typeof setTimeout> | null = null

    public static start()
    {
        const msUntilNextMinute = 60000 - (Date.now() % 60000); // self correct according to the next minute boundary to avoid drift
        this.#timeoutId ??= setTimeout(() => this.#scheduleNextTick(), msUntilNextMinute);
    }

    public static stop()
    {
        if (this.#timeoutId)
        {
            clearTimeout(this.#timeoutId);
            this.#timeoutId = null;
        }
    }

    static #scheduleNextTick()
    {
        this.stop();
        this.start();
        self.postMessage('tick');
    }
}

TimeInterval.start();
self.onmessage = (e: MessageEvent) =>
{
    if (e.data === "stop")
    {
        TimeInterval.stop();
        self.close();
    }
};
