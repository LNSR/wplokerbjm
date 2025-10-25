<script module lang="ts">
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import { GoogleServices } from "$lib/utils/Google.svelte";
  import { removeJobPostingJsonLd } from "$lib/utils/elements.svelte";
  import { SEOService } from "$lib/utils/SEO.svelte";

  let pathname = $derived(routeStore.currentUrl.pathname);
  let isLoading = $derived(routeStore.isLoading);
  let loadingComponent = $derived(routeStore.loadingComponent);
  let componentNamePath = $derived(routeStore.getComponentNamePath(pathname));
  let CurrentComponent: typeof SvelteComponent | null = $state(null);

  class RouteHandler {
    static async loadRoute(
      importPromise: Promise<any>,
      componentNamePath: string
    ): Promise<void> {
      const loadForPath = pathname;
      try {
        routeStore.setIsLoading(true, componentNamePath);
        const m = await importPromise;
        if (loadForPath !== pathname) return; // prevent race condition
        CurrentComponent = m.default;
      } catch (err) {
        CurrentComponent = null;
        console.error("Error loading route component:", pathname, err);
      } finally {
        routeStore.setIsLoading(false);
      }
    }
    static restoreScrollPosition(pathname: string): void {
      if (!routeStore.isInitialLoad && pathname !== "/") {
        const savedScroll = routeStateStore.getScrollPosition(pathname);
        if (savedScroll !== undefined) {
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              setTimeout(
                () => window.scrollTo({ top: savedScroll, behavior: "smooth" }),
                50
              );
            });
          });
        } else {
          // Scroll to top for new routes
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              setTimeout(
                () => window.scrollTo({ top: 0, behavior: "smooth" }),
                50
              );
            });
          });
        }
      }
    }
    static async handlePopstate(): Promise<void> {
      const newPath = window.location.pathname;
      routeStore.currentUrl.href = window.location.href;
      routeStore.setIsInitialLoad(false);
      routeStore.setIsLoading(true, componentNamePath);

      // Ensure we only attempt removal once on popstate as well.
      removeJobPostingJsonLd(undefined, "popstate");

      // Fetch RankMath head data
      await SEOService.fetchHeadData(newPath);
      // GTAG / GTM page view for back/forward SPA navigation after head update
      GoogleServices.sendPageView(newPath);
      // Trigger optional AdSense refresh for SPA navigation (back/forward).
      GoogleServices.adSenseRefresh();

      // Add loading timeout for popstate as well
      setTimeout(() => {
        if (routeStore.isLoading) {
          routeStore.setIsLoading(false);
        }
      }, 500);
    }
  }
</script>

<script lang="ts">
  import "@css/app.css";
  import { onMount, type SvelteComponent } from "svelte";
  import { fade } from "svelte/transition";
  import FloatingActionButton from "@components/ui/Shared/FloatingActionButton.svelte";
  import SkeletonHomepage from "@components/ui/Skeletons/SkeletonHomepage.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";
  import SkeletonPasangIklanLoker from "@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte";
  import Header from "@components/layouts/Header.svelte";

  let props = $props();

  const mapComponentName = (name: typeof componentNamePath) => {
    switch (name) {
      case "Homepage":
        return import("@routes/Homepage.svelte");
      case "PasangIklanLoker":
        return import("@routes/PasangIklanLoker.svelte");
      case "SingleLowongan":
        return import("@routes/SingleLowongan.svelte");
    }
  };

  $effect(() => {
    const importPromise = mapComponentName(componentNamePath);
    if (importPromise) {
      RouteHandler.loadRoute(importPromise, componentNamePath);
    } else {
      CurrentComponent = null;
    }
  });

  // Scroll restoration for non-homepage routes (homepage handles its own(job-grid))
  $effect(() => {
    RouteHandler.restoreScrollPosition(pathname);
  });

  onMount(() => {
    routeStore.setCurrentPath(window.location.pathname);
    GoogleServices.sendPageView(); // Send initial pageview on mount

    // Listen to browser back/forward
    if (typeof window !== "undefined") {
      window.addEventListener("popstate", RouteHandler.handlePopstate);

      // Return cleanup function
      return () => {
        window.removeEventListener("popstate", RouteHandler.handlePopstate);
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
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonHomepage />
      </div>
    {:else if isLoading && loadingComponent === "PasangIklanLoker"}
      <div
        class="page-transition fade-in"
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonPasangIklanLoker />
      </div>
    {:else if isLoading && loadingComponent === "SingleLowongan"}
      <div
        class="page-transition fade-in"
        in:fade={{ duration: 200 }}
        out:fade={{ duration: 150 }}
      >
        <SkeletonSingleLowongan />
      </div>
    {:else if CurrentComponent}
      <div
        class="page-transition fade-in"
        in:fade={{ duration: 250 }}
        out:fade={{ duration: 200 }}
      >
        <CurrentComponent {...routeStore.isInitialLoad ? props : {}} />
      </div>
    {/if}
  {/key}
  <FloatingActionButton />
</div>

<style lang="postcss">
  /* Page transition styles */
  .page-transition {
    transition: opacity 0.3s ease-in-out;
  }

  .page-transition.fade-in {
    opacity: 1;
  }

  /* Ensure smooth transitions for route changes */
  .route-container {
    min-height: 100vh;
    transition: all 0.2s ease-in-out;
  }
</style>
