<main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
  <!-- Header: Title + Summary (matches JobDetail header) -->
  <section class="overflow-hidden p-6 rounded-xl bg-[var(--wpl-global-color-5)] animate-pulse">
    <div class="grid grid-cols-1 pt-6">
      <div class="flex items-start gap-4 flex-col">
        <div class="w-full flex flex-col">
          <div class="justify-between flex flex-row items-start gap-2">
            <div class="h-8 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
            <div class="h-8 w-10 bg-gray-300 dark:bg-gray-600 rounded"></div>
          </div>

          <div class="flex flex-col wrap-anywhere mb-4 mt-4">
            <div class="items-center gap-2 mb-2">
              <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded inline-block"></div>
              <div class="h-5 w-40 bg-gray-300 dark:bg-gray-600 rounded ml-2"></div>
            </div>
            <div class="items-center gap-2 mb-2">
              <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded inline-block"></div>
              <div class="h-5 w-32 bg-gray-300 dark:bg-gray-600 rounded ml-2"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm md:text-base">
        {#each Array(3)}
          <div class="flex items-start gap-2">
            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
            <div class="w-full">
              <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-1/2 mb-1"></div>
              <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
            </div>
          </div>
        {/each}
      </div>
    </div>
  </section>

  <!-- Tentang Perusahaan -->
  <section class="wysiwyg-content px-3 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-4/5"></div>
    </div>
  </section>

  <!-- Deskripsi Pekerjaan -->
  <section class="wysiwyg-content px-3 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-48 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
    </div>
  </section>

  <!-- Persyaratan -->
  <section class="wysiwyg-content px-3 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-40 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      {#each Array(4)}
        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      {/each}
    </div>
  </section>

  <!-- Cara Melamar -->
  <section class="wysiwyg-content px-3 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-44 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
    </div>
  </section>

  <!-- Benefit -->
  <section class="wysiwyg-content px-3 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-36 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="space-y-3">
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full"></div>
      <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
    </div>
  </section>

  <!-- Kontak -->
  <section class="aside-content p-4 animate-pulse">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
        <div class="h-6 w-28 bg-gray-300 dark:bg-gray-600 rounded"></div>
      </div>
    </div>
    <div class="grid grid-cols-1 gap-4 mt-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
        {#each Array(6)}
          <div class="flex items-center">
            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded mr-2"></div>
            <div class="flex-1">
              <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded mb-1"></div>
              <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
            </div>
          </div>
        {/each}
      </div>
    </div>
  </section>

  <!-- Sosial Media -->
  <section class="aside-content p-4 animate-pulse">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded"></div>
      <div class="h-6 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
    </div>
    <div class="grid grid-cols-1 gap-4 mt-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
        {#each Array(3)}
          <div class="flex items-center">
            <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded mr-2"></div>
            <div class="flex-1">
              <div class="h-4 w-24 bg-gray-300 dark:bg-gray-600 rounded mb-1"></div>
              <div class="h-4 w-32 bg-gray-300 dark:bg-gray-600 rounded"></div>
            </div>
          </div>
        {/each}
      </div>
    </div>
  </section>
</main>
