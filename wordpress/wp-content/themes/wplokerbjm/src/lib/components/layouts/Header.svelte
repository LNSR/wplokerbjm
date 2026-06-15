<script module lang="ts">
  import { onMount, tick } from "svelte";
  import { type ThemeScriptData, type WPLokerBJMThemedData } from "@/types";
  import {
    applyThemeViewTransition,
    localStorageThemeActions,
  } from "@/utils/theme";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { themePropsStore } from "$lib/stores/Theme.svelte";
  import {
    APIServiceBrowser,
    APIServiceShared,
  } from "@/services/graphql/APIService";
  import { useRIC } from "@/utils/window";
  import { MediaQuery } from "svelte/reactivity";

  let showThemeModal = $state.raw(false);
  let showLoginModal = $state.raw(false);

  class ThemeColorManager {
    public currentTheme = $state.raw<ThemeScriptData["themeList"]>("light");
    public metaColor = $derived.by(() => {
      switch (this.currentTheme) {
        case "light":
          return "#f2f7ff";
        case "dark":
          return "#212a37";
        case "lavender":
          return "#f6f5ff";
      }
    });
    #mediaQuery: MediaQuery = new MediaQuery("(prefers-color-scheme: dark)");
    public systemPrefersDark = $derived(this.#mediaQuery?.current ?? false);

    /**
     * Initializes the theme based on saved preference or system setting. This should be called once on onMount.
     */
    public init(): void {
      const attributeName: ThemeScriptData["elements"]["attribute"] =
        "data-theme";

      if (document.documentElement.hasAttribute(attributeName)) {
        const currentAttr =
          document.documentElement.getAttribute(attributeName);
        this.currentTheme = currentAttr as ThemeScriptData["themeList"];
        return; // Inline script already set the theme
      }

      const savedTheme = localStorageThemeActions({ get: true });
      const theme = this.systemPrefersDark ? "dark" : (savedTheme ?? "light");

      void this.setTheme(theme);
    }

    public setTheme(theme: ThemeScriptData["themeList"]): void {
      void applyThemeViewTransition(theme);
    }
  }

  class HeaderManager {
    headerHeight = $state.raw<number | undefined>(undefined);
    currentHeight = $derived(this.headerHeight);
  }

  class LoginManager {
    #loginStates = $state({
      username: "",
      password: "",
      error: "",
      loading: false,
    });

    public loginStates = $derived(this.#loginStates);

    public async Login(): Promise<void> {
      try {
        const active = document.activeElement as HTMLElement | null;
        if (active && active.matches(".drawer-toggle")) {
          active.blur();
        }
      } catch (e) {
        console.error("Element focus error during login", e);
      }

      this.#loginStates.loading = true;
      this.#loginStates.error = "";
      const token = await APIServiceBrowser.getJWTGraphQL({
        username: this.#loginStates.username,
        password: this.#loginStates.password,
      });

      try {
        this.#loginStates.username = "";
        this.#loginStates.password = "";

        if (!token) {
          this.#loginStates.error = "Login gagal — periksa kredensial Anda.";
        } else {
          this.closeLogin();
        }
      } catch (e) {
        console.error("login error", e);
        this.#loginStates.error = "Terjadi kesalahan saat login.";
        this.#loginStates.password = "";
      } finally {
        useRIC(
          async () => {
            try {
              if (!token) {
                this.#loginStates.loading = false;
                return;
              }

              const nonce = await APIServiceBrowser.getThemeNonceGraphQL();
              if (nonce && nonce.length > 0) {
                themePropsStore.setNonce = nonce;
                APIServiceShared.setNonce(nonce);
              }
            } catch (error) {
              console.error("Error fetching theme nonce:", error);
            } finally {
              this.#loginStates.loading = false;
              await tick();
            }
          },
          { fallbackDelay: 0 },
        );
      }
    }
    public Logout(): void {
      window.location.reload();
    }
    public closeLogin() {
      showLoginModal = false;
      this.#loginStates.username = "";
      this.#loginStates.password = "";
      this.#loginStates.error = "";
      this.#loginStates.loading = false;
    }
  }

  const themeColorManager = new ThemeColorManager();
  const loginManager = new LoginManager();
  export const headerManager = new HeaderManager();
</script>

<script lang="ts">
  import {
    SunSolid,
    MoonSolid,
    BarsSolid,
    BookmarkSolid,
    ExternalLinkSolid,
    KeySolid,
  } from "svelte-awesome-icons";
  import { componentRegistry } from "@/lib/stores/ComponentRegistry.svelte";

  let { themeData }: { themeData: WPLokerBJMThemedData } = $props();

  let showBookmarkModal = $state.raw(false);
  let showLoginAdminModal = $state.raw(false);

  const isMobile = $derived(deviceDetector.isPlatformMobile);
  const bookmarkJobCount = $derived(bookmarkStore.jobs.length);

  /**
   * Sets up a global handler on the window object to control the visibility of the login admin modal.
   */
  function loginAdminModalHandler() {
    interface ShowUI extends Window {
      __wplokerbjm?: {
        showUI: {
          loginAdmin: boolean;
        };
      };
    }
    if (typeof window === "undefined") return;
    const w = window as ShowUI;
    Object.defineProperty(w, "__wplokerbjm" as keyof ShowUI, {
      value: {
        showUI: {
          get loginAdmin() {
            return showLoginAdminModal;
          },
          set loginAdmin(value: boolean) {
            showLoginAdminModal = value;
          },
        },
      },
      writable: true,
      configurable: true,
    });

    return () => {
      if (typeof window === "undefined") return;
      delete (w as any).__wplokerbjm.showUI.loginAdmin;
    };
  }

  (() => {
    themePropsStore.setThemeData = themeData;
    APIServiceShared.setNonce(themeData.wpRestNonce);
  })();

  onMount(() => {
    themeColorManager.init();
    const cleanupLoginAdminModalHandler = loginAdminModalHandler();
    return () => {
      cleanupLoginAdminModalHandler?.();
    };
  });
