<script lang="ts">
  import "@css/app.css";
  import { GoogleServices } from "@/services/Google";
  import Header from "$lib/components/layouts/Header.svelte";
  import Footer from "$lib/components/layouts/Footer.svelte";
  import FloatingActionButton from "$lib/components/ui/Shared/FloatingActionButton.svelte";
  import { onMount } from "svelte";
  import { afterNavigate, onNavigate } from "$app/navigation";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import type { RankMathHeadData, WPLokerBJMThemedData } from "@/types";
  import { themeManager } from "@/lib/stores/Theme.svelte";
  import script from "@@/public/js/theme/InlineScript.html?raw";

  let initialPageviewSent = false;

  onNavigate(() => {
    routeStore.setIsInitialLoad(false);
    routeStore.isTransitioningRoute = true;
  });

  const {
    children,
    data,
  }: {
    children?: any;
    data?: {
      themeData?: WPLokerBJMThemedData | null;
      deviceType?: App.PageData["deviceType"];
      rankMathHead?: RankMathHeadData | null;
    };
  } = $props();

  const { themeData, rankMathHead } = $derived({
    themeData: data?.themeData ?? null,
    rankMathHead: data?.rankMathHead ?? null,
  });

  afterNavigate(() => {
    routeStore.setIsLoading(false);
    routeStore.isTransitioningRoute = false;
    if (routeStore.isInitialLoad) {
      if (!initialPageviewSent) {
        GoogleServices.injectGTMScript()
          .then(() => {
            if (GoogleServices.gtmLoaded) {
              GoogleServices.sendPageView();
              initialPageviewSent = true;
            }
          })
          .catch(() => {
            console.error("Failed to inject GTM script on initial load");
          });
      }
    } else {
      if (GoogleServices.gtmLoaded) {
        GoogleServices.sendPageView();
      }
    }
  });

  onMount(() => {
    themeManager.setThemeData(themeData as WPLokerBJMThemedData);
    routeStateStore.setInitialDevice(
      data?.deviceType?.isMobile ? "mobile" : "desktop",
    );

    routeStateStore.observeBreakpointChanges();

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
