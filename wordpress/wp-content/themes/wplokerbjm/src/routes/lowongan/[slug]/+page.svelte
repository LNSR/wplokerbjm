<script lang="ts">
  import JobDetail from "$lib/components/ui/Shared/JobDetail.svelte";
  import type { JobDetailResponse as SingleJob } from "@/types";
  import Homepage from "@/routes/+page.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  const props = $props<{
    data?: { job?: SingleJob; jobSchema?: any | null };
  }>();

  let job: SingleJob | null = $derived(props.data?.job ?? null);
</script>

{#if job}
  {#if !isMobile()}
    <Homepage />
  {:else if isMobile()}
    <main
      class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12"
    >
      <JobDetail {job} />
    </main>
  {/if}
{:else}
  <!-- minimal not-found fallback -->
  <main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
    <h1 class="text-xl font-semibold">Lowongan tidak ditemukan</h1>
    <p class="text-gray-600">
      Maaf, lowongan yang Anda cari tidak tersedia atau telah dihapus.
    </p>
  </main>
{/if}
