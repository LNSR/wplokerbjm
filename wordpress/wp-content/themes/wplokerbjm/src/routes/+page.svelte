<script lang="ts">
  import SearchForm from "@components/ui/Homepage/SearchForm.svelte";
  import Hero from "@components/ui/Homepage/Hero.svelte";
  import JobCarousel from "@components/ui/Homepage/JobCarousel.svelte";
  import JobGrid from "@components/ui/Homepage/JobGrid.svelte";
  import { page } from "$app/state";
  import type { CarouselProps, JobGridProps } from "@/types";
  const props: {
    data: {
      carousel?: CarouselProps;
      jobGrid?: JobGridProps;
      itemListSchemaScript?: string;
    };
  } = $props();
</script>

<svelte:head>
  {#if page.url.pathname === "/" && props.data?.itemListSchemaScript}
    {@html props.data.itemListSchemaScript}
  {/if}
</svelte:head>

<main class="container mx-auto max-w-[90vw] lg:max-w-[85vw] mt-16">
  <Hero />
  <SearchForm />
  <JobCarousel {...props.data.carousel} />
  <JobGrid {...props.data.jobGrid} />
</main>
