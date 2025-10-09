<script lang="ts">
  import "@css/app.css";
  import { onMount } from "svelte";
  import { fade } from "svelte/transition";
  import { routeStore } from "$lib/stores/route.svelte";
  import FloatingActionButton from "@components/ui/Shared/FloatingActionButton.svelte";
  import SkeletonHomepage from "@components/ui/Skeletons/SkeletonHomepage.svelte";
  import SkeletonPasangIklanLoker from "@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte";
  import Header from "@components/layouts/Header.svelte";
  import { SEOService } from "$lib/utils/SEO.svelte";

  let props = $props();
  let pathname = $derived(routeStore.currentPath);
  let isLoading = $derived(routeStore.isLoading);
  let loadingComponent = $derived(routeStore.loadingComponent);
  let CurrentComponent: any = $state(null);

  $effect(() => {
    if (pathname === "/") {
      import("@routes/Homepage.svelte")
        .then((m) => {
          CurrentComponent = m.default;
          routeStore.setIsLoading(false);
        })
        .catch(() => {
          routeStore.setIsLoading(false);
        });
    } else if (pathname.startsWith("/pasang-iklan-loker")) {
      import("@routes/PasangIklanLoker.svelte")
        .then((m) => {
          CurrentComponent = m.default;
          routeStore.setIsLoading(false);
        })
        .catch(() => {
          routeStore.setIsLoading(false);
        });
    } else if (pathname.startsWith("/lowongan/")) {
      import("@routes/SingleLowongan.svelte")
        .then((m) => {
          CurrentComponent = m.default;
          routeStore.setIsLoading(false);
        })
        .catch(() => {
          routeStore.setIsLoading(false);
        });
    } else {
      CurrentComponent = null;
      routeStore.setIsLoading(false);
    }
  });

  // Scroll restoration for non-homepage routes (homepage handles its own)
  $effect(() => {
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