</script>

<svelte:head>
  <meta
    name="system-theme"
    content={themeColorManager.systemPrefersDark ? "dark" : "light"}
  />
  {#if themeColorManager.metaColor}
    <meta name="theme-color" content={themeColorManager.metaColor} />
  {/if}
</svelte:head>

<header
  bind:offsetHeight={headerManager.headerHeight}
  class="fixed top-0 left-0 w-full bg-[var(--wpl-global-color-4)] border-b-3 border-base-300 min-h-auto z-[60]"
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
          <a href="/" class="focus:outline-none">
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
              popovertarget="theme-switcher"
            >
              {#if themeColorManager.currentTheme === "dark"}
                <MoonSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {/if}
            </button>
          </div>

          {#if showThemeModal}
            <div
              id="theme-switcher"
              class="modal pointer-events-none"
              class:modal-open={showThemeModal}
              popover="auto"
              hidden={!showThemeModal}
              ontoggle={(e) =>
                (showThemeModal = e.newState === "open" ? true : false)}
            >
              <div class="modal-box pointer-events-auto">
                <h3 class="font-semibold text-lg">Pilih Tema</h3>
                <p class="py-2 text-sm text-muted">
                  Pilih tema yang ingin Anda gunakan.
                </p>
                <div class="flex gap-3 mt-3">
                  {#snippet themeButton(
                    choosenTheme: ThemeScriptData["themeList"],
                  )}
                    <button
                      class="btn flex-1 capitalize"
                      aria-label={`Set theme to ${choosenTheme}`}
                      class:btn-primary={themeColorManager.currentTheme ===
                        choosenTheme}
                      onclick={() => {
                        themeColorManager.setTheme(choosenTheme);
                        showThemeModal = false;
                      }}
                    >
                      {choosenTheme}
                    </button>
                  {/snippet}

                  {#each ["light", "dark", "lavender"] as choosenTheme (choosenTheme)}
                    {@render themeButton(
                      choosenTheme as ThemeScriptData["themeList"],
                    )}
                  {/each}
                </div>
                <div class="modal-action">
                  <button class="btn" onclick={() => (showThemeModal = false)}
                    >Close</button
                  >
                </div>
              </div>
            </div>
          {/if}

          <button
            onclick={() => {
              componentRegistry.loadComponentByName("BookmarkModal");
              showBookmarkModal = true;
            }}
            class="btn btn-circle md:flex relative border-[var(--wpl-global-color-1)] border-1 hover:border-2"
            aria-label="Lowongan tersimpan"
            title="Lowongan tersimpan"
          >
            <BookmarkSolid
              class="h-5 w-5 text-[var(--wpl-global-color-1)]"
              aria-hidden="true"
              focusable="false"
            />
            {#if bookmarkJobCount > 0}
              <span
                class="absolute -top-2 -right-1 bg-[var(--wpl-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10"
              >
                {bookmarkJobCount}
              </span>
            {/if}
          </button>

          <!-- Login button (desktop) -->
          {#if showLoginAdminModal}
            <button
              class="btn rounded-full font-semibold bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] relative border-1 border-[var(--wpl-global-color-1)] hover:border-2 ml-2 hidden md:flex"
              onclick={() => {
                componentRegistry.loadComponentByName("LoginModal");
                showLoginModal = true;
              }}
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
          {#if isMobile}
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
    {#if isMobile}
      <div id="header-drawer-side" class="drawer-side z-50">
        <label
          for="header-drawer"
          aria-label="Close navigation menu"
          class="drawer-overlay"
        ></label>
        <ul
          class="menu bg-base-200 text-base-content min-h-full w-auto max-w-[90vw] p-4 px-2 gap-4"
        >
          {#if showLoginAdminModal}
            <li>
              <button
                class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-center"
                onclick={() => {
                  componentRegistry.loadComponentByName("LoginModal");
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
  {#if showBookmarkModal}
    {const BookmarkModal = $derived(
      componentRegistry.getComponentByName("BookmarkModal"))}
    <BookmarkModal bind:open={showBookmarkModal} />
  {/if}

  {#if showLoginModal}
    {const LoginModal = $derived(
      componentRegistry.getComponentByName("LoginModal"))}
    <LoginModal
      bind:open={showLoginModal}
      bind:username={loginManager.loginStates.username}
      bind:password={loginManager.loginStates.password}
      error={loginManager.loginStates.error}
      loading={loginManager.loginStates.loading}
      onClose={() => loginManager.closeLogin()}
      onLogin={() => loginManager.Login()}
    />
  {/if}
</header>

<style lang="postcss">
  #theme-switcher:not(:popover-open) {
    display: none !important;
  }
</style>
