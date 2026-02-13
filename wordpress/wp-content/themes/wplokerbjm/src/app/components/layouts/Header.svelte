<script module lang="ts">
  import { debounce, getThemeData } from "@/utils";
  import { ThemeName } from "@/types";
  import { MediaQuery, SvelteMap } from "svelte/reactivity";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import { NonceManager } from "@/utils/Nonce";
  const isMobileValue = $derived.by(() => isMobile());
  const isHasNonce = $derived(NonceManager.getNonce !== null);
  let showBookmarkModal = $state(false);
  const bookmarkJobs = $derived(bookmarkStore.jobs);

  let logoSrcset = $state("");
  let logoSizes = $state("");
  let logoWidth = $state<number | undefined>(undefined);
  let logoHeight = $state<number | undefined>(undefined);
  let logoDecoding = $state<HTMLImgAttributes["decoding"]>(undefined);

  class ThemeManager {
    private mediaQuery: MediaQuery | null = null;
    private debouncedSetTheme: (d: ThemeName) => void = () => {};
    public isDark = $state(false);
    public currentTheme = $state<ThemeName>(ThemeName.Light);
    private _initialized = false;

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
          'meta[name="theme-color"]',
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

    private setThemeDirect(theme: ThemeName): void {
      // guard against concurrent invocations during view transitions
      if (routeStore.currentViewTransition) {
        routeStore.currentViewTransition.finished.then(() => {
          this.setThemeDirect(theme);
        });
        return;
      }

      const newTheme = theme;
      const isDark = newTheme === ThemeName.Dark;

      if (this.currentTheme === newTheme) return;

      this.currentTheme = newTheme;

      window.requestAnimationFrame(() => {
        const applyTheme = () => {
          document.documentElement.classList.add("theme-switching");
          document.documentElement.setAttribute("data-theme", newTheme);
          if (isDark) {
            document.documentElement.classList.add(
              "wplokerbjm-dark-mode-enable",
            );
          } else {
            document.documentElement.classList.remove(
              "wplokerbjm-dark-mode-enable",
            );
          }
          this.updateMetaThemeColor(isDark);
        };

        if (
          typeof document !== "undefined" &&
          !routeStore.isInitialLoad &&
          document.startViewTransition &&
          !document.viewTransition &&
          !routeStore.lockViewTransition
        ) {
          const trans = document.startViewTransition!(applyTheme);
          routeStore.lockViewTransition = true;
          trans;
          if (trans && trans.finished) {
            trans.finished
              .then(() => {
                routeStore.lockViewTransition = false;
              })
              .catch(() => {
                console.error("Theme view transition failed");
                routeStore.lockViewTransition = false;
              });
          }
        } else {
          applyTheme();
        }

        try {
          // Defer storage write off the critical paint path so it cannot
          // block rendering or cause forced reflow during theme toggles.
          const write = () => {
            try {
              localStorage.setItem("wplokerbjm-theme", newTheme);
            } catch (e) {
              console.warn(
                "Failed to write theme preference to localStorage",
                e,
              );
            }
          };
          if (typeof (window as any).requestIdleCallback === "function") {
            (window as any).requestIdleCallback(write);
          } else {
            setTimeout(write, 0);
          }
        } catch {
          console.error("Failed to schedule theme preference save");
        }
        setTimeout(() => {
          document.documentElement.classList.remove("theme-switching");
          // Give the browser a short moment to paint the theme change, then
          // trigger a single header measurement. This avoids forcing layout
          // during the theme toggle which causes long presentation delays.
          try {
            setTimeout(() => {
              requestAnimationFrame(() => {
                if (
                  typeof headerManager !== "undefined" &&
                  headerManager &&
                  headerManager.scheduleUpdate
                ) {
                  headerManager.scheduleUpdate();
                }
              });
            }, 50);
          } catch (e) {
            console.warn("Failed to schedule header measurement", e);
          }
        }, 30);
      });
    }

    public init(): void {
      if (this._initialized) return;
      this._initialized = true;
      this.debouncedSetTheme = debounce(
        (theme: ThemeName) => this.setThemeDirect(theme),
        10,
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

      if (
        saved === ThemeName.Dark ||
        saved === ThemeName.Lavender ||
        saved === ThemeName.Light
      ) {
        // persisted preference
        this.isDark = saved === ThemeName.Dark;
        this.setThemeDirect(saved as ThemeName);
      } else if (!saved && systemPrefersDark) {
        this.isDark = true;
        this.setThemeDirect(ThemeName.Dark);
      } else {
        this.isDark = false;
        this.setThemeDirect(ThemeName.Light);
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
            this.setThemeDirect(
              this.mediaQuery!.current ? ThemeName.Dark : ThemeName.Light,
            );
          }
        });
      } catch {
        this.mediaQuery = null;
      }

      $effect(() => {
        this.currentTheme;
        this.debouncedSetTheme(this.currentTheme);
      });
    }

    public setTheme(theme: ThemeName): void {
      // update boolean flag early so UI icon flips, but DON'T set currentTheme yet
      // otherwise setThemeDirect will erroneously early-return without updating DOM
      this.isDark = theme === ThemeName.Dark;

      // apply immediately for responsive UI, but keep debounced path for save/side-effects
      try {
        this.setThemeDirect(theme);
      } catch {
        // fallback to debounced if direct fails for some reason
        this.debouncedSetTheme(theme);
      }

      // ensure a debounced call remains to avoid double-write races
      try {
        if ((this.debouncedSetTheme as any)?.flush) {
          (this.debouncedSetTheme as any).flush();
        }
      } catch {
        // ignore
      }
    }
  }

  class HeaderManager {
    headerEl: HTMLElement | null = null;
    private _lastOffsetUpdate = $state(0);
    rafId: number | null = null;
    mutationObserver: MutationObserver | null = null;
    adminBarObserver: MutationObserver | null = null;
    resizeObserver: ResizeObserver | null = null;
    headerResizeObserver: ResizeObserver | null = null;
    domObserver: MutationObserver | null = null;
    adminBarEl: HTMLElement | null = null;
    adminBarCache: object = $state({});
    adminBarCacheMap = $state(new SvelteMap<string, any>());

    scheduleUpdate = () => {
      if (this.rafId !== null) return;
      this.rafId = requestAnimationFrame(() => {
        this.rafId = null;
        this.updateOffsets();
      });
    };

    updateOffsets = () => {
      // Throttle expensive offset calculations to avoid layout thrash.
      const now = Date.now();
      if (this._lastOffsetUpdate && now - this._lastOffsetUpdate < 100) return;
      this._lastOffsetUpdate = now;

      // Avoid updating during view transitions to prevent race conditions
      if (routeStore.currentViewTransition) {
        routeStore.currentViewTransition.finished.then(() =>
          this.updateOffsets(),
        );
        return;
      }

      this.headerEl = document.querySelector("header");
      try {
        // Update the signal-based store for top offset
        const adminBarHeight = headerStore.getWpAdminBarHeight();
        headerStore.headerTop = adminBarHeight;
        headerStore.appEl?.style.setProperty(
          "--site-header-top",
          adminBarHeight + "px",
        );
        // Update height initially
        this.updateHeaderHeight();
      } catch {
        // Fallback: ensure at least --site-header-top is set using admin bar height
        const adminBarHeight = headerStore.getWpAdminBarHeight();
        headerStore.appEl?.style.setProperty(
          "--site-header-top",
          adminBarHeight + "px",
        );
        headerStore.headerTop = adminBarHeight;
        headerStore.headerHeight = 0;
      }
    };

    private updateHeaderHeight = () => {
      if (this.headerEl) {
        const height = this.headerEl.offsetHeight;
        headerStore.headerHeight = height;
        headerStore.appEl?.style.setProperty(
          "--site-header-height",
          height + "px",
        );
        const scrollPadding = headerStore.headerTop + height;
        headerStore.appEl?.style.setProperty(
          "--site-scroll-padding-top",
          scrollPadding + "px",
        );
      }
    };

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

      // Remove transition/animation listeners from a previously tracked element
      if (this.adminBarEl) {
        try {
          this.adminBarEl.removeEventListener(
            "transitionend",
            this.scheduleUpdate,
          );
          this.adminBarEl.removeEventListener(
            "animationend",
            this.scheduleUpdate,
          );
        } catch {
          // ignore
        }
        this.adminBarEl = null;
      }

      this.adminBarEl = document.getElementById("wpadminbar");
      if (!this.adminBarEl) return;

      this.adminBarObserver = new MutationObserver(this.scheduleUpdate);
      this.adminBarObserver.observe(this.adminBarEl!, {
        attributes: true,
        attributeFilter: ["style", "class"],
        subtree: false,
      });

      this.resizeObserver = new ResizeObserver(this.scheduleUpdate);
      this.resizeObserver.observe(this.adminBarEl!);

      // listen for transition/animation end to catch transform-based hides
      this.adminBarEl!.addEventListener("transitionend", this.scheduleUpdate, {
        passive: true,
      });
      this.adminBarEl!.addEventListener("animationend", this.scheduleUpdate, {
        passive: true,
      });
    }

    moveAdminBarIntoHeader() {
      try {
        let adminBar = document.getElementById("wpadminbar");
        const headerEl = document.querySelector("header");
        const prevBodyMarginTop =
          typeof document !== "undefined"
            ? document.body.style.marginTop || ""
            : "";

        // Use SvelteMap-backed cache under key 'wpadminbar'
        const existing = this.adminBarCacheMap.get("wpadminbar") || {};
        const cache = existing;
        cache.prevBodyMarginTop = prevBodyMarginTop;
        cache.prevParent = adminBar?.parentElement || cache.prevParent || null;
        cache.nextSibling = adminBar?.nextSibling || cache.nextSibling || null;
        if (adminBar) {
          try {
            cache.savedHTML = adminBar.outerHTML;
          } catch (e) {
            console.warn("Failed to read wpadminbar.outerHTML", e);
          }
        }
        this.adminBarCacheMap.set("wpadminbar", cache);
        this.adminBarCache = cache;

        // If adminBar is missing (e.g., due to HMR), try to rehydrate from cached HTML in 'cache'
        if (!adminBar && cache?.savedHTML) {
          try {
            const container = document.createElement("div");
            container.innerHTML = cache.savedHTML;
            const candidate = container.querySelector(
              "#wpadminbar",
            ) as HTMLElement | null;
            if (candidate) {
              // Prefer previous parent if it's still in the document, otherwise fall back to body
              const potentialParent =
                cache.prevParent && document.contains(cache.prevParent)
                  ? cache.prevParent
                  : document.body;
              const parent = potentialParent as Node;
              if (
                cache.nextSibling &&
                cache.nextSibling.parentNode === parent
              ) {
                parent.insertBefore(candidate, cache.nextSibling);
              } else {
                parent.appendChild(candidate);
              }
              adminBar = candidate;
              try {
                cache.savedHTML = adminBar.outerHTML;
                this.adminBarCacheMap.set("wpadminbar", cache);
                this.adminBarCache = cache;
              } catch (e) {
                console.warn(
                  "Failed to update adminBar cache after rehydration",
                  e,
                );
              }
            }
          } catch (e) {
            // ignore rehydrate failures
            console.error("Failed to rehydrate wpadminbar from cache", e);
          }
        }

        if (adminBar && headerEl && !headerEl.contains(adminBar)) {
          headerEl.prepend(adminBar);
          adminBar.classList.add("in-header");
          try {
            cache.savedHTML = adminBar.outerHTML;
            this.adminBarCacheMap.set("wpadminbar", cache);
            this.adminBarCache = cache;
          } catch (e) {
            console.warn(
              "Failed to update adminBar cache after moving into header",
              e,
            );
          }
          this.adminBarEl = adminBar;
        }
      } catch (e) {
        console.error("Failed to move wpadminbar into header", e);
      }
    }

    restoreAdminBar() {
      try {
        const cache =
          this.adminBarCacheMap.get("wpadminbar") || this.adminBarCache || {};
        let adminBar = document.getElementById("wpadminbar");

        // If adminBar is missing but we have cached HTML, re-insert it into prevParent
        if (!adminBar && cache && cache.savedHTML) {
          try {
            const container = document.createElement("div");
            container.innerHTML = cache.savedHTML;
            const candidate = container.querySelector(
              "#wpadminbar",
            ) as HTMLElement | null;
            if (candidate) {
              const potentialParent =
                cache.prevParent && document.contains(cache.prevParent)
                  ? cache.prevParent
                  : document.body;
              const parent = potentialParent as Node;
              if (
                cache.nextSibling &&
                cache.nextSibling.parentNode === parent
              ) {
                parent.insertBefore(candidate, cache.nextSibling);
              } else {
                parent.appendChild(candidate);
              }
              adminBar = candidate;
              try {
                cache.savedHTML = adminBar.outerHTML;
                this.adminBarCacheMap.set("wpadminbar", cache);
                this.adminBarCache = cache;
              } catch (e) {
                console.warn(
                  "Failed to update adminBar cache after reinsertion",
                  e,
                );
              }
            }
          } catch (e) {
            console.error("Failed to reinsert cached wpadminbar", e);
          }
        }

        if (cache && adminBar && cache.prevParent) {
          if (cache.nextSibling)
            cache.prevParent.insertBefore(adminBar, cache.nextSibling);
          else cache.prevParent.appendChild(adminBar);
          adminBar.classList.remove("in-header");
          adminBar.style.position = "";
          adminBar.style.transform = "";
          adminBar.style.top = "";
          try {
            document.body.style.marginTop = cache.prevBodyMarginTop || "";
          } catch {
            // ignore
          }
        }
      } catch (e) {
        console.error("Failed to restore wpadminbar to original location", e);
      }
    }

    attachMutationObserver() {
      const htmlEl = document.documentElement;
      // Ignore mutations triggered by theme switching marker to avoid
      // forced synchronous layout reads during theme toggles.
      this.mutationObserver = new MutationObserver(() => {
        if (document.documentElement.classList.contains("theme-switching"))
          return;
        this.scheduleUpdate();
      });
      this.mutationObserver.observe(htmlEl, {
        attributes: true,
        attributeFilter: ["class", "style"],
      });
    }

    attachDomObserver() {
      this.domObserver = new MutationObserver(() => {
        // Skip reacting while theme switching is in progress
        if (document.documentElement.classList.contains("theme-switching"))
          return;
        const found = document.getElementById("wpadminbar");
        if (
          (found && !this.adminBarObserver) ||
          (!found && this.adminBarObserver)
        ) {
          this.attachAdminBarObservers();
          this.scheduleUpdate();
        }
      });
      this.domObserver.observe(document.body || document.documentElement, {
        childList: true,
        subtree: true,
      });
    }

    start = () => {
      // run immediately once
      this.scheduleUpdate();

      // Move admin bar into header early so offsets are simpler
      if (isHasNonce) {
        try {
          this.moveAdminBarIntoHeader();
        } catch (e) {
          console.warn("moveAdminBarIntoHeader failed", e);
        }
      }

      this.attachMutationObserver();
      this.attachDomObserver();
      this.attachAdminBarObservers();

      // Attach ResizeObserver for header height changes
      if (this.headerEl && !this.headerResizeObserver) {
        this.headerResizeObserver = new ResizeObserver(() => {
          this.updateHeaderHeight();
        });
        this.headerResizeObserver.observe(this.headerEl);
      }
    };

    destroy = () => {
      // attempt to restore admin bar to its original location
      try {
        this.restoreAdminBar();
      } catch (e) {
        console.warn("restoreAdminBar failed", e);
      }

      // cleanup listeners and observers
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

      if (this.headerResizeObserver) {
        this.headerResizeObserver.disconnect();
        this.headerResizeObserver = null;
      }

      // remove any remaining event listeners from the admin bar element
      if (this.adminBarEl) {
        try {
          this.adminBarEl.removeEventListener(
            "transitionend",
            this.scheduleUpdate,
          );
          this.adminBarEl.removeEventListener(
            "animationend",
            this.scheduleUpdate,
          );
        } catch {
          // ignore
        }
        this.adminBarEl = null;
      }

      if (this.domObserver) {
        this.domObserver.disconnect();
        this.domObserver = null;
      }
      headerStore.appEl?.style.removeProperty("--site-header-top");
      headerStore.appEl?.style.removeProperty("--site-header-height");
      headerStore.appEl?.style.removeProperty("--site-scroll-padding-top");
    };
  }

  export const themeStore = new ThemeManager();
  export const headerManager = new HeaderManager();
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import type { HTMLImgAttributes } from "svelte/elements";
  import { GlobalNavigateTo, routeStore } from "$lib/stores/Route.svelte";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
  import { innerWidth } from "svelte/reactivity/window";
  import {
    SunSolid,
    MoonSolid,
    BarsSolid,
    BookmarkSolid,
    ExternalLinkSolid,
  } from "svelte-awesome-icons";
  import { PortalManager } from "$lib/utils/elements.svelte";

  let { HeaderLogo = "" } = $props();
  let showThemeModal = $state(false);

  function updateLogo(): void {
    try {
      const themeData = getThemeData();
      const runtimeLogo = themeData?.logo?.logoUrl;
      const runtimeLogoSrcset = themeData?.logo?.logoSrcset;
      const runtimeLogoSizes = themeData?.logo?.logoSizes;
      const runtimeLogoDecoding = themeData?.logo?.logoDecoding;
      const runtimeLogoWidth = themeData?.logo?.logoWidth;
      const runtimeLogoHeight = themeData?.logo?.logoHeight;

      const parseDimension = (value: unknown): number | undefined => {
        if (typeof value === "number" && value > 0) return value;
        if (typeof value === "string" && value.trim()) {
          const parsed = Number(value.trim());
          if (!Number.isNaN(parsed) && parsed > 0) return parsed;
        }
        return undefined;
      };

      if (runtimeLogo) {
        HeaderLogo = runtimeLogo as string;
        logoSrcset = runtimeLogoSrcset || "";
        logoSizes = runtimeLogoSizes || "";
        logoDecoding = runtimeLogoDecoding;
        logoWidth = parseDimension(runtimeLogoWidth);
        logoHeight = parseDimension(runtimeLogoHeight);
      }
    } catch {
      console.error("Error updating logo");
    }
  }

  // Open bookmark modal
  function openBookmarkModal(): void {
    showBookmarkModal = true;
  }

  onMount(() => {
    try {
      themeStore.init();
      updateLogo();
    } catch {
      console.error("Theme initialization error");
    }

    headerManager.start();
  });
  onDestroy(() => {
    // headerManager.destroy will attempt to restore wpadminbar; failures are logged (non-fatal)
    headerManager?.destroy();
  });

  $effect(() => {
    if (showBookmarkModal && !dynamicComponentStore.BookmarkModal) {
      dynamicComponentStore.loadBookmarkModal();
    }
  });

  $effect(() => {
    innerWidth.current;
    headerManager.scheduleUpdate();
  });
