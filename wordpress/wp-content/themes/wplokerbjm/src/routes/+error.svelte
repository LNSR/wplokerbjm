<script lang="ts">
  import { CircleQuestionRegular } from "svelte-awesome-icons";
</script>

<main
  class="container flex min-h-screen mx-auto items-center justify-center text-center max-w-[90vw]"
>
  <section class="sectionClass" aria-labelledby="page-error-title">
    <div class="flex flex-row justify-center gap-6 space-y-6 max-w-full">
      <h1 id="page-error-title" class="text-2xl items-center font-bold">
        Oops! Terjadi kesalahan
      </h1>
      <CircleQuestionRegular
        class="w-8 h-8 bg-[var(--wpl-global-color-5)] items-center"
        aria-hidden="true"
      />
    </div>
    <p class="text-[var(--wpl-global-color-1)] text-lg">
      Maaf, halaman yang Anda cari tidak tersedia
    </p>
  </section>
</main>
