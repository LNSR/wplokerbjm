<script lang="ts">
  import "@css/app.css";
  import {
    deviceDetector,
    type DeviceDetectorInternal,
  } from "$lib/features/DeviceDetector.svelte";
  import { GoogleServices } from "@/services/Google";
  import { routeStore } from "$lib/stores/Route.svelte";
  import Header, { headerManager } from "$lib/components/layouts/Header.svelte";
  import Footer from "$lib/components/layouts/Footer.svelte";
  import FloatingActionButton from "$lib/components/ui/Shared/FloatingActionButton.svelte";
  import { afterNavigate, onNavigate, beforeNavigate } from "$app/navigation";
  import { updated } from "$app/state";
  import type { OnNavigate } from "@sveltejs/kit";
  import type { LayoutProps } from "./$types";
  import inlinedScript from "@/utils/inlineScript?inline-script";
  import { themePropsStore } from "@/lib/stores/Theme.svelte";
  import { untrack } from "svelte";

  let initialPageviewSent = false;

  const { children, data }: LayoutProps = $props();

  const { themeData, rankMathHead, deviceType } = $derived({
    themeData: data?.themeData!,
    rankMathHead: data?.rankMathHead,
    deviceType: data?.deviceType,
  });

  beforeNavigate(({ to, willUnload }) => {
    try {
      if (updated.current && !willUnload && to?.url)
        location.href = to.url.href;

      routeStore.setIsInitialLoad = false;
      routeStore.setIsLoading = true;
      routeStore.setIsTransitioningRoute = true;
    } catch (error) {
      console.error("Error during beforeNavigate:", error);
    }
  });

  onNavigate((navigation: OnNavigate) => {
    if (
      typeof document.startViewTransition !== "function" ||
      document.activeViewTransition ||
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
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
        return reject(error);
      } finally {
        if (transition) {
          transition.finished.then(() => {
            return navigation.complete;
          });
        }
      }
    });
  });

  afterNavigate(() => {
    routeStore.setIsLoading = false;
    routeStore.setIsTransitioningRoute = false;
    if (
      !routeStore.isInitialLoad &&
      initialPageviewSent &&
      GoogleServices.gtmLoaded
    )
      return GoogleServices.sendPageView();

    GoogleServices.injectGTMScript()
      .then(() => {
        if (GoogleServices.gtmLoaded) {
          GoogleServices.sendPageView();
          initialPageviewSent ||= true;
        }
      })
      .catch(() => {
        console.error("Failed to inject GTM script on initial load");
      });
  });

  // IIFE to avoid closure Svelte warning; set initialDeviceSSR for DeviceDetector during SSR
  (() => {
    if (deviceType)
      (deviceDetector as DeviceDetectorInternal).initialDeviceSSR =
        deviceType.isMobile ? "mobile" : "desktop";
  })();
</script>

<svelte:head>
  {#if routeStore.isInitialLoad}
    {@html `<script id="wplokerbjm-theme-inline-script">${inlinedScript}</script>`}
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
