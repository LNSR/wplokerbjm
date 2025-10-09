<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  <!-- Header Skeleton -->
  <div class="animate-pulse">
    <div class="h-12 w-96 bg-gray-300 dark:bg-gray-600 rounded mx-auto mb-8"></div>
  </div>

  <!-- Benefit Section Skeleton -->
  <section class="rounded-xl border-2 border-gray-300 dark:border-gray-600 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-800 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-80 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>

    <div class="space-y-3 mb-4">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
    </div>

    <div class="flex flex-row items-center gap-3 mt-6 p-4 bg-gray-200 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
      <div class="w-5 h-5 bg-yellow-300 dark:bg-yellow-600 rounded mt-1"></div>
      <div class="flex-1">
        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full mb-1"></div>
        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      </div>
    </div>
  </section>

  <!-- Form Section Skeleton -->
  <section class="rounded-xl border-2 border-gray-300 dark:border-gray-600 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-800 animate-pulse">
    <div class="flex items-center gap-2 mb-6">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>

    <div class="space-y-6">
      <!-- Form fields -->
      {#each Array(8) as _}
        <div class="space-y-2">
          <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
          <div class="h-12 bg-gray-300 dark:bg-gray-600 rounded"></div>
        </div>
      {/each}

      <!-- Checkbox options -->
      <div class="space-y-3">
        {#each Array(4) as _}
          <div class="flex items-center gap-3">
            <div class="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
            <div class="h-4 w-48 bg-gray-300 dark:bg-gray-600 rounded"></div>
          </div>
        {/each}
      </div>

      <!-- Submit button -->
      <div class="h-12 w-32 bg-blue-300 dark:bg-blue-600 rounded-lg mx-auto"></div>
    </div>
  </section>

  <!-- Contact Section Skeleton -->
  <section class="rounded-xl border-2 border-gray-300 dark:border-gray-600 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-800 animate-pulse">
    <div class="flex items-center gap-2 mb-6">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-40 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      {#each Array(4) as _}
        <div class="space-y-2">
          <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
          <div class="h-10 bg-gray-300 dark:bg-gray-600 rounded"></div>
        </div>
      {/each}
    </div>

    <div class="mt-6">
      <div class="h-4 w-64 bg-gray-300 dark:bg-gray-600 rounded mb-3"></div>
      <div class="h-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
  </section>

  <!-- Terms Section Skeleton -->
  <section class="rounded-xl border-2 border-gray-300 dark:border-gray-600 shadow-md p-4 sm:p-6 md:p-8 mb-12 bg-white dark:bg-gray-800 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-36 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>

    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
    </div>

    <div class="flex items-center gap-3 mt-6">
      <div class="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-4 w-64 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
  </section>
</main>
