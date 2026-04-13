<script lang="ts">
  import "@css/app.css";
  import { GoogleServices } from "@/services/Google";
  import Header from "$lib/components/layouts/Header.svelte";
  import Footer from "$lib/components/layouts/Footer.svelte";
  import FloatingActionButton from "$lib/components/ui/Shared/FloatingActionButton.svelte";
  import { type Snippet } from "svelte";
  import { afterNavigate, onNavigate, beforeNavigate } from "$app/navigation";
  import { routeStore } from "$lib/stores/Route.svelte";
  import { updated } from "$app/state";
  import type { RankMathHeadData, WPLokerBJMThemedData } from "@/types";
  import type { OnNavigate } from "@sveltejs/kit";
  import { headerManager } from "$lib/components/layouts/Header.svelte";

  let initialPageviewSent = false;

  const {
    children,
    data,
  }: {
    children: Snippet;
    data: {
      themeData: WPLokerBJMThemedData;
      rankMathHead?: Partial<RankMathHeadData> | string;
      inlineScript?: string;
    };
  } = $props();

  const { themeData, rankMathHead, inlineScript } = $derived({
    themeData: data?.themeData,
    rankMathHead: data?.rankMathHead,
    inlineScript: data?.inlineScript,
  });

  beforeNavigate(({ to, willUnload }) => {
    try {
      if (updated.current && !willUnload && to?.url) {
        location.href = to.url.href;
      }
      routeStore.setIsInitialLoad(false);
      routeStore.setIsLoading(true);
      routeStore.setIsTransitioningRoute(true);
    } catch (error) {
      console.error("Error during beforeNavigate:", error);
    }
  });

  onNavigate((navigation: OnNavigate) => {
    if (
      !document.startViewTransition ||
      typeof document.startViewTransition !== "function"
    )
      return;

    return new Promise((resolve, reject) => {
      const transition = document.startViewTransition();
      try {
        transition.ready.then(() => {
          resolve();
        });
      } catch (error) {
        console.error("Error during onNavigate:", error);
        reject(error);
        return;
      } finally {
        if (transition) {
          transition.finished.then(() => {
            navigation.complete;
          });
        }
        return;
      }
    });
  });

  afterNavigate(() => {
    routeStore.setIsLoading(false);
    routeStore.setIsTransitioningRoute(false);
    if (routeStore.isInitialLoad && !initialPageviewSent) {
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
    } else {
      if (GoogleServices.gtmLoaded) {
        GoogleServices.sendPageView();
      }
    }
  });
</script>

<svelte:head>
  {#if routeStore.isInitialLoad && inlineScript}
    {@html inlineScript}
  {/if}
  {#if themeData?.siteIconTags}
    {@html themeData.siteIconTags}
  {/if}
  {#if rankMathHead}
    {@html rankMathHead}
  {/if}
</svelte:head>

<Header {themeData} />
<div
  class="route-container !pt-20"
  style="--site-header-height: {headerManager.currentHeight}px; --site-scroll-padding-top: {headerManager.currentHeight}px; padding-top: {headerManager.currentHeight}px;"
>
  <div class="page-transition">
    {@render children()}
  </div>
  <FloatingActionButton />
</div>
<Footer />

<style lang="postcss">
  @reference "@css/app.css";
  .page-transition {
    transition: opacity 0.1s ease-in-out;
    content-visibility: auto;
    contain-intrinsic-size: auto
      calc(
        100vh -
          (var(--site-header-height, 0px) + var(--site-scroll-padding-top, 0px))
      );
    opacity: 1;
    view-transition-name: auto;
  }

  /* Ensure smooth transitions for route changes */
  .route-container {
    min-height: 100vh;
    transition: all 0.1s ease-in-out;
    position: relative;
  }
</style>
