<script module lang="ts">
  import { debounce } from "@/utils/debounce";
  import { MediaQuery } from "svelte/reactivity";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import { navigateTo } from "$lib/stores/route.svelte";
  import { headerStore, HeaderUtils } from "$lib/stores/HeaderStore.svelte";
  import { ThemeName, type CardJob } from "@/types";

  class ThemeManager {
    private mediaQuery: MediaQuery | null = null;
    private debouncedSetTheme: (d: boolean) => void = () => {};
    public isDark = $state(false);
    public currentTheme = $state<ThemeName>(ThemeName.Light);

    private prefersReducedMotion(): boolean {
      try {
        return !!(
          window.matchMedia &&
          window.matchMedia("(prefers-reduced-motion: reduce)").matches
        );
      } catch {
        return false;
      }
    }

    private updateMetaThemeColor(dark: boolean): void {
      try {
        const root = document.documentElement;
        const cs = getComputedStyle(root);
        let color = (
          cs.getPropertyValue("--theme-color") ||
          cs.getPropertyValue("--wpl-global-color-4") ||
          ""
        ).trim();
        if (!color) color = dark ? "#0b1220" : "#ffffff";

        let meta = document.querySelector(
          'meta[name="theme-color"]'
        ) as HTMLMetaElement | null;
        if (!meta) {
          meta = document.createElement("meta");
          meta.name = "theme-color";
          document.head.appendChild(meta);
        }
        meta.setAttribute("content", color);
      } catch {
        console.error("Failed to update theme color meta tag");
      }
    }

    private setThemeDirect(dark: boolean): void {
      const newTheme = dark ? ThemeName.Dark : ThemeName.Light;
      if (this.currentTheme === newTheme) return;
      this.currentTheme = newTheme;

      window.requestAnimationFrame(() => {
        if (!this.prefersReducedMotion()) {
          document.documentElement.classList.add("theme-switching");
        }
        document.documentElement.setAttribute("data-theme", newTheme);
        if (dark) {
          document.documentElement.classList.add("wplokerbjm-dark-mode-enable");
        } else {
          document.documentElement.classList.remove(
            "wplokerbjm-dark-mode-enable"
          );
        }
        try {
          localStorage.setItem("wplokerbjm-theme", newTheme);
        } catch {
          console.error("Failed to save theme preference");
        }
        if (!this.prefersReducedMotion()) {
          setTimeout(() => {
            document.documentElement.classList.remove("theme-switching");
          }, 30);
        }
        this.updateMetaThemeColor(dark);
      });
    }

    private _initialized = false;

    public init(): void {
      if (this._initialized) return;
      this._initialized = true;
      this.debouncedSetTheme = debounce(
        (dark: boolean) => this.setThemeDirect(dark),
        10
      );

      let saved = "";
      try {
        saved = localStorage.getItem("wplokerbjm-theme") || "";
      } catch {
        saved = "";
      }

      const systemPrefersDark = ((): boolean => {
        try {
          return (
            window.matchMedia &&
            window.matchMedia("(prefers-color-scheme: dark)").matches
          );
        } catch {
          return false;
        }
      })();

      if (saved === ThemeName.Dark || (!saved && systemPrefersDark)) {
        this.isDark = true;
        this.setThemeDirect(true);
      } else {
        this.isDark = false;
        this.setThemeDirect(false);
      }

      try {
        this.mediaQuery = new MediaQuery("(prefers-color-scheme: dark)");
        $effect(() => {
          this.mediaQuery!.current;
          let hasStored = false;
          try {
            hasStored = !!localStorage.getItem("wplokerbjm-theme");
          } catch {
            hasStored = false;
          }
          if (!hasStored) {
            this.isDark = this.mediaQuery!.current;
            this.setThemeDirect(this.mediaQuery!.current);
          }
        });
      } catch {
        this.mediaQuery = null;
      }

      $effect(() => {
        this.isDark;
        this.debouncedSetTheme(this.isDark);
      });
    }

    public teardown(): void {
      // keep teardown minimal; do not attempt to remove $effects created
      // in the module reactive scope. Reset internal state so re-init is possible
      // in testing or HMR scenarios.
      this.mediaQuery = null;
      this._initialized = false;
    }

    public setTheme(dark: boolean): void {
      this.isDark = dark;
      this.debouncedSetTheme(dark);
    }
  }

  let BookmarkModalComponent:
    | typeof import("@components/ui/Header/BookmarkModal.svelte").default
    | null = $state(null);
  let isMobileValue = $state(false);
  let showBookmarkModal = $state(false);
  let bookmarkJobs = $state<CardJob[]>([]);

  class HeaderManager {
    headerEl: HTMLElement | null = null;
    rafId: number | null = null;
    mutationObserver: MutationObserver | null = null;
    adminBarObserver: MutationObserver | null = null;
    resizeObserver: ResizeObserver | null = null;
    domObserver: MutationObserver | null = null;

    // bound callback so we can add/remove listeners easily
    private boundScheduleUpdate = this.scheduleUpdate.bind(this);

    scheduleUpdate() {
      if (this.rafId !== null) return;
      this.rafId = requestAnimationFrame(() => {
        this.rafId = null;
        this.updateOffsets();
      });
    }

    updateOffsets() {
      this.headerEl = document.querySelector("header");
      try {
        HeaderUtils.setSiteHeaderVars({
          headerEl: this.headerEl,
        });

        // Update the signal-based store
        const adminBarHeight = HeaderUtils.getWpAdminBarHeight();
        const headerHeight = this.headerEl ? this.headerEl.offsetHeight : 0;
        headerStore.headerTop = adminBarHeight;
        headerStore.headerHeight = headerHeight;
      } catch (e) {
        // Fallback: ensure at least --site-header-top is set using admin bar height
        const adminBarHeight = HeaderUtils.getWpAdminBarHeight();
        document.documentElement.style.setProperty(
          "--site-header-top",
          adminBarHeight + "px"
        );
        headerStore.headerTop = adminBarHeight;
        headerStore.headerHeight = 0;
      }
    }

    attachAdminBarObservers() {
      // detach previous observers/listeners first
      if (this.adminBarObserver) {
        this.adminBarObserver.disconnect();
        this.adminBarObserver = null;
      }
      if (this.resizeObserver) {
        this.resizeObserver.disconnect();
        this.resizeObserver = null;
      }

      const adminBarEl = document.getElementById("wpadminbar");
      if (!adminBarEl) return;

      this.adminBarObserver = new MutationObserver(this.boundScheduleUpdate);
      this.adminBarObserver.observe(adminBarEl, {
        attributes: true,
        attributeFilter: ["style", "class"],
        subtree: false,
      });

      this.resizeObserver = new ResizeObserver(this.boundScheduleUpdate);
      this.resizeObserver.observe(adminBarEl);

      // listen for transition/animation end to catch transform-based hides
      adminBarEl.addEventListener("transitionend", this.boundScheduleUpdate, {
        passive: true,
      });
      adminBarEl.addEventListener("animationend", this.boundScheduleUpdate, {
        passive: true,
      });
    }

    attachMutationObserver() {
      const htmlEl = document.documentElement;
      this.mutationObserver = new MutationObserver(this.boundScheduleUpdate);
      this.mutationObserver.observe(htmlEl, {
        attributes: true,
        attributeFilter: ["class", "style"],
      });
    }

    attachDomObserver() {
      this.domObserver = new MutationObserver(() => {
        const found = document.getElementById("wpadminbar");
        if (
          (found && !this.adminBarObserver) ||
          (!found && this.adminBarObserver)
        ) {
          this.attachAdminBarObservers();
          this.boundScheduleUpdate();
        }
      });
      this.domObserver.observe(document.body || document.documentElement, {
        childList: true,
        subtree: true,
      });
    }

    start() {
      // rAF-debounced resize/scroll
      window.addEventListener("resize", this.boundScheduleUpdate, {
        passive: true,
      });
      window.addEventListener("scroll", this.boundScheduleUpdate, {
        passive: true,
      });

      // run immediately once
      this.scheduleUpdate();

      this.attachMutationObserver();
      this.attachDomObserver();
      this.attachAdminBarObservers();
    }

    destroy() {
      // cleanup listeners and observers
      window.removeEventListener("resize", this.boundScheduleUpdate);
      window.removeEventListener("scroll", this.boundScheduleUpdate);
      if (this.rafId !== null) cancelAnimationFrame(this.rafId);
      if (this.mutationObserver) {
        this.mutationObserver.disconnect();
        this.mutationObserver = null;
      }
      if (this.adminBarObserver) {
        this.adminBarObserver.disconnect();
        this.adminBarObserver = null;
      }
      if (this.resizeObserver) {
        this.resizeObserver.disconnect();
        this.resizeObserver = null;
      }
      if (this.domObserver) {
        this.domObserver.disconnect();
        this.domObserver = null;
      }
    }
  }

  export const themeStore = new ThemeManager();
  export const headerManager = new HeaderManager();
  async function loadBookmarkModal() {
    if (!BookmarkModalComponent) {
      const module = await import("@components/ui/Header/BookmarkModal.svelte");
      BookmarkModalComponent = module.default;
    }
  }
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";

  const isDev = import.meta.env.DEV;
  let { logo = "" } = $props();

  onMount(() => {
    try {
      themeStore.init();
    } catch (e) {
      console.error("Theme initialization error:", e);
    }

    // Resolve logo at runtime in the browser using window.wpTheme (set by PHP hook).
    try {
      const wpTheme = (window as any).wpTheme || {};
      const runtimeLogo: string | undefined = wpTheme.logo;
      const themeUrl: string | undefined = wpTheme.themeUrl;

      if (runtimeLogo) {
        logo = runtimeLogo;
      } else if (themeUrl) {
        logo =
          themeUrl +
          (isDev
            ? "/public/img/Logo-LOKER-Watermark.avif"
            : "/assets/dist/img/Logo-LOKER-Watermark.avif");
      }
    } catch (e) {
      console.error("Error resolving wpTheme logo:", e);
    }

    // Start header manager after DOM is available
    headerManager.start();
  });
  onDestroy(() => {
    headerManager?.destroy();
    document.documentElement.style.removeProperty("--site-header-top");
    document.documentElement.style.removeProperty("--site-header-height");
    document.documentElement.style.removeProperty("--site-scroll-padding-top");
  });

  $effect(() => {
    if (showBookmarkModal && !BookmarkModalComponent) {
      loadBookmarkModal();
    }
  });

  $effect(() => {
    const unsub = bookmarkStore.store.subscribe((v) => {
      bookmarkJobs = v.jobs;
    });
    return unsub;
  });

  $effect(() => {
    const unsub = isMobile.subscribe((v) => (isMobileValue = v));
    return unsub;
  });
