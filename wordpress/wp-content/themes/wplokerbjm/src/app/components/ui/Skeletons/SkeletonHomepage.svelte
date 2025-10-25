
<script lang="ts">
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
</script>

<main
  class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8"
  style:padding-top={headerStore.totalOffset + 'px'}
>
  <!-- Search Form Skeleton -->
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
    <div class="animate-pulse">
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
          <div class="h-12 bg-gray-300 dark:bg-gray-600 rounded-lg"></div>
        </div>
        <div class="md:w-48">
          <div class="h-12 bg-gray-300 dark:bg-gray-600 rounded-lg"></div>
        </div>
        <div class="md:w-32">
          <div class="h-12 bg-gray-300 dark:bg-gray-600 rounded-lg"></div>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 mt-4">
        <div class="h-8 w-20 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
        <div class="h-8 w-24 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
        <div class="h-8 w-16 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
        <div class="h-8 w-18 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
      </div>
    </div>
  </div>

  <!-- Job Carousel Skeleton -->
  <div class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px]">
    <div class="flex items-center md:justify-between mb-4">
      <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
      <div class="hidden sm:flex gap-1">
        <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
        <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
      </div>
    </div>

    <div class="swiper job-carousel invisible">
      <div class="swiper-wrapper">
        {#each Array(4) as _}
          <div class="swiper-slide">
            <div class="block group rounded-xl transition-all duration-300 cursor-pointer carousel-card max-w-full border-2 border-gray-300 dark:border-gray-600 shadow-md animate-pulse">
              <div class="card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full">
                <div class="flex-1 flex flex-col justify-start">
                  <div class="flex items-center justify-between mb-2 gap-x-2">
                    <div class="h-6 w-3/4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                    <div class="h-4 w-16 bg-gray-300 dark:bg-gray-600 rounded"></div>
                  </div>

                  <div class="h-4 w-1/2 bg-gray-300 dark:bg-gray-600 rounded mb-2"></div>
                  <div class="divider mt-0"></div>

                  <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                    <div class="h-4 w-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
                    <div class="h-4 w-16 bg-gray-300 dark:bg-gray-600 rounded"></div>
                    <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
                  </div>
                </div>

                <div class="divider my-2"></div>

                <div class="flex items-center justify-between font-semibold">
                  <div class="h-6 w-16 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                  <div class="flex items-center gap-2 ml-auto">
                    <div class="h-8 w-8 bg-gray-300 dark:bg-gray-600 rounded"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/each}
      </div>
      <div class="flex justify-center mt-24">
        <div class="flex gap-2">
          <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full animate-pulse"></div>
          <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full animate-pulse"></div>
          <div class="w-2 h-2 bg-gray-300 dark:bg-gray-600 rounded-full animate-pulse"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Job Grid Skeleton -->
  <section class="relative mt-8">
    <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded animate-pulse mb-6"></div>
    <div class="h-4 w-64 bg-gray-300 dark:bg-gray-600 rounded animate-pulse mb-4"></div>

    <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
      {#each Array(6) as _}
        <div class="block group rounded-xl transition-all duration-300 cursor-pointer w-full h-full max-w border-2 border-gray-300 dark:border-gray-600 shadow-lg animate-pulse">
          <div class="card-body relative p-4 gap-1 flex flex-col h-full">
            <div class="flex-1 flex flex-col justify-start">
              <div class="flex items-center justify-between mb-2 gap-x-2">
                <div class="h-6 w-3/4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div class="h-4 w-16 bg-gray-300 dark:bg-gray-600 rounded"></div>
              </div>

              <div class="h-4 w-1/2 bg-gray-300 dark:bg-gray-600 rounded mb-6"></div>
              <div class="divider -mt-4"></div>

              <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                <div class="h-4 w-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div class="h-4 w-16 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded"></div>
              </div>
            </div>

            <div class="divider my-2"></div>

            <div class="flex items-center justify-between font-semibold">
              <div class="h-6 w-16 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
              <div class="flex items-center gap-2 ml-auto">
                <div class="h-8 w-8 bg-gray-300 dark:bg-gray-600 rounded"></div>
              </div>
            </div>
          </div>
        </div>
      {/each}
    </div>

    <!-- Loading indicator -->
    <div class="flex justify-center mt-8">
      <div class="animate-spin h-8 w-8 text-[var(--wpl-global-color-1)]"></div>
    </div>
  </section>
</main>