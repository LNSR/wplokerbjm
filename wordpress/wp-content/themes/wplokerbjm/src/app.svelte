<script module lang="ts">
  import { onMount, type Component } from "svelte";
  import { fade } from "svelte/transition";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import { GoogleServices } from "@/services/Google";

  const pathname = $derived(routeStore.currentUrl.pathname);
  const isLoading = $derived(routeStore.isLoading);
  const isInitialLoad = $derived(routeStore.isInitialLoad);
  const loadingComponent = $derived(routeStore.loadingComponent);
  const componentNamePath = $derived(routeStore.getComponentNamePath(pathname));
  let CurrentComponent: Component | null = $state(null);

  class AppRouteHandler {
    static async loadRoute(
      importPromise: Promise<any>,
      componentNamePath: string
    ): Promise<void> {
      const loadForPath = pathname;
      try {
        routeStore.setIsLoading(true, componentNamePath);
        const m = await importPromise;
        if (loadForPath !== pathname) return; // prevent race condition
        CurrentComponent = m;
      } catch (err) {
        CurrentComponent = null;
        console.error("Error loading route component:", pathname, err);
      } finally {
        routeStore.setIsLoading(false);
      }
    }
    static handlePopstate(): void {
      const newPath = window.location.pathname;
      routeStore.currentUrl.href = window.location.href;
      routeStore.setIsInitialLoad(false);
      routeStore.setIsLoading(true, componentNamePath);

      // Perform route transition side effects
      routeStore.performRouteTransitionSideEffects(newPath);

      // Add loading timeout for popstate as well
      if (routeStore.isLoading) {
        routeStore.setIsLoading(false);
      }
    }
  }
</script>

<script lang="ts">
  import FloatingActionButton from "@components/ui/Shared/FloatingActionButton.svelte";
  import SkeletonHomepage from "@components/ui/Skeletons/SkeletonHomepage.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";
  import SkeletonPasangIklanLoker from "@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte";
  import Header from "@components/layouts/Header.svelte";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";

  const props = $props();

  const mapComponentName = (name: typeof componentNamePath) => {
    switch (name) {
      case "Homepage":
        return dynamicComponentStore.loadHomepage();
      case "PasangIklanLoker":
        return dynamicComponentStore.loadPasangIklanLoker();
      case "SingleLowongan":
        return dynamicComponentStore.loadSingleLowongan();
    }
    return undefined;
  };

  $effect(() => {
    const importPromise = mapComponentName(componentNamePath);
    if (importPromise) {
      AppRouteHandler.loadRoute(importPromise, componentNamePath);
    } else {
      CurrentComponent = null;
    }
  });

  // Scroll restoration for non-homepage routes (homepage handles its own(job-grid))
  $effect(() => {
    if (!isInitialLoad) {
      routeStateStore.restoreScrollForPath(pathname);
    }
  });

  onMount(() => {
    routeStore.setCurrentPath(window.location.pathname);
    GoogleServices.injectGTMScript()
      .then(() => {
        GoogleServices.sendPageView(); // Send initial pageview after GTM loads
      })
      .catch(() => {
        console.error("Failed to inject GTM script on initial load");
      });

    // Listen to browser back/forward
    if (typeof window !== "undefined") {
      window.addEventListener("popstate", AppRouteHandler.handlePopstate);

      // Return cleanup function
      return () => {
        window.removeEventListener("popstate", AppRouteHandler.handlePopstate);
      };
    }
  });
</script>

<div class="route-container">
  <Header />
  {#key pathname}
    {#if isLoading && loadingComponent === "Homepage"}
      <div
        class="page-transition fade-in"
        style:padding-top="{headerStore.totalOffset}px"
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonHomepage />
      </div>
    {:else if isLoading && loadingComponent === "PasangIklanLoker"}
      <div
        class="page-transition fade-in"
        style:padding-top="{headerStore.totalOffset}px"
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonPasangIklanLoker />
      </div>
    {:else if isLoading && loadingComponent === "SingleLowongan"}
      <div
        class="page-transition fade-in"
        style:padding-top="{headerStore.totalOffset}px"
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonSingleLowongan />
      </div>
    {:else if CurrentComponent}
      <div
        class="page-transition fade-in"
        style:padding-top="{headerStore.totalOffset}px"
        in:fade={{ duration: 250 }}
        out:fade={{ duration: 200 }}
      >
        <CurrentComponent {...isInitialLoad ? props : {}} />
      </div>
    {/if}
  {/key}
  <FloatingActionButton />
</div>

<style lang="postcss">
  /* Page transition styles */
  .page-transition {
    transition: opacity 0.3s ease-in-out;
    content-visibility: auto;
    contain: layout style paint;
    /* Reserve viewport space using header CSS vars to avoid layout shifts */
    contain-intrinsic-size: auto
      calc(
        100vh - (var(--site-header-height, 0px) + var(--site-header-top, 0px))
      );
  }

  .page-transition.fade-in {
    opacity: 1;
  }

  /* Ensure smooth transitions for route changes */
  .route-container {
    min-height: 100vh;
    transition: all 0.3s ease-in-out;
  }
</style>
