<script lang="ts">
  import JobDetail from "$lib/components/ui/Shared/JobDetail.svelte";
  import type { JobDetailResponse, CarouselProps, JobGridProps } from "@/types";
  import Homepage from "@/routes/+page.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  interface Props {
    data: {
      job: JobDetailResponse;
      carousel?: CarouselProps;
      jobGrid?: JobGridProps;
    };
  }
  const props: Props = $props();
  const homepageDesktopData = $derived({
    data: {
      carousel: props.data.carousel,
      jobGrid: props.data.jobGrid,
    },
  });
  let job: JobDetailResponse | null = $derived(props.data.job ?? null);
</script>

{#if job}
  {#if !isMobile()}
    <Homepage {...homepageDesktopData} />
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
