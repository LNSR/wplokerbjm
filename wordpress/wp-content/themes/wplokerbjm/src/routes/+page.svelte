<script lang="ts">
  import SearchForm from "@components/ui/Homepage/SearchForm.svelte";
  import Hero from "@components/ui/Homepage/Hero.svelte";
  import JobCarousel from "@components/ui/Homepage/JobCarousel.svelte";
  import JobGrid from "@components/ui/Homepage/JobGrid.svelte";
  import { page } from "$app/state";
  import { schemaScriptAttach } from "@/utils";
</script>

<svelte:head>
  {#if page.url.pathname === "/"}
  {@const schemaItemList = page.data?.itemListSchema}
  {@html schemaScriptAttach(schemaItemList, "ItemList")}
  {/if}
</svelte:head>

<main class="container mx-auto max-w-[90vw] lg:max-w-[85vw] mt-16">
  <Hero />
  <SearchForm />
  <JobCarousel {...page.data?.carousel ?? {}} />
  <JobGrid {...page.data?.jobGrid ?? {}} />
</main>
