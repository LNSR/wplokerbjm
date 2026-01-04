<script lang="ts">
  import SearchForm from "@components/ui/Homepage/SearchForm.svelte";
  import JobCarousel from "@components/ui/Homepage/JobCarousel.svelte";
  import JobGrid from "@components/ui/Homepage/JobGrid.svelte";
  import type { CarouselProps, JobGridProps } from "@/types";

  const { carousel, jobGrid } = $props<{
    carousel?: CarouselProps;
    jobGrid?: JobGridProps;
  }>();
</script>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
  <SearchForm />
  <JobCarousel {...carousel} />
  <JobGrid {...jobGrid} />
</main>
