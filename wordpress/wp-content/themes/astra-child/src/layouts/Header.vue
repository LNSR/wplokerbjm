<template>
  <header
    class="fixed sm:relative left-0 w-full bg-[var(--ast-global-color-4)] !pb-4 border-b-2 border-[var(--ast-global-color-7)] min-h-auto z-[60]"
    style="top:var(--site-header-top, 0)">
    <div class="drawer drawer-end">
      <input id="header-drawer" type="checkbox" class="drawer-toggle" />
      <div class="drawer-content">
        <div class="!mr-auto !ml-auto !pl-4 !pr-4 max-w-[1240px] flex items-center justify-between">
          <div class="mt-4" v-html="props.logo"></div>
          <div class="flex items-center !gap-2 !mt-5">
            <!-- Color/theme switcher -->
            <div class="!backdrop-blur-lg rounded-full shadow-lg !p-2">
              <label class="flex cursor-pointer gap-2 items-center" :aria-hidden="false">
                <span class="relative w-12 h-6 flex items-center" role="switch" :aria-checked="isDark">
                  <span class="absolute inset-0 rounded-full bg-gray-200 dark:bg-slate-700 transition-colors"></span>
                  <span
                    class="absolute top-0 left-0 w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow transition-transform"
                    :style="{ transform: isDark ? 'translateX(100%)' : 'translateX(0)' }"></span>
                  <!-- Sun icon -->
                  <svg class="absolute left-1 top-1 w-4 h-4 transition-all z-10"
                    :class="isDark ? 'opacity-40 grayscale' : 'opacity-100'" style="color: var(--icon-color);"
                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                    <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none"
                      stroke="currentColor">
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
                  <svg class="absolute right-1 top-1 w-4 h-4 transition-all z-10"
                    :class="!isDark ? 'opacity-40 grayscale' : 'opacity-100'" style="color: var(--icon-color);"
                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                    <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none"
                      stroke="currentColor">
                      <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                    </g>
                  </svg>
                  <input type="checkbox" value="dark"
                    class="toggle theme-controller focus:ring-2 focus:ring-blue-400 absolute w-12 h-6 opacity-0 cursor-pointer"
                    aria-label="Toggle color theme" :aria-checked="isDark" v-model="isDark" />
                </span>
              </label>
            </div>

            <!-- Bookmark button on desktop -->
            <button @click="showBookmarkModal = true" class="btn !btn-circle md:flex relative"
              aria-label="Lowongan tersimpan" title="Lowongan tersimpan">
              <i class="fas fa-bookmark"></i>
              <span v-if="bookmarkStore.jobs.length > 0"
                class="absolute -top-2 -right-1 bg-[var(--ast-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10">{{
                  bookmarkStore.jobs.length }}</span>
            </button>
            <button class="btn btn-primary hidden md:block" @click="openWindow('/pasang-iklan-loker')">
              <i class="fas fa-arrow-up-right-from-square"></i>
              Pasang Iklan Loker
            </button>
            <!-- Drawer toggle button, shown only on mobile -->
            <label v-if="isMobile" for="header-drawer" class="drawer-button">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </label>
          </div>
        </div>
      </div>
      <!-- Mobile drawer side. margin-top reads the same CSS var we set in JS (--site-header-top) -->
      <div v-if="isMobile" class="drawer-side z-50" style="margin-top:var(--site-header-top, 0)">
        <label for="header-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
        <ul class="menu bg-base-200 text-base-content min-h-full w-auto max-w-[90vw] !p-4 !mx-4 !px-2 !gap-4">
          <li>
            <a href="/pasang-iklan-loker" class="!btn !btn-primary justify-start">
              <i class="fas fa-arrow-up-right-from-square"></i>
              Pasang Iklan Loker
            </a>
          </li>
        </ul>
      </div>
    </div>
    <BookmarkedModal v-if="showBookmarkModal" v-model="showBookmarkModal" />
  </header>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, defineAsyncComponent } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { type LayoutProps } from '@/types/Component';
import { getWpAdminBarTopOffset } from '@/utils/elements';
import { useSavedJobsStore } from '@/stores';
const BookmarkedModal = defineAsyncComponent(() => import('@/components/Header/BookmarkedModal.vue'));

const props = defineProps<LayoutProps>();

const bookmarkStore = useSavedJobsStore();
const showBookmarkModal = ref(false);

// reactive: measured WP admin bar offset (px)
const adminBarHeight = ref(0);

// Mirror Tailwind's md breakpoint so JS and CSS are consistent.
// `isMobile` toggles which layout logic we run (fixed header/padding on mobile).
const isMobile = ref(window.innerWidth < 768);
// matchMedia is more precise for breakpoint detection than checking innerWidth manually
const mobileMq = window.matchMedia('(max-width: 767.98px)');

