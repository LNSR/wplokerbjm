<script lang="ts">
  import SearchForm from "#lib/components/ui/Homepage/SearchForm.svelte";
  import Hero from "#lib/components/ui/Homepage/Hero.svelte";
  import JobCarousel from "#lib/components/ui/Homepage/JobCarousel.svelte";
  import JobGrid from "#lib/components/ui/Homepage/JobGrid.svelte";
  import { page } from "$app/state";
  import type { PageProps } from "./$types";
  import type { CarouselProps, JobGridProps } from "#src/types/index.js";

  interface Props {
    data: {
      carousel: CarouselProps;
      jobGrid: JobGridProps;
      itemListSchemaScript?: PageProps['data']['itemListSchemaScript'];
    };
  }

  const props: Props = $props();
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
