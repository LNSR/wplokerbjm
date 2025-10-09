<script module lang="ts">
  import { isDevelopmentMode } from "@/utils";
  import { tick } from "svelte";
  import { AuthService } from "@/services/AuthService";

  export class AdsenseHandler {
    clientId: string;
    adSlot: string;
    test: boolean;
    className: string;
    container = $state<HTMLDivElement | null>(null);
    adLoaded = $state(false);
    adFailed = $state(false);
    observer: MutationObserver | null = null;
    timeoutId: number | null = null;
    removalPending = false;

    constructor(props: {
      clientId?: string;
      adSlot?: string;
      test?: boolean;
      className?: string;
    }) {
      this.clientId = props.clientId ?? "ca-pub-3206452872913415";
      this.adSlot = props.adSlot ?? "";
      this.test = props.test ?? isDevelopmentMode();
      this.className = props.className ?? "";
    }

    pushAd() {
      try {
        if (!this.container) return;
        const ins = document.createElement("ins");
        ins.className = "adsbygoogle";
        ins.style.display = "block";
        ins.setAttribute("data-ad-client", this.clientId);
        ins.setAttribute("data-ad-slot", this.adSlot);
        ins.setAttribute("data-ad-format", "auto");
        ins.setAttribute("data-full-width-responsive", "true");
        if (this.test) ins.setAttribute("data-adtest", "on");
        this.container.appendChild(ins);

        window.adsbygoogle = window.adsbygoogle || [];
        try {
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
      }
    }

    init() {
      if (typeof window === "undefined") return;
      if (!this.clientId || !this.adSlot) return;

      // Don't load ads if user is logged in and has a nonce
      if (AuthService.getRestNonce() && AuthService.isLoggedIn()) return;

      const scriptSelector =
        'script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]';
      const existing = document.querySelector(
        scriptSelector
      ) as HTMLScriptElement | null;

      const loadAd = () =>
        tick().then(() => setTimeout(() => this.pushAd(), 300));

      if (!existing) {
        const s = document.createElement("script");
        s.async = true;
        s.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${this.clientId}`;
        s.crossOrigin = "anonymous";
        s.onload = loadAd;
        document.head.appendChild(s);
      } else {
        loadAd();
      }
    }

    destroy() {
      this.observer?.disconnect();
      if (this.timeoutId) {
        clearTimeout(this.timeoutId);
        this.timeoutId = null;
      }
      if (this.container) {
        this.container.innerHTML = "";
      }
    }
  }
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { slide } from "svelte/transition";

  const props = $props();
  let handler = new AdsenseHandler(props);

  onMount(() => {
    handler.init();
  });

  onDestroy(() => {
    handler.destroy();
  });
</script>

{#if !handler.adFailed && !(AuthService.isLoggedIn() && AuthService.getRestNonce())}
  <div
    class={`${handler.className} mb-2 mt-2 transition-opacity duration-500 ease-out ${handler.adLoaded ? "opacity-100" : "opacity-0"}`}
    aria-hidden="false"
    transition:slide={{ duration: 1000 }}
    onoutroend={() => handler.outroEnd()}
  >
    <div bind:this={handler.container} aria-hidden="false"></div>
  </div>
{/if}
