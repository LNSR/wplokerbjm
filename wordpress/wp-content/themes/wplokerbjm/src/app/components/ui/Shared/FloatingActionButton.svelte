<script module lang="ts">
  import { type SocialMediaItem, SocialMediaPlatform } from "@/types";
  import {
    ArrowUpSolid,
    HeadsetSolid,
    ChevronDownSolid,
    InstagramBrands,
    TiktokBrands,
    FacebookBrands,
    ThreadsBrands,
  } from "svelte-awesome-icons";

  const socialLinks: SocialMediaItem[] = [
    {
      url: "https://www.instagram.com/loker_banjarmasin",
      icon: InstagramBrands,
      platform: SocialMediaPlatform.Instagram,
      color: "text-pink-500 dark:text-pink-400",
    },
    {
      url: "https://www.tiktok.com/@loker_banjarmasin",
      icon: TiktokBrands,
      platform: SocialMediaPlatform.TikTok,
      color: "text-black dark:text-white",
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
  let show = $state(false);
  let dropdownOpen = $state(false);
  let dropdownRef: HTMLElement | null = null;

  const btnClass: string =
    "btn btn-sm border-1 border-[var(--wpl-global-color-1)] flex items-center gap-2 rounded-full px-4 py-3 cursor-pointer transform transition hover:scale-105 focus:ring-2 focus:ring-blue-400 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]";
  const gridBtnClass: string =
    "btn btn-sm border-1 border-[var(--wpl-global-color-1)] flex items-center gap-1 rounded-lg px-2 py-2 cursor-pointer transform transition hover:scale-105 focus:ring-2 focus:ring-blue-400 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]";

  let jobGridObserver: IntersectionObserver | null = null;

  function scrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
    show = false;
  }

  function toggleDropdown() {
    dropdownOpen = !dropdownOpen;
    if (dropdownOpen) setTimeout(() => dropdownRef?.querySelector("a"));
  }

  function closeDropdown() {
    dropdownOpen = false;
  }

  function handleClickOutside(event: MouseEvent) {
    if (dropdownRef && !dropdownRef.contains(event.target as Node))
      closeDropdown();
  }

  function observeJobGrid() {
    const jobGrid = document.getElementById("job-grid");
    if (!jobGrid) return;
    jobGridObserver = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          show = true;
        }
      },
      { threshold: 0.1 }
    );
    jobGridObserver.observe(jobGrid);
  }

  function handleScroll() {
    show = window.scrollY > 0;
  }

  function handleKeyDown(event: KeyboardEvent) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      toggleDropdown();
    }
    if (event.key === "Escape") closeDropdown();
  }
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";

  onMount(() => {
    document.addEventListener("mousedown", handleClickOutside);
    observeJobGrid();
    window.addEventListener("scroll", handleScroll);
    handleScroll();
  });

  onDestroy(() => {
    document.removeEventListener("mousedown", handleClickOutside);
    if (jobGridObserver) jobGridObserver.disconnect();
    window.removeEventListener("scroll", handleScroll);
  });
</script>

<aside class="fixed bottom-3 right-3 z-30 flex flex-col items-end gap-4" style="view-transition-name: fab;">
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
  <div class="relative" bind:this={dropdownRef}>
    <button
      class={btnClass}
      onmousedown={(e) => {
        e.preventDefault();
        toggleDropdown();
      }}
      onkeydown={handleKeyDown}
      aria-haspopup="menu"
      title="Kontak Admin"
      tabindex="0"
      aria-expanded={dropdownOpen}
    >
      <!-- Headset icon converted to SVG component -->
      <HeadsetSolid class="text-xl w-5 text-center" aria-hidden="true" />
      <span>Kontak Admin</span>
      <ChevronDownSolid
        class="w-4 h-4 ml-1 transition-transform {dropdownOpen ? 'rotate-180' : ''}"
        aria-hidden="true"
      />
    </button>

    {#if dropdownOpen}
      <div
        class="shadow-xl border border-blue-500 rounded-xl p-4 w-72 absolute bottom-full mb-2 right-0 z-50 bg-[var(--wpl-global-color-5)]"
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
