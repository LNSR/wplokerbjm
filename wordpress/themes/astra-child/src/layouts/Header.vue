<template>
  <div class="drawer drawer-end">
    <input id="header-drawer" type="checkbox" class="drawer-toggle" />
    <div class="drawer-content">
      <div class="!mr-auto !ml-auto !pl-4 !pr-4 max-w-[1240px] flex items-center justify-between">
        <div class="mt-4" v-html="headerData.logo"></div>
        <div class="flex items-center gap-2 mt-5">
          <!-- Desktop button -->
          <ColorSwitchButton />
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
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import ColorSwitchButton from '@/components/ColorSwitchButton.vue';

const props = defineProps<{ logo?: string; }>();
const headerData = reactive({
  logo: props.logo || ''
});

const adminBarHeight = ref(0);

// Mobile detection
const isMobile = ref(window.innerWidth < 768);
function handleResize() {
  isMobile.value = window.innerWidth < 768;
}
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