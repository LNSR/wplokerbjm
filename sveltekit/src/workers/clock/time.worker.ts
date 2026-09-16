/// <reference lib="webworker" />

class TimeInterval {
  #timeoutId: ReturnType<typeof setTimeout> | null = null;

  public start() {
    const msUntilNextMinute = 60000 - (Date.now() % 60000); // self correct according to the next minute boundary to avoid drift
    this.#timeoutId ??= setTimeout(
      () => this.#scheduleNextTick(),
      msUntilNextMinute,
    );
  }

  public stop() {
    if (this.#timeoutId) {
      clearTimeout(this.#timeoutId);
      this.#timeoutId = null;
    }
  }

  #scheduleNextTick() {
    this.stop();
    this.start();
    self.postMessage("tick");
  }
}

const timeInterval = new TimeInterval();
timeInterval.start();

self.onmessage = (e: MessageEvent) => {
  if (e.data === "stop") {
    timeInterval.stop();
    self.close();
  }
};
