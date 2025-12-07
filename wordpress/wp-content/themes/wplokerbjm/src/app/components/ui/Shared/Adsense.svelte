<script module lang="ts">
  import { isDevelopmentMode } from "@/utils";
  import { debounce } from "@/utils/debounce";
  import { tick } from "svelte";
  import { nonceStore } from "$lib/stores/Nonce.svelte";
  import { GoogleServices } from "$lib/utils/Google.svelte";

  interface AdsenseProps {
    clientId?: string;
    adSlot?: string;
    test?: boolean;
    className?: string;
    disable?: boolean;
  }

  export class AdsenseHandler {
    clientId: string;
    adSlot: string;
    test: boolean;
    className: string;
    disable = true;
    container = $state<HTMLDivElement | null>(null);
    adLoaded = $state(false);
    adFailed = $state(false);
    destroyed = $state(false);
    observer: MutationObserver | null = null;
    resizeObserver: ResizeObserver | null = null;
    timeoutId: number | null = null;
    _resizeRetryTimeout: number | null = null;
    _resizeAttempts = 0;
    removalPending = false;
    _debouncedRefresh: any = null;
    _lastRefreshTime: number = 0;

    constructor(props: AdsenseProps) {
      this.clientId = props.clientId ?? "ca-pub-3206452872913415";
      this.adSlot = props.adSlot ?? "";
      this.test = props.test ?? isDevelopmentMode();
      this.className = props.className ?? "";
      this.disable = props.disable ?? true;

      // If disable is true, mark destroyed so the UI (template) won't render
      if (this.disable) {
        this.destroyed = true;
        this.adFailed = true;
      }
    }

    pushAd() {
      if (this.disable) return;
      try {
        if (!this.container) return;
        // Ensure we don't accumulate multiple <ins> slots in the same container.
        // Remove any previous ins.adsbygoogle child before creating a new one.
        const existingIns = this.container.querySelector("ins.adsbygoogle");
        if (existingIns) {
          try {
            this.container.removeChild(existingIns);
          } catch (e) {
            // ignore
          }
        }
        const ins = document.createElement("ins");
        ins.className = "adsbygoogle";
        ins.style.display = "block";
        ins.setAttribute("data-ad-client", this.clientId);
        ins.setAttribute("data-ad-slot", this.adSlot);
        ins.setAttribute("data-ad-format", "auto");
        ins.setAttribute("data-full-width-responsive", "true");
        if (this.test) ins.setAttribute("data-adtest", "on");
        this.container.appendChild(ins);

        // Ensure global adsbygoogle array exists and try to request rendering for this slot.
        try {
          window.adsbygoogle = window.adsbygoogle || [];
          window.adsbygoogle.push({});
        } catch (e) {
          console.warn("adsbygoogle.push failed", e);
        }

        // Set up MutationObserver to detect ad status
        this.observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (
              mutation.type === "attributes" &&
              mutation.attributeName === "data-ad-status"
            ) {
              const status = ins.getAttribute("data-ad-status");
              if (status === "filled") {
                this.adLoaded = true;
                // cancel pending timeout if ad filled
                if (this.timeoutId) {
                  clearTimeout(this.timeoutId);
                  this.timeoutId = null;
                }
                this.observer?.disconnect();
              } else if (status === "unfilled") {
                // mark failed and wait for outro to finish before removing DOM
                this.adFailed = true;
                this.removalPending = true;
                this.observer?.disconnect();
              }
            }
          });
        });
        this.observer.observe(ins, {
          attributes: true,
          attributeFilter: ["data-ad-status"],
        });

        // Timeout to handle ads that don't get status — mark failed, wait for outro
        this.timeoutId = window.setTimeout(() => {
          if (!this.adLoaded && !this.adFailed) {
            this.adFailed = true;
            this.removalPending = true;
            this.observer?.disconnect();
          }
        }, 5000) as unknown as number;
      } catch (e) {
        console.warn("Failed to insert AdSense slot", e);
      }
    }

    /**
     * Ensure the container has a measurable width before pushing the ad.
     * This avoids adsbygoogle.push() errors like "No slot size for availableWidth=0".
     */
    ensureContainerHasSizeAndPush() {
      try {
        if (!this.container || this.disable) return;

        const rect = this.container.getBoundingClientRect();
        if (rect.width > 0) {
          // Container already has width — safe to push
          this.pushAd();
          return;
        }

        // If ResizeObserver is available, wait for a positive width
        if (typeof ResizeObserver !== "undefined") {
          // Clean up any previous observer
          this.resizeObserver?.disconnect();
          this.resizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) {
              const w =
                entry.contentRect?.width ??
                entry.target.getBoundingClientRect().width;
              if (w > 0) {
                this.resizeObserver?.disconnect();
                this.resizeObserver = null;
                this.pushAd();
                return;
              }
            }
          });
          this.resizeObserver.observe(this.container);
          // Also set a fallback timeout so we don't wait forever
          if (this._resizeRetryTimeout) {
            clearTimeout(this._resizeRetryTimeout);
            this._resizeRetryTimeout = null;
          }
          this._resizeRetryTimeout = window.setTimeout(() => {
            this.resizeObserver?.disconnect();
            this.resizeObserver = null;
            // If still no width, give up and mark as failed to avoid stuck UI
            const r = this.container
              ? this.container.getBoundingClientRect()
              : { width: 0 };
            if (r.width > 0) {
              this.pushAd();
            } else {
              console.warn(
                "Ad container never received a size — skipping ad render to avoid TagError"
              );
              this.adFailed = true;
              this.removalPending = true;
            }
          }, 5000) as unknown as number;
          return;
        }

        // Fallback polling (older browsers)
        this._resizeAttempts = 0;
        const attempt = () => {
          if (!this.container) return;
          this._resizeAttempts++;
          const r = this.container.getBoundingClientRect();
          if (r.width > 0) {
            this.pushAd();
            return;
          }
          if (this._resizeAttempts < 25) {
            this._resizeRetryTimeout = window.setTimeout(
              attempt,
              200
            ) as unknown as number;
          } else {
            console.warn(
              "Ad container never received a size (poll fallback) — skipping ad render"
            );
            this.adFailed = true;
            this.removalPending = true;
          }
        };
        attempt();
      } catch (e) {
        console.warn("ensureContainerHasSizeAndPush failed", e);
      }
    }

    /**
     * Immediate refresh implementation (non-debounced).
     * Separated so we can expose a debounced public `refresh()`.
     */
    async refreshImmediate() {
      try {
        if (this.disable) return;
        // Avoid refreshing while the page is not visible (background tabs)
        if (
          typeof document !== "undefined" &&
          document.visibilityState === "hidden"
        ) {
          // Do not refresh in background - helps avoid accidental impression inflation
          return;
        }

        // Enforce a minimum interval between refreshes (15s) as an extra guard
        const now = Date.now();
        const minInterval = 15000; // ms
        if (
          this._lastRefreshTime &&
          now - this._lastRefreshTime < minInterval
        ) {
          return;
        }
        this._lastRefreshTime = now;
        // Reset state so the UI can re-listen for the new ad status
        this.adLoaded = false;
        this.adFailed = false;
        this.removalPending = false;
        this.destroyed = false;

        // Clear any previous observer/timeout
        this.observer?.disconnect();
        if (this.timeoutId) {
          clearTimeout(this.timeoutId);
          this.timeoutId = null;
        }

        // Remove any existing slot and push a new one
        if (this.container) {
          this.container.innerHTML = "";
        }

        // If the AdSense script is present, push immediately. Otherwise init() will load script.
        try {
          await GoogleServices.injectAdSenseScript();
          // Slight delay to allow DOM updates from Svelte before inserting
          tick().then(() =>
            setTimeout(() => this.ensureContainerHasSizeAndPush(), 300)
          );
        } catch (e) {
          console.warn("Failed to inject AdSense script in refresh", e);
        }
      } catch (e) {
        console.warn("Failed to refresh AdSense slot", e);
      }
    }

    /**
     * Public debounced refresh. Prevents rapid consecutive refreshes which can
     * trigger AdSense limits. Uses the project's `debounce` util.
     */
    refresh() {
      if (this.disable) return;
      if (!this._debouncedRefresh) {
        // 10s debounce to be conservative with policy limits
        this._debouncedRefresh = debounce(
          () => this.refreshImmediate(),
          10000,
          { leading: false, trailing: true }
        );
      }
      this._debouncedRefresh();
    }

    /**
     * Called when the Svelte outro animation finishes on the wrapper.
     * If removal was pending, actually remove the ad DOM now.
     */
    outroEnd() {
      try {
        if (this.removalPending) {
          if (this.container && this.container.firstElementChild) {
            this.container.removeChild(this.container.firstElementChild);
          }
          this.removalPending = false;
        }
      } finally {
        if (this.timeoutId) {
          clearTimeout(this.timeoutId);
          this.timeoutId = null;
        }
        this.observer?.disconnect();
        this.resizeObserver?.disconnect();
        if (this._resizeRetryTimeout) {
          clearTimeout(this._resizeRetryTimeout);
          this._resizeRetryTimeout = null;
        }
      }
    }

    async init() {
      if (this.disable) return;
      if (typeof window === "undefined") return;
      if (!this.clientId || !this.adSlot) return;

      // Don't load ads if user is logged in(has a nonce)
      if (nonceStore.getNonce()) return;

      const loadAd = () =>
        tick().then(() =>
          setTimeout(() => this.ensureContainerHasSizeAndPush(), 300)
        );

      try {
        await GoogleServices.injectAdSenseScript();
        loadAd();
      } catch (e) {
        console.warn("Failed to inject AdSense script", e);
      }
    }

    destroy() {
      try {
        this.observer?.disconnect();
        this.resizeObserver?.disconnect();
        if (this._resizeRetryTimeout) {
          clearTimeout(this._resizeRetryTimeout);
          this._resizeRetryTimeout = null;
        }
        if (this.timeoutId) {
          clearTimeout(this.timeoutId);
          this.timeoutId = null;
        }

        // Remove any inserted ad node
        if (this.container) {
          // Remove ins.adsbygoogle if present to avoid duplicate requests later
          const existingIns = this.container.querySelector("ins.adsbygoogle");
          if (existingIns) {
            try {
              this.container.removeChild(existingIns);
            } catch (e) {
              // best-effort
            }
          }
          this.container.innerHTML = "";
        }

        // Reset internal state flags so the component can be re-used safely
        this.adLoaded = false;
        this.adFailed = false;
        this.removalPending = false;
        this.destroyed = true;
      } catch (e) {
        console.warn("Error during AdsenseHandler.destroy", e);
      }
    }
  }
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { slide } from "svelte/transition";

  const props: AdsenseProps = $props();
  const finalProps = { ...props, disable: props.disable ?? true };
  let handler = new AdsenseHandler(finalProps);
  let _refreshListener: (() => void) | null = null;
  let _destroyListener: (() => void) | null = null;

  onMount(async () => {
    await handler.init();
    // Register optional global refresh hook used by SPA routing logic.
    _refreshListener = () => handler.refresh();
    window.addEventListener("adsense:refresh", _refreshListener);

    // New destroy listener
    _destroyListener = () => handler.destroy();
    window.addEventListener("adsense:destroy", _destroyListener);
  });

  onDestroy(() => {
    handler.destroy();
    if (_refreshListener) {
      window.removeEventListener("adsense:refresh", _refreshListener);
    }
    if (_destroyListener) {
      window.removeEventListener("adsense:destroy", _destroyListener);
    }
  });
</script>

<!-- Don't show ad if user is logged in(has a nonce) -->
{#if !handler.adFailed && !handler.destroyed && !nonceStore.getNonce() && !handler.disable}
  <div
    class={`${handler.className} mb-2 mt-2 transition-opacity duration-500 ease-out ${handler.adLoaded ? "opacity-100" : "opacity-0"}`}
    aria-hidden="false"
    in:slide={{ duration: 1000 }}
    out:slide={{ duration: 1000 }}
    onoutroend={() => handler.outroEnd()}
  >
    <div bind:this={handler.container} aria-hidden="false"></div>
  </div>
{/if}