const { isDark } = useTheme();

// store previous main paddingTop so we can restore it when component unmounts or when leaving mobile layout
let previousMainPadding: string | null = null;

let headerEl: HTMLElement | null = null;
let mainEl: HTMLElement | null = null;
let adminBarEl: HTMLElement | null = null;
let rafId: number | null = null;
let mutationObserver: MutationObserver | null = null;
let mqListener: ((ev: MediaQueryListEvent) => void) | null = null;

function scheduleUpdate() {
  if (rafId !== null) return;
  rafId = requestAnimationFrame(() => {
    rafId = null;
    // refresh mobile flag from matchMedia and then recompute offsets
    isMobile.value = mobileMq.matches;
    updateOffsets();
  });
}

function updateOffsets() {
  if (!headerEl) headerEl = document.querySelector('header') as HTMLElement | null;
  if (!mainEl) mainEl = document.querySelector('main') as HTMLElement | null;
  if (!adminBarEl) adminBarEl = document.getElementById('wpadminbar') as HTMLElement | null;

  // measure admin bar and compute top offset (only relevant for mobile)
  adminBarHeight.value = getWpAdminBarTopOffset();

  const top = isMobile.value ? adminBarHeight.value : 0;
  try {
    document.documentElement.style.setProperty('--site-header-top', top + 'px');
  } catch {
    // ignore: operations may fail in some restricted contexts (SSR or CSP)
  }

  if (isMobile.value && headerEl && mainEl) {
    const headerHeight = headerEl.offsetHeight || 0;
    if (previousMainPadding === null) previousMainPadding = mainEl.style.paddingTop || '';
    // still set inline padding for main for immediate layout safety while CSS var support propagates
    // (we also set --site-header-height for future CSS-only handling)
    mainEl.style.paddingTop = headerHeight + 'px';
    try {
      document.documentElement.style.setProperty('--site-header-height', headerHeight + 'px');
    } catch {
      /* ignore */
    }
  } else {
    // reset CSS vars and restore previous main padding when leaving mobile
    try {
      document.documentElement.style.setProperty('--site-header-top', '0px');
      document.documentElement.style.setProperty('--site-header-height', '0px');
    } catch {
      /* ignore */
    }
    if (mainEl && previousMainPadding !== null) {
      mainEl.style.paddingTop = previousMainPadding;
    }
  }
}

onMounted(() => {
  // cache DOM nodes once on mount for faster repeated access
  headerEl = document.querySelector('header') as HTMLElement | null;
  mainEl = document.querySelector('main') as HTMLElement | null;
  adminBarEl = document.getElementById('wpadminbar') as HTMLElement | null;

  // Use rAF-debounced listeners for scroll/resize to avoid layout thrashing.
  // We use passive listeners for scroll/resize to avoid blocking the main thread.
  window.addEventListener('resize', scheduleUpdate, { passive: true });
  window.addEventListener('scroll', scheduleUpdate, { passive: true });
  // run once immediately to initialize CSS vars and padding
  scheduleUpdate();

  // keep isMobile in sync with the matchMedia; scheduleUpdate will pick up the change
  mqListener = () => scheduleUpdate();
  mobileMq.addEventListener('change', mqListener);

  // Observe html attribute changes only (class/style) to detect WP toggles without watching whole body
  const htmlEl = document.documentElement;
  // observe changes to <html> attributes that may affect header positioning (WP toggles classes/style)
  mutationObserver = new MutationObserver(() => scheduleUpdate());
  mutationObserver.observe(htmlEl, { attributes: true, attributeFilter: ['class', 'style'] });
});

onUnmounted(() => {
  window.removeEventListener('resize', scheduleUpdate);
  window.removeEventListener('scroll', scheduleUpdate);
  if (rafId !== null) cancelAnimationFrame(rafId);
  if (mainEl && previousMainPadding !== null) {
    mainEl.style.paddingTop = previousMainPadding;
  }
  if (mutationObserver) {
    // stop observing to avoid memory leaks
    mutationObserver.disconnect();
    mutationObserver = null;
  }

  // remove matchMedia listener and clear CSS vars
  // remove media listener and CSS vars on unmount so other parts of the app are not affected
  if (mqListener) mobileMq.removeEventListener('change', mqListener);
  mqListener = null;

  document.documentElement.style.removeProperty('--site-header-top');
  document.documentElement.style.removeProperty('--site-header-height');
});

function openWindow(url: string) {
  window.open(url, '_blank');
}
</script>