<script lang="ts">
  import SearchForm from "@components/ui/Homepage/SearchForm.svelte";
  import JobCarousel from "@components/ui/Homepage/JobCarousel.svelte";
  import JobGrid from "@components/ui/Homepage/JobGrid.svelte";
  import type { CarouselProps, JobGridProps, JobDetailResponse } from "@/types";

  const { carousel, jobGrid, job } = $props<{
    carousel?: CarouselProps;
    jobGrid?: JobGridProps;
    job?: JobDetailResponse | undefined;
  }>();
</script>

<main class="container mx-auto max-w-[90vw] lg:max-w-[85vw] space-y-8 mt-12">
  <SearchForm />
  <JobCarousel {...carousel} />
  <JobGrid {...jobGrid} {job} />
</main>
