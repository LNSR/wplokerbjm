<script lang="ts">
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
</script>

<main
  class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12"
  style:padding-top={headerStore.totalOffset + 'px'}
>
  <!-- Job Title Skeleton -->
  <section class="top-0 backdrop-blur">
    <div class="flex items-center justify-center gap-4 animate-pulse">
      <div class="h-8 w-64 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="text-sm mt-2 flex items-center justify-center gap-2 animate-pulse">
      <div class="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
  </section>

  <div class="divider"></div>

  <!-- Company Name Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- About Company Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-40 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Job Summary Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-36 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="gap-4 mt-4">
      <div class="gap-x-6 gap-y-4 text-lg ml-2">
        {#each Array(5) as _}
          <div class="flex items-start lg:space-x-2 space-x-1 mb-2">
            <div class="w-3 h-3 bg-gray-300 dark:bg-gray-600 rounded mt-2"></div>
            <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
            <div class="h-4 w-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
            <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
          </div>
        {/each}
      </div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Job Description Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-40 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Requirements Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- How to Apply Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-36 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Benefits Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Contact Info Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
        <div class="h-6 w-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
      </div>
    </div>
    <div class="grid grid-cols-1 gap-4 mt-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
        {#each Array(6) as _}
          <div class="flex items-center">
            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded mr-2"></div>
            <div class="flex-1">
              <div class="h-4 w-16 bg-gray-300 dark:bg-gray-600 rounded mb-1"></div>
              <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
            </div>
          </div>
        {/each}
      </div>
    </div>
    <div class="divider"></div>
  </section>

  <!-- Social Media Skeleton -->
  <section class="animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-28 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="grid grid-cols-1 gap-4 mt-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
        {#each Array(3) as _}
          <div class="flex items-center">
            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded mr-2"></div>
            <div class="flex-1">
              <div class="h-4 w-20 bg-gray-300 dark:bg-gray-600 rounded mb-1"></div>
              <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
            </div>
          </div>
        {/each}
      </div>
    </div>
  </section>
</main>