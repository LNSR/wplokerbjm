export interface ConcurrencyOptions {
  concurrency: number;
  continueOnError?: boolean;
  onProgress?: (completed: number, total: number, url: string) => void;
  onError?: (url: string, error: string) => void;
}

export interface ConcurrencyResult {
  successful: number;
  failed: number;
  totalTime: number;
  averageTimePerPage: number;
  results: Array<{ url: string; success: boolean; error?: string; duration?: number }>;
}

export class Semaphore {
  private permits: number;
  private waiting: Array<() => void> = [];

  constructor(permits: number) {
    this.permits = permits;
  }

  async acquire(): Promise<() => void> {
    if (this.permits > 0) {
      this.permits--;
      return () => this.release();
    }

    return new Promise((resolve) => {
      this.waiting.push(() => {
        this.permits--;
        resolve(() => this.release());
      });
    });
  }

  private release(): void {
    this.permits++;
    if (this.waiting.length > 0) {
      const next = this.waiting.shift();
      if (next) next();
    }
  }
}

export class ConcurrencyManager {
  private semaphore: Semaphore;
  private options: ConcurrencyOptions;

  constructor(options: ConcurrencyOptions) {
    this.semaphore = new Semaphore(options.concurrency);
    this.options = options;
  }

  async processTasks<T>(
    tasks: Array<{
      id: string;
      execute: () => Promise<T>;
    }>
  ): Promise<ConcurrencyResult> {
    const results: Array<{ url: string; success: boolean; error?: string; duration?: number }> = [];
    const startTime = Date.now();

    console.log(`Starting processing of ${tasks.length} tasks with concurrency ${this.options.concurrency}...`);
    if (this.options.continueOnError) {
      console.log('Continuing on errors enabled');
    }

    // Process tasks in parallel with controlled concurrency
    const promises = tasks.map(async (task, index) => {
      return this.semaphore.acquire().then(async (release) => {
        const taskStartTime = Date.now();

        try {
          this.options.onProgress?.(index + 1, tasks.length, task.id);
          await task.execute();

          const duration = Date.now() - taskStartTime;
          results.push({ url: task.id, success: true, duration });

        } catch (error) {
          const errorMessage = error instanceof Error ? error.message : String(error);
          console.error(`Failed to process ${task.id}: ${errorMessage}`);
          this.options.onError?.(task.id, errorMessage);

          const duration = Date.now() - taskStartTime;
          results.push({ url: task.id, success: false, error: errorMessage, duration });

          if (!this.options.continueOnError) {
            throw error;
          }
        } finally {
          release();
        }
      });
    });

    await Promise.all(promises);

    const endTime = Date.now();
    const totalTime = (endTime - startTime) / 1000;

    // Report results
    const successful = results.filter(r => r.success).length;
    const failed = results.filter(r => !r.success).length;
    const averageTimePerPage = tasks.length > 0 ? (totalTime / tasks.length) * 1000 : 0;

    console.log(`\nProcessing Summary:`);
    console.log(`✅ Successful: ${successful}`);
    console.log(`❌ Failed: ${failed}`);
    console.log(`⏱️  Total time: ${totalTime.toFixed(2)}s`);
    console.log(`📊 Average: ${averageTimePerPage.toFixed(0)}ms per task`);

    if (failed > 0) {
      console.log(`\nFailed tasks:`);
      results.filter(r => !r.success).forEach(result => {
        console.log(`  - ${result.url}: ${result.error}`);
      });

      if (!this.options.continueOnError) {
        throw new Error(`${failed} tasks failed to process`);
      }
    }

    return {
      successful,
      failed,
      totalTime,
      averageTimePerPage,
      results
    };
  }
}

// Convenience function for processing URLs with concurrency
export async function processWithConcurrency<T>(
  items: string[],
  processor: (item: string) => Promise<T>,
  options: ConcurrencyOptions
): Promise<ConcurrencyResult> {
  const manager = new ConcurrencyManager(options);

  const tasks = items.map(item => ({
    id: item,
    execute: () => processor(item)
  }));

  return manager.processTasks(tasks);
}