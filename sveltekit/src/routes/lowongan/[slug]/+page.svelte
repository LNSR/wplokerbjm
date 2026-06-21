<script lang="ts">
  import JobDetail from "$lib/components/ui/Shared/JobDetail.svelte";
  import type { JobDetailResponse, CarouselProps, JobGridProps } from "@/types";
  import Homepage from "@/routes/+page.svelte";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";
  interface Props {
    data: {
      job: JobDetailResponse;
      carousel: CarouselProps;
      jobGrid: JobGridProps;
    };
  }
  const props: Props = $props();
  const homepageDesktopData = $derived({
    data: {
      carousel: props.data.carousel,
      jobGrid: props.data.jobGrid,
    },
  });
  const isMobile = $derived(deviceDetector.isPlatformMobile);
  let job: JobDetailResponse | null = $derived(props.data.job ?? null);
</script>

{#if job}
  {#if !isMobile}
    <Homepage {...homepageDesktopData} />
  {:else if isMobile}
    <main
      class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12"
    >
      <JobDetail {job} />
    </main>
  {/if}
{/if}
