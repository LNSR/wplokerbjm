<script module lang="ts">
  import { onMount, tick } from "svelte";
  import { type ThemeName, type WPLokerBJMThemedData } from "@/types";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { themePropsStore } from "$lib/stores/Theme.svelte";
  import { APIServiceBrowser } from "@/services/graphql/APIService";
  import { useRIC } from "$lib/utils/window.svelte";
  import { MediaQuery } from "svelte/reactivity";

  let showThemeModal = $state(false);
  let showLoginModal = $state(false);
  let loginStates = $state({
    username: "",
    password: "",
    error: "",
    loading: false,
  });

  class ThemeColorManager {
    readonly #key = "wplokerbjm-theme";
    public currentTheme = $state<ThemeName>("light");
    public metaColor: string | undefined = $state(undefined);
    #mediaQuery: MediaQuery = new MediaQuery("(prefers-color-scheme: dark)");
    #initialized = false;

    get systemPrefersDark(): boolean {
      $inspect("System Prefers Dark", this.#mediaQuery?.current);
      return this.#mediaQuery?.current ?? false;
    }

    /**
     * Initializes the theme based on saved preference or system setting. This should be called once on onMount.
     */
    public init(): void {
      if (this.#initialized) return;
      this.#initialized = true;

      let savedTheme = "";
      try {
        savedTheme = localStorage.getItem(this.#key) || "";
      } catch {
        savedTheme = "";
      }

      const theme: ThemeName =
        savedTheme === "light" ||
        savedTheme === "dark" ||
        savedTheme === "lavender"
          ? savedTheme
          : this.systemPrefersDark
            ? "dark"
            : "light";

      this.setThemeHelper(theme);
    }

    public setTheme(theme: ThemeName): void {
      try {
        this.setThemeHelper(theme, { useViewTransition: true });
      } catch {
        this.setThemeHelper(theme);
      }
    }

    private updateMetaThemeColor(): void {
      try {
        const theme: ThemeName = this.currentTheme;
        // according to --wpl-global-color-4 in app.css
        switch (theme) {
          case "light":
            this.metaColor = "#f2f7ff";
            break;
          case "dark":
            this.metaColor = "#212a37";
            break;
          case "lavender":
            this.metaColor = "#f6f5ff";
            break;
          default:
            this.metaColor = undefined;
        }
      } catch {
        console.error("Failed to update theme color meta tag");
      }
    }

    private persistTheme(theme: ThemeName): void {
      useRIC(
        () => {
          try {
            localStorage.setItem(this.#key, theme);
          } catch (e) {
            console.warn("Failed to write theme preference to localStorage", e);
          }
        },
        { fallbackDelay: 500 },
      );
    }

    private applyThemeAttribute(theme: ThemeName, dark: boolean): void {
      document.documentElement.setAttribute("data-theme", theme);
      if (dark) {
        document.documentElement.classList.add("wplokerbjm-dark-mode-enable");
      } else {
        document.documentElement.classList.remove(
          "wplokerbjm-dark-mode-enable",
        );
      }
      this.updateMetaThemeColor();
    }

    private setThemeHelper(
      theme: ThemeName,
      options: { useViewTransition?: boolean } = {},
    ): void {
      const isDark = theme === "dark";
      if (this.currentTheme === theme) return;

      this.currentTheme = theme;
      window.requestAnimationFrame(() => {
        const runThemeUpdate = () => {
          this.applyThemeAttribute(theme, isDark);
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
                this.persistTheme(theme);
              });
            return;
          }
        }

        runThemeUpdate();
        this.persistTheme(theme);
      });
    }
  }

  class HeaderManager {
    headerHeight = $state<number | undefined>(undefined);
    get currentHeight(): number {
      $inspect("Header Height", this.headerHeight);
      return this.headerHeight ?? 0;
    }
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
        useRIC(
          async () => {
            try {
              if (!token) {
                loginStates.loading = false;
                return;
              }

              const nonce = await APIServiceBrowser.getThemeNonceGraphQL();
              if (nonce && nonce.length > 0) {
                themePropsStore.setNonce = nonce;
              }
            } catch (error) {
              console.error("Error fetching theme nonce:", error);
            } finally {
              loginStates.loading = false;
              await tick();
            }
          },
          { fallbackDelay: 0 },
        );
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
  import {
    SunSolid,
    MoonSolid,
    BarsSolid,
    BookmarkSolid,
    ExternalLinkSolid,
    KeySolid,
  } from "svelte-awesome-icons";
  import { teleportTo } from "$lib/utils/elements.svelte";
  import { isMobile } from "$lib/utils/window.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import type { Attachment } from "svelte/attachments";

  let { themeData }: { themeData: WPLokerBJMThemedData } = $props();

  let showBookmarkModal = $state(false);
  let showloginAdminModal = $state(false);

  const isMobileValue = $derived(isMobile());
  const bookmarkJobs = $derived(bookmarkStore.jobs);

  export const ButtonUIHandler: Attachment<Window> = (() => {
    const handler: ProxyHandler<any> = {
      set(target: any, prop: string, value: any) {
        if (prop === "loginAdmin") {
          showloginAdminModal = !!value;
        }
        return Reflect.set(target, prop, value);
      },
      get(target: any, prop: string) {
        if (prop === "loginAdmin") return showloginAdminModal;
        return Reflect.get(target, prop);
      },
    };

    return (w: Window) => {
      w = window;
      (w as any).showUI = new Proxy(
        { loginAdmin: showloginAdminModal },
        handler,
      );
      return () => {
        delete (w as any).showUI;
      };
    };
  })();

  onMount(() => {
    themePropsStore.setThemeData = themeData;
    bookmarkStore.init();
    themeColorManager.init();
  });
</script>

<svelte:window {@attach ButtonUIHandler} />

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
            >
              {#if themeColorManager.currentTheme === "dark"}
                <MoonSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {:else}
                <SunSolid class="w-5 h-5" style="color: var(--icon-color);" />
              {/if}
            </button>
          </div>

          {#if showThemeModal}
            <div {@attach teleportTo("#app")}>
              <div class="modal modal-open z-[1100]">
                <div class="modal-box">
                  <h3 class="font-semibold text-lg">Pilih Tema</h3>
                  <p class="py-2 text-sm text-muted">
                    Pilih tema yang ingin Anda gunakan.
                  </p>
                  <div class="flex gap-3 mt-3">
                    {#snippet themeButton(choosenTheme: ThemeName)}
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
                      {@render themeButton(choosenTheme as ThemeName)}
                    {/each}
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
            onclick={() => {
              dynamicComponentStore.loadComponentByName("BookmarkModal");
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
            {#if bookmarkJobs.length > 0}
              <span
                class="absolute -top-2 -right-1 bg-[var(--wpl-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10"
              >
                {bookmarkJobs.length}
              </span>
            {/if}
          </button>

          <!-- Login button (desktop) -->
          {#if showloginAdminModal}
            <button
              class="btn rounded-full font-semibold bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] relative border-1 border-[var(--wpl-global-color-1)] hover:border-2 ml-2 hidden md:flex"
              onclick={() => {
                dynamicComponentStore.loadComponentByName("LoginModal");
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
          {#if showloginAdminModal}
            <li>
              <button
                class="btn font-semibold border-1 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] justify-center"
                onclick={() => {
                  dynamicComponentStore.loadComponentByName("LoginModal");
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
    {@const BookmarkModal =
      dynamicComponentStore.getComponentByName("BookmarkModal")}
    <BookmarkModal bind:open={showBookmarkModal} {@attach teleportTo("#app")} />
  {/if}

  {#key showLoginModal}
    {@const LoginModal = dynamicComponentStore.getComponentByName("LoginModal")}
    <LoginModal
      {@attach teleportTo("#app")}
      bind:open={showLoginModal}
      bind:username={loginStates.username}
      bind:password={loginStates.password}
      error={loginStates.error}
      loading={loginStates.loading}
      onClose={LoginManager.closeLogin}
      onLogin={LoginManager.Login}
    />
  {/key}
</header>
