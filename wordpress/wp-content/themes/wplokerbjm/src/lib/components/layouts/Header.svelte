<script module lang="ts">
  import { onMount, onDestroy, tick } from "svelte";
  import { debounce } from "es-toolkit";
  import { type ThemeName, type WPLokerBJMThemedData } from "@/types";
  import { MediaQuery } from "svelte/reactivity";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import { isAppEl } from "@/utils/elements";
  import { themeManager } from "$lib/stores/Theme.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import { browser } from "$app/environment";
  import { APIServiceBrowser } from "@/services/APIService";
  const isMobileValue = $derived.by(() => isMobile());

  let showBookmarkModal = $state(false);

  let showThemeModal = $state(false);
  let showLoginModal = $state(false);
  let loginStates = $state({
    username: "",
    password: "",
    error: "",
    loading: false,
  });

  class ThemeColorManager {
    public mediaQuery: MediaQuery | null = null;
    public debouncedSetTheme!: ReturnType<typeof debounce>;
    public isDark = $state(false);
    public currentTheme = $state<ThemeName>("light");
    #initialized = false;

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

    private persistTheme(theme: ThemeName): void {
      try {
        localStorage.setItem("wplokerbjm-theme", theme);
      } catch (e) {
        console.warn("Failed to write theme preference to localStorage", e);
      }
    }

    private queueThemeCleanup(theme: ThemeName): void {
      const runIdle =
        typeof window.requestIdleCallback === "function"
          ? window.requestIdleCallback.bind(window)
          : (callback: IdleRequestCallback) => window.setTimeout(callback, 0);

      requestAnimationFrame(() => {
        document.documentElement.classList.remove("theme-switching");
        runIdle(() => {
          this.persistTheme(theme);
          headerManager.scheduleUpdate();
        });
      });
    }

    private applyTheme(theme: ThemeName, dark: boolean): void {
      document.documentElement.setAttribute("data-theme", theme);
      if (dark) {
        document.documentElement.classList.add("wplokerbjm-dark-mode-enable");
      } else {
        document.documentElement.classList.remove(
          "wplokerbjm-dark-mode-enable",
        );
      }
      this.updateMetaThemeColor(dark);
    }

    public setThemeDirect(
      theme: ThemeName,
      options: { useViewTransition?: boolean } = {},
    ): void {
      const newTheme = theme;
      const isDark = newTheme === "dark";

      if (this.currentTheme === newTheme) return;

      this.currentTheme = newTheme;

      window.requestAnimationFrame(() => {
        const runThemeUpdate = () => {
          document.documentElement.classList.add("theme-switching");
          this.applyTheme(newTheme, isDark);
        };

        if (
          options.useViewTransition &&
          window.matchMedia("(prefers-reduced-motion: no-preference)").matches
        ) {
          const transition = document.startViewTransition?.(() => {
            runThemeUpdate();
          });

          if (transition) {
            void transition.finished
              ?.catch(() => {
                console.error("Theme view transition failed");
              })
              .finally(() => {
                this.queueThemeCleanup(newTheme);
              });
            return;
          }
        }

        runThemeUpdate();
        this.queueThemeCleanup(newTheme);
      });
    }

    public init(): void {
      if (this.#initialized) return;
      this.#initialized = true;
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

      switch (saved) {
        case "dark":
        case "lavender":
        case "light":
          // persisted preference
          this.isDark = saved === "dark";
          this.setThemeDirect(saved as ThemeName);
          break;
        default:
          if (systemPrefersDark) {
            this.isDark = true;
            this.setThemeDirect("dark");
          } else {
            this.isDark = false;
            this.setThemeDirect("light");
          }
      }
    }

    public setTheme(theme: ThemeName): void {
      // update boolean flag early so UI icon flips, but DON'T set currentTheme yet
      // otherwise setThemeDirect will erroneously early-return without updating DOM
      this.isDark = theme === "dark";

      // apply immediately for responsive UI, but keep debounced path for save/side-effects
      try {
        this.setThemeDirect(theme, { useViewTransition: true });
      } catch {
        // fallback to debounced if direct fails for some reason
        this.debouncedSetTheme(theme);
      }

      // ensure a debounced call remains to avoid double-write races
      this.debouncedSetTheme.flush();
    }
  }

  class HeaderManager {
    headerEl: HTMLElement | null = null;
    headerHeight = $state(0);

    appEl: HTMLElement | null = null;
    #lastOffsetUpdate = $state(0);
    rafId: number | null = null;
    mutationObserver: MutationObserver | null = null;
    headerResizeObserver: ResizeObserver | null = null;

    scheduleUpdate = () => {
      if (!browser) return;
      if (this.rafId !== null) return;
      this.rafId = requestAnimationFrame(() => {
        this.rafId = null;
        this.updateOffsets();
      });
    };

    updateOffsets = () => {
      // Throttle expensive offset calculations to avoid layout thrash.
      const now = Date.now();
      if (this.#lastOffsetUpdate && now - this.#lastOffsetUpdate < 100) return;
      this.#lastOffsetUpdate = now;

      this.headerEl = document.querySelector("header");
      try {
        this.updateHeaderHeight();
      } catch(e) {
        console.error("Error updating header height", e);
        this.headerHeight = 0;
      }
    };

    private updateHeaderHeight = () => {
      if (this.headerEl) {
        const height = this.headerEl.offsetHeight;

        // Also store globally so any sibling/content section can consume it.
        this.appEl?.style.setProperty("--site-header-height", height + "px");
        this.appEl?.style.setProperty(
          "--site-scroll-padding-top",
          height + "px",
        );
      }
    };

    attachMutationObserver() {
      if (!browser) return;
      const htmlEl = document.documentElement;
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

    start = () => {
      if (!browser) return; // nothing to do on server
      // resolve main app container for sibling-style updates
      this.appEl = document.querySelector(isAppEl) as HTMLElement | null;

      // run immediately once
      this.scheduleUpdate();

      this.attachMutationObserver();

      // Attach ResizeObserver for header height changes
      if (this.headerEl && !this.headerResizeObserver) {
        this.headerResizeObserver = new ResizeObserver(() => {
          this.updateHeaderHeight();
        });
        this.headerResizeObserver.observe(this.headerEl);
      }
    };

    destroy = () => {
      // cleanup listeners and observers
      if (this.rafId !== null) cancelAnimationFrame(this.rafId);

      if (this.mutationObserver) {
        this.mutationObserver.disconnect();
        this.mutationObserver = null;
      }

      if (this.headerResizeObserver) {
        this.headerResizeObserver.disconnect();
        this.headerResizeObserver = null;
      }

      this.headerEl?.style.removeProperty("--site-header-height");
      this.headerEl?.style.removeProperty("--site-scroll-padding-top");
    };
  }

  class LoginManager {
    static async Login(): Promise<void> {
      try {
        const active = document.activeElement as HTMLElement | null;
        if (active && active.matches(".drawer-toggle")) {
          active.blur();
        }
      } catch (e) {
        console.error("Element focus error during login", e);
      }

      loginStates.loading = true;
      loginStates.error = "";
      const token = await APIServiceBrowser.getJWTGraphQL({
        username: loginStates.username,
        password: loginStates.password,
      });

      try {
        loginStates.username = "";
        loginStates.password = "";

        if (!token) {
          loginStates.error = "Login gagal — periksa kredensial Anda.";
        } else {
          LoginManager.closeLogin();
        }
      } catch (e) {
        console.error("login error", e);
        loginStates.error = "Terjadi kesalahan saat login.";
        loginStates.password = "";
      } finally {
        const useRIF =
          typeof window.requestIdleCallback === "function"
            ? window.requestIdleCallback.bind(window)
            : (cb: IdleRequestCallback) => setTimeout(cb, 0);

        useRIF(async () => {
          try {
            if (!token) {
              loginStates.loading = false;
              return;
            }

            const nonce = await APIServiceBrowser.getThemeNonceGraphQL();
            if (nonce && nonce.length > 0) {
              themeManager.setNonce = nonce;
            }
          } catch (error) {
            console.error("Error refreshing nonce after login:", error);
          } finally {
            loginStates.loading = false;
            await tick();
          }
        });
      }
    }
    static Logout(): void {
      window.location.reload();
    }
    static closeLogin() {
      showLoginModal = false;
      loginStates.username = "";
      loginStates.password = "";
      loginStates.error = "";
      loginStates.loading = false;
    }
  }

  export const themeColorManager = new ThemeColorManager();
  export const headerManager = new HeaderManager();
</script>

<script lang="ts">
  import { goto } from "$app/navigation";
  import { innerWidth } from "svelte/reactivity/window";
  import {
    SunSolid,
    MoonSolid,
    BarsSolid,
    BookmarkSolid,
    ExternalLinkSolid,
    KeySolid,
  } from "svelte-awesome-icons";
  import { attachPortal } from "$lib/utils/elements.svelte";

  let { themeData }: { themeData: WPLokerBJMThemedData } = $props();

  let loginAdmin = $state(false);
  let _loginAdminPoll: number | null = null;
  const bookmarkJobs = $derived(bookmarkStore.jobs);

  // Open bookmark modal
  function openBookmarkModal(): void {
    showBookmarkModal = true;
  }

  function showButtonLogin(): void {
    const w = window as Window & { loginadmin?: boolean };
    const initial = !!w.loginadmin;
    loginAdmin = initial;
    const desc = Object.getOwnPropertyDescriptor(w, "loginadmin");
    if (!desc || desc.configurable) {
      let internal = initial;
      Object.defineProperty(w, "loginadmin", {
        configurable: true,
        enumerable: true,
        get() {
          return internal;
        },
        set(v: boolean) {
          internal = v;
          loginAdmin = !!v;
        },
      });
    } else {
      _loginAdminPoll = window.setInterval(() => {
        try {
          if ((w.loginadmin as boolean) !== loginAdmin)
            loginAdmin = !!w.loginadmin;
        } catch (e) {
          console.error("Error polling loginadmin property:", e);
        }
      }, 300);
    }
  }

  onMount(() => {
    themeManager.setThemeData = themeData;
    bookmarkStore.init();

    try {
      themeColorManager.init();
    } catch {
      console.error("Theme initialization error");
    }

    headerManager.start();
    showButtonLogin();
  });
  onDestroy(() => {
    headerManager?.destroy();
    if (_loginAdminPoll) {
      clearInterval(_loginAdminPoll);
      _loginAdminPoll = null;
    }
  });

  const hasStoredTheme = $derived.by(() => {
    if (!browser) return true;
    try {
      return !!localStorage.getItem("wplokerbjm-theme");
    } catch {
      return false;
    }
  });

  const prefersSystemDark = $derived.by(() => {
    if (!browser) return false;

    if (!themeColorManager.mediaQuery) {
      themeColorManager.mediaQuery = new MediaQuery(
        "(prefers-color-scheme: dark)",
      );
    }

    return themeColorManager.mediaQuery!.current;
  });

  const derivedTheme = $derived.by(() => {
    if (!browser) return themeColorManager.currentTheme;

    const stored = hasStoredTheme;
    const prefersDark = prefersSystemDark;

    if (!stored) {
      themeColorManager.isDark = prefersDark;
      themeColorManager.setThemeDirect(prefersDark ? "dark" : "light");
    }

    const theme = themeColorManager.currentTheme;
    themeColorManager.debouncedSetTheme(theme);
    return theme;
  });

  const bookmarkModalComponent = $derived.by(() => {
    if (!showBookmarkModal) return undefined;
    return dynamicComponentStore.loadBookmarkModal();
  });

  const loginModalComponent = $derived.by(() => {
    if (!showLoginModal) return undefined;
    return dynamicComponentStore.loadLoginModal();
  });

  const currentWindowWidth = $derived.by(() => {
    if (!browser) return 0;
    const width = innerWidth.current;
    headerManager.scheduleUpdate();
    return width;
  });
</script>

<header
  class="fixed top-0 left-0 w-full bg-[var(--wpl-global-color-4)] border-b-3 border-base-300 min-h-auto z-[60]"
  data-window-width={currentWindowWidth}
  style="view-transition-name: none;"
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
          <a
            href="/"
            class="focus:outline-none"
            onclick={(e) => {
              e.preventDefault();
              void goto("/");
            }}
          >
            {#if themeData.logo.logoUrl}
              <img
                src={themeData.logo.logoUrl}
                srcset={themeData.logo.logoSrcset}
                sizes={themeData.logo.logoSizes}
                decoding={themeData.logo.logoDecoding}
                alt="Site logo"
                width={themeData.logo.logoWidth}
                height={themeData.logo.logoHeight}
                fetchpriority="high"
                class="h-12 w-auto mb-2 md:h-16 md:w-auto"
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
              {#if themeColorManager.isDark}
                <MoonSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else if themeColorManager.currentTheme === "lavender"}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {/if}
            </button>
          </div>

          {#if showThemeModal}
            <div {@attach attachPortal("#app")}>
              <div class="modal modal-open z-[1100]">
                <div class="modal-box">
                  <h3 class="font-semibold text-lg">Pilih Tema</h3>
                  <p class="py-2 text-sm text-muted">
                    Pilih tema yang ingin Anda gunakan.
                  </p>
                  <div class="flex gap-3 mt-3">
                    <button
                      class="btn flex-1"
                      class:btn-primary={derivedTheme === "light"}
                      onclick={() => {
                        themeColorManager.setTheme("light");
                        showThemeModal = false;
                      }}
                    >
                      Light
                    </button>
                    <button
                      class="btn flex-1"
                      class:btn-primary={derivedTheme === "dark"}
                      onclick={() => {
                        themeColorManager.setTheme("dark");
                        showThemeModal = false;
                      }}
                    >
                      Dark
                    </button>
                    <button
                      class="btn flex-1"
                      class:btn-primary={derivedTheme === "lavender"}
                      onclick={() => {
                        themeColorManager.setTheme("lavender");
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

          <!-- Login button (desktop) -->
          {#if loginAdmin}
            <button
              class="btn rounded-full font-semibold bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] relative border-1 border-[var(--wpl-global-color-1)] hover:border-2 ml-2 hidden md:flex"
              onclick={() => (showLoginModal = true)}
              aria-label="Masuk"
              title="Masuk"
            >
              <KeySolid class="h-5 w-5" aria-hidden="true" focusable="false" />
              Masuk
            </button>
          {/if}
          <a
            class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)] hidden rounded-full md:inline-flex"
            href="/pasang-iklan-loker"
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
      <div id="header-drawer-side" class="drawer-side z-50">
        <label
          for="header-drawer"
          aria-label="Close navigation menu"
          class="drawer-overlay"
        ></label>
        <ul
          class="menu bg-base-200 text-base-content min-h-full w-auto max-w-[90vw] p-4 px-2 gap-4"
        >
          {#if loginAdmin}
            <li>
              <button
                class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-center"
                onclick={() => {
                  showLoginModal = true;
                  document.getElementById("header-drawer")?.click();
                }}
              >
                <KeySolid
                  class="h-5 w-5"
                  aria-hidden="true"
                  focusable="false"
                />
                Masuk
              </button>
            </li>
          {/if}
          <li>
            <a
              href="/pasang-iklan-loker"
              class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-center"
              onclick={() => {
                document.getElementById("header-drawer")?.click();
              }}
            >
              <ExternalLinkSolid
                class="h-6 w-6"
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
  {#if bookmarkModalComponent}
    {#await bookmarkModalComponent then BookmarkModal}
      <BookmarkModal bind:open={showBookmarkModal} />
    {/await}
  {/if}

  {#if loginModalComponent}
    {#await loginModalComponent then LoginModal}
      <LoginModal
        bind:open={showLoginModal}
        bind:username={loginStates.username}
        bind:password={loginStates.password}
        error={loginStates.error}
        loading={loginStates.loading}
        onClose={LoginManager.closeLogin}
        onLogin={LoginManager.Login}
      />
    {/await}
  {/if}
</header>
