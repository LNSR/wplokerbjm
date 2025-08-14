<template>
  <header class="relative !pb-4 border-b-2 border-[var(--ast-global-color-7)] min-h-[86px]">
    <div class="drawer drawer-end">
      <input id="header-drawer" type="checkbox" class="drawer-toggle" />
      <div class="drawer-content">
        <div class="!mr-auto !ml-auto !pl-4 !pr-4 max-w-[1240px] flex items-center justify-between">
          <div class="mt-4" v-html="props.header"></div>
          <div class="flex items-center gap-2 mt-5">
            <!-- Inlined ColorSwitchButton -->
            <div class="!backdrop-blur-lg rounded-full shadow-lg p-2">
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

            <button class="btn btn-primary hidden md:block" @click="openWindow('/pasang-iklan-loker')">
              Pasang Iklan Loker
            </button>
            <!-- Drawer toggle button, visible on mobile -->
            <label v-if="isMobile" for="header-drawer" class="drawer-button">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </label>
          </div>
        </div>
      </div>
      <div v-if="isMobile" class="drawer-side z-50" :style="{ marginTop: adminBarHeight + 'px' }">
        <label for="header-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
        <ul class="menu bg-base-200 text-base-content min-h-full w-auto max-w-[90vw] !p-4 mx-4 px-2">
          <li class="!mt-4">
            <a href="/pasang-iklan-loker" class="btn btn-primary">Pasang Iklan Loker</a>
          </li>
        </ul>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { type LayoutProps } from '@/types/Component';

const props = defineProps<LayoutProps>();

const adminBarHeight = ref(0);

// Mobile detection
const isMobile = ref(window.innerWidth < 768);
function handleResize() {
  isMobile.value = window.innerWidth < 768;
}

// use theme composable (handles init/teardown internally)
const { isDark } = useTheme();

onMounted(() => {
  window.addEventListener('resize', handleResize);
  handleResize();
  const adminBar = document.getElementById('wpadminbar');
  if (adminBar) {
    adminBarHeight.value = adminBar.offsetHeight;
  }
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});

function openWindow(url: string) {
  window.open(url, '_blank');
}
</script>