</script>

<header
  class="fixed top-0 left-0 w-full bg-[var(--wpl-global-color-4)] border-b-2 border-[var(--wpl-global-color-5)] min-h-auto z-[60]"
  style="top:var(--site-header-top, 0)"
>
  <div class="drawer drawer-end">
    <input
      id="header-drawer"
      type="checkbox"
      class="drawer-toggle"
      aria-label="Toggle navigation menu"
      aria-controls="header-drawer-side"
    />
    <div class="drawer-content">
      <div
        class="mr-auto ml-auto pl-4 pr-4 max-w-screen-xl w-full flex items-center justify-between"
      >
        <div class="mt-3">
          <button onclick={() => navigateTo("/")} class="focus:outline-none">
            {#if logo}
              <img
                src={logo}
                alt="Site logo"
                class="h-12 w-auto mt-1 md:h-16 md:w-auto"
              />
            {/if}
          </button>
        </div>
        <div class="flex items-center gap-1 mt-5">
          <!-- Color/theme switcher -->
          <div class="backdrop-blur-lg rounded-full shadow-lg p-2">
            <label class="flex cursor-pointer gap-2 items-center">
              <span
                class="relative w-12 h-6 flex items-center"
                role="switch"
                aria-checked={themeStore.isDark}
              >
                <span
                  class="absolute inset-0 rounded-full bg-gray-200 dark:bg-slate-700 transition-colors"
                ></span>
                <span
                  class="absolute top-0 left-0 w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow transition-transform"
                  style:transform={themeStore.isDark
                    ? "translateX(100%)"
                    : "translateX(0)"}
                ></span>
                <!-- Sun icon -->
                <svg
                  class="absolute left-1 top-1 w-4 h-4 transition-all z-10"
                  class:opacity-40={themeStore.isDark}
                  class:grayscale={themeStore.isDark}
                  class:opacity-100={!themeStore.isDark}
                  style="color: var(--icon-color);"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <g
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    stroke-width="2"
                    fill="none"
                    stroke="currentColor"
                  >
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2"></path>
                    <path d="M12 20v2"></path>
                    <path d="m4.93 4.93 1.41 1.41"></path>
                    <path d="m17.66 17.66 1.41 1.41"></path>
                    <path d="M2 12h2"></path>
                    <path d="M20 12h2"></path>
                    <path d="m6.34 17.66-1.41 1.41"></path>
                    <path d="m19.07 4.93-1.41 1.41"></path>
                  </g>
                </svg>
                <!-- Moon icon -->
                <svg
                  class="absolute right-1 top-1 w-4 h-4 transition-all z-10"
                  class:opacity-40={!themeStore.isDark}
                  class:grayscale={!themeStore.isDark}
                  class:opacity-100={themeStore.isDark}
                  style="color: var(--icon-color);"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <g
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    stroke-width="2"
                    fill="none"
                    stroke="currentColor"
                  >
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                  </g>
                </svg>
                <input
                  type="checkbox"
                  value="dark"
                  class="toggle theme-controller focus:ring-2 focus:ring-blue-400 absolute w-12 h-6 opacity-0 cursor-pointer"
                  aria-label="Toggle color theme"
                  aria-checked={themeStore.isDark}
                  bind:checked={themeStore.isDark}
                />
              </span>
            </label>
          </div>

          <!-- Bookmark button on desktop -->
          <button
            onclick={() => (showBookmarkModal = true)}
            class="btn btn-circle md:flex relative border-[var(--wpl-global-color-1)] border-1 hover:border-2"
            aria-label="Lowongan tersimpan"
            title="Lowongan tersimpan"
          >
            <svg
              class="h-5 w-5 text-[var(--wpl-global-color-1)]"
              viewBox="0 0 24 24"
              fill="currentColor"
              aria-hidden="true"
              focusable="false"
            >
              <path d="M6 2a2 2 0 00-2 2v17l8-4 8 4V4a2 2 0 00-2-2H6z" />
            </svg>
            {#if bookmarkJobs.length > 0}
              <span
                class="absolute -top-2 -right-1 bg-[var(--wpl-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10"
              >
                {bookmarkJobs.length}
              </span>
            {/if}
          </button>
          <button
            class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)] hidden rounded-full md:inline-flex"
            onclick={() => navigateTo("/pasang-iklan-loker")}
          >
            <svg
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <path d="M7 17L17 7" />
              <path d="M7 7h10v10" />
            </svg>
            Pasang Iklan Loker
          </button>
          <!-- Drawer toggle button, shown only on mobile -->
          {#if isMobileValue}
            <label
              for="header-drawer"
              class="btn btn-ghost btn-sm md:btn-md"
              aria-label="Open navigation menu"
            >
              <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
              >
                <path d="M3 12h18" />
                <path d="M3 6h18" />
                <path d="M3 18h18" />
              </svg>
            </label>
          {/if}
        </div>
      </div>
    </div>
    <!-- Mobile drawer side. margin-top reads the same CSS var we set in JS (--site-header-top) -->
    {#if isMobileValue}
      <div
        id="header-drawer-side"
        class="drawer-side z-50"
        style="margin-top:var(--site-header-top, 0)"
      >
        <label
          for="header-drawer"
          aria-label="Close navigation menu"
          class="drawer-overlay"
        ></label>
        <ul
          class="menu bg-base-200 text-base-content min-h-full w-auto max-w-[90vw] p-4 px-2 gap-4"
        >
          <li>
            <button
              onclick={() => navigateTo("/pasang-iklan-loker")}
              class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-start"
            >
              <svg
                class="h-6 w-6 mr-2"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
              >
                <path d="M7 17L17 7" />
                <path d="M7 7h10v10" />
              </svg>
              Pasang Iklan Loker
            </button>
          </li>
        </ul>
      </div>
    {/if}
  </div>
  {#if showBookmarkModal}
    {#if BookmarkModalComponent}
      <BookmarkModalComponent bind:open={showBookmarkModal} />
    {:else}{/if}
  {/if}
</header>