</script>

<header
  class="fixed top-0 left-0 w-full bg-[var(--wpl-global-color-4)] border-b-3 border-[var(--wpl-global-color-5)] min-h-auto z-[60]"
  style="top:0; transform: translateY(var(--site-header-top, 0)); view-transition-name: site-header;"
>
  <div class="drawer drawer-end">
    <input
      id="header-drawer"
      type="checkbox"
      class="drawer-toggle"
      aria-hidden="true"
      tabindex="-1"
    />
    <div class="drawer-content">
      <div
        class="mr-auto ml-auto pl-4 pr-4 max-w-screen-xl w-full flex items-center justify-between"
      >
        <div class="mt-3">
          <a href="/"
            onclick={(e) => {
              e.preventDefault();
              GlobalNavigateTo("/");
            }}
            class="focus:outline-none"
          >
            {#if HeaderLogo}
              <img
                src={HeaderLogo}
                srcset={logoSrcset}
                sizes={logoSizes}
                decoding={logoDecoding}
                alt="Site logo"
                width={logoWidth}
                height={logoHeight}
                fetchpriority="high"
                class="h-12 w-auto mt-1 md:h-16 md:w-auto"
              />
            {/if}
          </a>
        </div>
        <div class="flex items-center gap-1 mt-5">
          <!-- Color/theme switcher -->
          <div class="p-1">
            <button
              class="flex items-center gap-2 p-2 rounded focus:outline-none"
              aria-label="Choose color theme"
              title="Pilih tema warna"
              onclick={() => (showThemeModal = true)}
            >
              {#if themeStore.isDark}
                <MoonSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else if themeStore.currentTheme === ThemeName.Lavender}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {/if}
            </button>
          </div>

          {#if showThemeModal}
            <div use:PortalManager.teleport={".route-container"}>
              <div class="modal modal-open z-[1000]">
                <div class="modal-box">
                  <h3 class="font-semibold text-lg">Pilih Tema</h3>
                  <p class="py-2 text-sm text-muted">
                    Pilih tema yang ingin Anda gunakan.
                  </p>
                  <div class="flex gap-3 mt-3">
                    <button
                      class="btn flex-1"
                      class:btn-primary={themeStore.currentTheme ===
                        ThemeName.Light}
                      onclick={() => {
                        themeStore.setTheme(ThemeName.Light);
                        showThemeModal = false;
                      }}
                    >
                      Light
                    </button>
                    <button
                      class="btn flex-1"
                      class:btn-primary={themeStore.currentTheme ===
                        ThemeName.Dark}
                      onclick={() => {
                        themeStore.setTheme(ThemeName.Dark);
                        showThemeModal = false;
                      }}
                    >
                      Dark
                    </button>
                    <button
                      class="btn flex-1"
                      class:btn-primary={themeStore.currentTheme ===
                        ThemeName.Lavender}
                      onclick={() => {
                        themeStore.setTheme(ThemeName.Lavender);
                        showThemeModal = false;
                      }}
                    >
                      Lavender
                    </button>
                  </div>
                  <div class="modal-action">
                    <button class="btn" onclick={() => (showThemeModal = false)}
                      >Close</button
                    >
                  </div>
                </div>
              </div>
            </div>
          {/if}

          <!-- Bookmark button on desktop -->
          <button
            onclick={openBookmarkModal}
            class="btn btn-circle md:flex relative border-[var(--wpl-global-color-1)] border-1 hover:border-2"
            aria-label="Lowongan tersimpan"
            title="Lowongan tersimpan"
          >
            <BookmarkSolid
              class="h-5 w-5 text-[var(--wpl-global-color-1)]"
              aria-hidden="true"
              focusable="false"
            />
            {#if bookmarkJobs.length > 0}
              <span
                class="absolute -top-2 -right-1 bg-[var(--wpl-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10"
              >
                {bookmarkJobs.length}
              </span>
            {/if}
          </button>
          <a
            class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)] hidden rounded-full md:inline-flex"
            href="/pasang-iklan-loker/"
            onclick={(e) => {
              e.preventDefault();
              GlobalNavigateTo("/pasang-iklan-loker/");
            }}
          >
            <ExternalLinkSolid
              class="h-6 w-6"
              aria-hidden="true"
              focusable="false"
            />
            Pasang Iklan Loker
          </a>
          <!-- Drawer toggle button, shown only on mobile -->
          {#if isMobileValue}
            <label
              for="header-drawer"
              class="btn btn-ghost btn-sm md:btn-md"
              aria-label="Open navigation menu"
            >
              <BarsSolid class="h-5 w-5" aria-hidden="true" focusable="false" />
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
            <a
              href="/pasang-iklan-loker/"
              onclick={(e) => {
                e.preventDefault();
                GlobalNavigateTo("/pasang-iklan-loker/");
              }}
              class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-start"
            >
              <ExternalLinkSolid
                class="h-6 w-6 mr-2"
                aria-hidden="true"
                focusable="false"
              />
              Pasang Iklan Loker
            </a>
          </li>
        </ul>
      </div>
    {/if}
  </div>
  {#if showBookmarkModal && dynamicComponentStore.BookmarkModal}
    {#await dynamicComponentStore.BookmarkModal then BookmarkModal}
      <BookmarkModal bind:open={showBookmarkModal} />
    {/await}
  {/if}
</header>

<style>
  :global(#wpadminbar.in-header) {
    position: static !important;
    transform: none !important;
    top: auto !important;
    left: auto !important;
    z-index: inherit !important;
  }
</style>
