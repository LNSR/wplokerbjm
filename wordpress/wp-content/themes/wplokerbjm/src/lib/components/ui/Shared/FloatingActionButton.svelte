<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { type SocialMediaItem, SocialMediaPlatform } from "@/types";
  import {
    ArrowUpSolid,
    HeadsetSolid,
    ChevronDownSolid,
    InstagramBrands,
    FacebookBrands,
    ThreadsBrands,
  } from "svelte-awesome-icons";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";

  const isMobile = $derived(deviceDetector.isPlatformMobile);

  let show = $state(false);
  let hideAtBottom = $state(false);

  let jobGridObserver: IntersectionObserver | null = null;

  /**
   * Event handler helpers for mouse/keyboard interactions.
   */
  class InteractionController {
    static handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        dropdownHandler.openDropdown();
      }

      if (event.key === "Escape") dropdownHandler.closeDropdown();
    };

    static handleGlobalKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") dropdownHandler.closeDropdown();
    };

    static handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownHandler.dropdownRef &&
        !dropdownHandler.dropdownRef.contains(event.target as Node)
      )
        dropdownHandler.closeDropdown();
    };
    static handleMouseDown = (event: MouseEvent) => {
      event.preventDefault();
      dropdownHandler.openDropdown();
    };
  }

  class DropdownHandler {
    public dropdownOpen = $state(false);
    public dropdownRef = $state<HTMLElement | null>(null);
    public openDropdown() {
      this.dropdownOpen = true;
    }

    public closeDropdown() {
      this.dropdownOpen = false;
    }
  }

  /**
   * Scroll smoothly to the top of the page when the button is clicked, and hide the button afterward.
   */
  function scrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
    show = false;
  }

  /**
   * Handle scroll events to determine when to show the scroll-to-top button and when to hide it at the bottom of the page on mobile devices.
   */
  function handleScroll() {
    const scrolledToBottom =
      window.scrollY + window.innerHeight >= document.body.scrollHeight - 100;
    hideAtBottom = scrolledToBottom && isMobile;

    const shouldShow = window.scrollY > 0;
    if (show !== shouldShow) show = shouldShow;
  }

  /**
   * Set up an IntersectionObserver to watch for the job grid element and show the scroll-to-top button when it comes into view.
   */
  function observeJobGrid() {
    const jobGrid = isJobGridEl();
    if (!jobGrid) return;

    jobGridObserver = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          show = true;
        }
      },
      { threshold: 0.1 },
    );

    jobGridObserver.observe(jobGrid);
  }

  const socialLinks: SocialMediaItem[] = [
    {
      url: "https://www.instagram.com/loker_banjarmasin",
      icon: InstagramBrands,
      platform: SocialMediaPlatform.Instagram,
      color: "text-pink-500 dark:text-pink-400",
    },
    {
      url: "https://www.facebook.com/loker.banjarmasin.2025",
      icon: FacebookBrands,
      platform: SocialMediaPlatform.Facebook,
      color: "text-[var(--wpl-global-color-1)] dark:text-blue-400",
    },
    {
      url: "https://www.threads.net/@loker_banjarmasin",
      icon: ThreadsBrands,
      platform: SocialMediaPlatform.Threads,
      color: "text-black dark:text-white",
    },
  ];

  const btnClass: string =
    "btn btn-sm border-1 border-[var(--wpl-global-color-1)] flex items-center gap-2 rounded-full px-4 py-3 cursor-pointer transform transition hover:scale-105 focus:ring-2 focus:ring-blue-400 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]";
  const gridBtnClass: string =
    "btn btn-sm border-1 border-[var(--wpl-global-color-1)] flex items-center gap-1 rounded-lg px-2 py-2 cursor-pointer transform transition hover:scale-105 focus:ring-2 focus:ring-blue-400 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]";

  const dropdownHandler = new DropdownHandler();

  onMount(() => {
    observeJobGrid();
    handleScroll();
  });

  onDestroy(() => {
    jobGridObserver?.disconnect();
  });
</script>

<svelte:window
  on:scroll={handleScroll}
  on:keydown={InteractionController.handleGlobalKeyDown}
/>
<svelte:document on:mousedown={InteractionController.handleClickOutside} />

{#if !(hideAtBottom && isMobile)}
  <aside
    class="fixed bottom-3 right-3 z-30 flex flex-col items-end gap-4"
    style="view-transition-name: none;"
  >
    {#if show}
      <!-- Scroll to Top Button -->
      <button
        onclick={scrollToTop}
        class={btnClass + " !h-2 !w-12 sm:h-2 sm:w-8"}
        title="Kembali ke Atas"
        aria-label="Kembali ke Atas"
      >
        <!-- Arrow icon switched to SVG component for tree-shaking -->
        <ArrowUpSolid class="text-base" aria-hidden="true" />
      </button>
    {/if}

    <!-- Contact Dropdown -->
    <div class="relative" bind:this={dropdownHandler.dropdownRef}>
      <button
        class={btnClass}
        onmousedown={InteractionController.handleMouseDown}
        onkeydown={InteractionController.handleKeyDown}
        aria-haspopup="menu"
        title="Kontak Admin"
        tabindex="0"
        aria-expanded={dropdownHandler.dropdownOpen}
      >
        <!-- Headset icon converted to SVG component -->
        <HeadsetSolid class="text-xl w-5 text-center" aria-hidden="true" />
        <span>Kontak Admin</span>
        <ChevronDownSolid
          class="w-4 h-4 ml-1 transition-transform {dropdownHandler.dropdownOpen
            ? 'rotate-180'
            : ''}"
          aria-hidden="true"
        />
      </button>

      {#if dropdownHandler.dropdownOpen}
        <div
          class="shadow-xl border border-[var(--wpl-global-color-5)] rounded-xl p-4 w-72 absolute bottom-full mb-2 right-0 z-50 bg-[var(--wpl-global-color-5)]"
          role="menu"
        >
          <div class="grid grid-cols-2 gap-2" role="menu">
            {#each socialLinks as link}
              {@const Icon = link.icon}
              <a
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                class={gridBtnClass}
                role="menuitem"
                tabindex="0"
              >
                {#if Icon}
                  <Icon
                    class="{link.color} text-lg w-5 inline-block text-center static"
                    aria-hidden="true"
                  />
                {/if}
                <span class="font-semibold text-sm ml-1">{link.platform}</span>
              </a>
            {/each}
          </div>
        </div>
      {/if}
    </div>
  </aside>
{/if}
