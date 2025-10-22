<script module lang="ts">
  import { routeStore } from "$lib/stores/route.svelte";
  import { SEOService } from "$lib/utils/SEO.svelte";

  let pathname = $derived(routeStore.currentPath);
  let isLoading = $derived(routeStore.isLoading);
  let loadingComponent = $derived(routeStore.loadingComponent);
  let CurrentComponent: typeof SvelteComponent | null = $state(null);

  class RouteHandler {
    static async loadRoute(
      importPromise: Promise<any>,
      componentName: string
    ): Promise<void> {
      const loadForPath = pathname; // capture current path to guard against race
      try {
        routeStore.setIsLoading(true, componentName);
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
        const savedScroll = routeStore.getScrollPosition(pathname);
        if (savedScroll !== undefined) {
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              setTimeout(() => window.scrollTo(0, savedScroll), 50);
            });
          });
        } else {
          // Scroll to top for new routes
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              setTimeout(() => window.scrollTo(0, 0), 50);
            });
          });
        }
      }
    }
  }
</script>

<script lang="ts">
  import "@css/app.css";
  import { onMount, type SvelteComponent } from "svelte";
  import { fade } from "svelte/transition";
  import FloatingActionButton from "@components/ui/Shared/FloatingActionButton.svelte";
  import SkeletonHomepage from "@components/ui/Skeletons/SkeletonHomepage.svelte";
  import SkeletonPasangIklanLoker from "@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte";
  import Header from "@components/layouts/Header.svelte";

  let props = $props();

  $effect(() => {
    if (pathname === "/") {
      RouteHandler.loadRoute(import("@routes/Homepage.svelte"), "Homepage");
    }
    if (pathname.startsWith("/pasang-iklan-loker")) {
      RouteHandler.loadRoute(
        import("@routes/PasangIklanLoker.svelte"),
        "PasangIklanLoker"
      );
    }
    if (pathname.startsWith("/lowongan/")) {
      RouteHandler.loadRoute(
        import("@routes/SingleLowongan.svelte"),
        "SingleLowongan"
      );
    }
  });

  // Scroll restoration for non-homepage routes (homepage handles its own)
  $effect(() => {
    RouteHandler.restoreScrollPosition(pathname);
  });

  onMount(() => {
    routeStore.setCurrentPath(window.location.pathname);
    SEOService.fetchHeadData(window.location.pathname);
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
    {:else if CurrentComponent}
      <div
        class="page-transition fade-in"
        in:fade={{ duration: 300 }}
        out:fade={{ duration: 150 }}
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
