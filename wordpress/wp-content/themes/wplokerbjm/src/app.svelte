<script module lang="ts">
  import { onMount, type Component } from "svelte";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import { GoogleServices } from "@/services/Google";

  const pathname = $derived(routeStore.currentUrl.pathname);
  const isInitialLoad = $derived(routeStore.isInitialLoad);
  const componentNamePath = $derived(routeStore.getComponentNamePath(pathname));
  const CurrentComponent = $derived(routeStore.CurrentComponent);
  const isTransitioningRoute = $derived(routeStore.isTransitioningRoute);

  class AppRouteHandler {
    public mapComponentName(
      name: typeof componentNamePath,
    ): Promise<Component<any>> | null {
      switch (name) {
        case "Homepage":
          return dynamicComponentStore.loadHomepage();
        case "PasangIklanLoker":
          return dynamicComponentStore.loadPasangIklanLoker();
        case "SingleLowongan":
          return dynamicComponentStore.loadSingleLowongan();
      }
      return null;
    }

    private setComponentWithTransition(component: any, path: string): void {
      if (routeStore.currentViewTransition) {
        routeStore.currentViewTransition.finished.then(() => {
          this.setComponentWithTransition(component, path);
        });
        return;
      }

      if (
        typeof document !== "undefined" &&
        document.startViewTransition &&
        !document.viewTransition &&
        !routeStore.lockViewTransition &&
        !isInitialLoad // Skip view transition for initial load to avoid browser incompatibilities issue
      ) {
        routeStore.lockViewTransition = true;

        try {
          const trans = document.startViewTransition!(() => {
            routeStore.CurrentComponent = component;
            routeStore.isTransitioningRoute = false;
            routeStateStore.restoreScrollForPath(path);
          });
          routeStore.currentViewTransition = trans;
          if (trans && trans.finished) {
            trans.finished
              .then(() => {
                routeStore.currentViewTransition = null;
                routeStore.lockViewTransition = false;
              })
              .catch(() => {
                routeStore.currentViewTransition = null;
                routeStore.lockViewTransition = false;
              });
          }
        } catch {
          routeStore.currentViewTransition = null;
          routeStore.lockViewTransition = false;
          routeStore.CurrentComponent = component;
          routeStore.isTransitioningRoute = false;
          routeStateStore.restoreScrollForPath(path);
        }
      } else {
        // Fallback without view transition
        routeStore.currentViewTransition = null;
        routeStore.lockViewTransition = false;
        routeStore.CurrentComponent = component;
        routeStore.isTransitioningRoute = false;
        routeStateStore.restoreScrollForPath(path);
      }
    }

    public loadRoute(
      importPromise: Promise<Component<any>>,
      componentNamePath: string,
    ): void {
      const loadForPath = pathname;
      try {
        routeStore.loadStart(componentNamePath);

        importPromise
          .then((m) => {
            if (loadForPath !== pathname) return; // prevent race condition
            this.setComponentWithTransition(m, pathname);
          })
          .catch((err) => {
            routeStore.CurrentComponent = null;
            routeStore.isTransitioningRoute = false;
            console.error("Error loading route component:", pathname, err);
          })
          .finally(() => {
            routeStore.loadEnd();
          });
      } catch (err) {
        routeStore.CurrentComponent = null;
        routeStore.isTransitioningRoute = false;
        console.error("Error in loadRoute:", err);
        routeStore.loadEnd();
      }
    }

    handlePopstate = (): void => {
      routeStore.handlePopstateEvent;
    };
  }

  const appRouteHandler = new AppRouteHandler();
</script>

<script lang="ts">
  import "@css/app.css";
  import FloatingActionButton from "@components/ui/Shared/FloatingActionButton.svelte";
  import Header from "@components/layouts/Header.svelte";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";

  const props = $props();

  $effect(() => {
    const importPromise = appRouteHandler.mapComponentName(componentNamePath);
    if (importPromise) {
      appRouteHandler.loadRoute(importPromise, componentNamePath);
    } else {
      routeStore.isTransitioningRoute = false;
    }
  });

  onMount(() => {
    routeStore.setCurrentPath(pathname);
    GoogleServices.injectGTMScript()
      .then(() => {
        GoogleServices.sendPageView(); // Send initial pageview after GTM loads
      })
      .catch(() => {
        console.error("Failed to inject GTM script on initial load");
      });

    // Listen to browser back/forward
    if (typeof window !== "undefined") {
      window.addEventListener("popstate", appRouteHandler.handlePopstate);

      // Return cleanup function
      return () => {
        window.removeEventListener("popstate", appRouteHandler.handlePopstate);
      };
    }
  });
</script>

<div class="route-container">
  <Header />
  {#if CurrentComponent && !isTransitioningRoute}
    <div
      class="page-transition"
      style="padding-top:{headerStore.totalOffset}px"
    >
      <CurrentComponent {...isInitialLoad ? props : {}} />
    </div>
  {/if}
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
    opacity: 1;
  }

  /* Ensure smooth transitions for route changes */
  .route-container {
    min-height: 100vh;
    transition: all 0.2s ease-in-out;
    position: relative;
    /* Prefer Document Transition API: provide a stable view name so the UA can capture snapshots */
    view-transition-name: route;
    /* tuneable CSS vars for platforms that respect view transition tuning */
    --view-transition-duration: 150ms;
    --view-transition-timing-function: ease-in-out;
  }

  /* View Transition API fade simulation (preferred when supported) */
  /* Use single-colon form to be compatible with some CSS toolchains (e.g., lightningcss) while remaining functional in user agents */
  .route-container::view-transition-group {
    /* ensure the group uses our configured duration */
    animation-duration: var(--view-transition-duration, 150ms);
    animation-timing-function: var(
      --view-transition-timing-function,
      ease-in-out
    );
  }

  /* Opt-out attribute so JS can temporarily disable view-transitions during overlays/history writes */
  :global(.route-container[data-no-view-transition="true"]) {
    view-transition-name: none !important;
  }

  /* old/new pseudo-elements animate opacity to simulate Svelte's fade */
  .route-container::view-transition-old {
    animation-name: vt-fade-out;
    animation-duration: var(--view-transition-duration, 150ms);
    animation-timing-function: var(
      --view-transition-timing-function,
      ease-in-out
    );
    animation-fill-mode: both;
  }

  .route-container::view-transition-new {
    animation-name: vt-fade-in;
    animation-duration: var(--view-transition-duration, 150ms);
    animation-timing-function: var(
      --view-transition-timing-function,
      ease-in-out
    );
    animation-fill-mode: both;
  }

  @keyframes vt-fade-in {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  @keyframes vt-fade-out {
    from {
      opacity: 1;
    }
    to {
      opacity: 0;
    }
  }
</style>
