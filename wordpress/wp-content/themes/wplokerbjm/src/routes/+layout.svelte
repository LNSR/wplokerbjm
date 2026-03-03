<script lang="ts">
  import "@css/app.css";
  import { GoogleServices } from "@/services/Google";
  import Header from "$lib/components/layouts/Header.svelte";
  import Footer from "$lib/components/layouts/Footer.svelte";
  import FloatingActionButton from "$lib/components/ui/Shared/FloatingActionButton.svelte";
  import { onMount } from "svelte";
  import { afterNavigate, onNavigate } from "$app/navigation";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import type { HeadData, WPLokerBJMThemedData } from "@/types";
  import { themeManager } from "@/lib/stores/Theme.svelte";
  import script from "@@/public/js/theme/InlineScript.html?raw";

  onNavigate(() => {
    routeStore.addContainerWillChange();
    routeStore.setIsInitialLoad(false);
    routeStore.isTransitioningRoute = true;

    // return cleanup executed once the new content is mounted
    return () => {
      routeStore.removeContainerWillChange();
    };
  });

  const {
    children,
    data,
  }: {
    children?: any;
    data?: {
      themeData?: WPLokerBJMThemedData | null;
      deviceType?: App.PageData["deviceType"];
      rankMathHead?: HeadData | null;
    };
  } = $props();

  const { themeData, rankMathHead } = $derived({
    themeData: data?.themeData ?? null,
    rankMathHead: data?.rankMathHead ?? null,
  });

  afterNavigate(async (nav: any) => {
    const url =
      nav && nav.to && nav.to.url ? nav.to.url : new URL(window.location.href);

    routeStore.setIsLoading(false);
    routeStore.isTransitioningRoute = false;
    setTimeout(() => {
      GoogleServices.sendPageView();
    }, 1000); // delay to ensure GTM has time to process the route change
  });

  onMount(() => {
    themeManager.setThemeData(themeData as WPLokerBJMThemedData);

    // keep breakpoint observer running
    routeStateStore.observeBreakpointChanges();

    if (routeStore.isInitialLoad) {
      // Only inject GTM script on initial load to avoid duplicates during navigation
      GoogleServices.injectGTMScript()
        .then(() => {
          GoogleServices.sendPageView(); // Send initial pageview after GTM loads
        })
        .catch(() => {
          console.error("Failed to inject GTM script on initial load");
        });
    }
    return () => {
      routeStateStore.cleanUpEffectObserveBreakpointChanges();
    };
  });
</script>

<svelte:head>
  {#if routeStore.isInitialLoad}
    {@html script}
  {/if}
  {#if themeData?.siteIconTags}
    {@html themeData.siteIconTags}
  {/if}
  {#if rankMathHead}
    {@html rankMathHead}
  {/if}
</svelte:head>
<Header {themeData} />
<div class="route-container pt-20">
  <div class="page-transition">
    {@render children()}
  </div>
  <Footer />
</div>
<FloatingActionButton />
