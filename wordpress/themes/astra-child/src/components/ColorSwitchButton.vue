<template>
  <div
    class="fixed z-50 right-3 top-2 lg:!top-8 transition-opacity duration-500"
    :style="{ opacity: visible ? '1' : '0' }"
    @mouseenter="showButton"
    @focusin="showButton"
    @mouseleave="hideSoon"
    @focusout="hideSoon"
  >
    <div class="backdrop-blur-md bg-white/60 dark:bg-slate-800/60 rounded-full shadow-lg p-2">
      <label class="flex cursor-pointer gap-2 items-center" title="Ganti tema">
        <!-- Sun icon (always visible, dim if dark) -->
        <svg
          class="w-6 h-6 transition-all"
          :class="isDark ? 'opacity-40 grayscale' : 'opacity-100'"
          style="color: var(--icon-color);"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
        >
          <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor">
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
        <input
          type="checkbox"
          value="dark"
          class="toggle theme-controller focus:ring-2 focus:ring-blue-400"
          aria-label="Theme Switch"
          v-model="isDark"
        />
        <!-- Moon icon (always visible, dim if light) -->
        <svg
          class="w-6 h-6 transition-all"
          :class="!isDark ? 'opacity-40 grayscale' : 'opacity-100'"
          style="color: var(--icon-color);"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
        >
          <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
          </g>
        </svg>
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { debounce } from '@/utils/debounce'

const isDark = ref(false)
const visible = ref(false)
let hideTimeout: ReturnType<typeof setTimeout> | null = null
let mediaQuery: MediaQueryList

function setTheme(dark: boolean) {
  document.documentElement.classList.add('theme-switching')
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
  localStorage.setItem('astra-theme', dark ? 'dark' : 'light')
  setTimeout(() => {
    document.documentElement.classList.remove('theme-switching')
  }, 120)
}

// Use requestAnimationFrame to batch DOM update
function showButtonRaf() {
  window.requestAnimationFrame(() => {
    visible.value = true
    if (hideTimeout) clearTimeout(hideTimeout)
  })
}

// Debounce the showButton handler
const debouncedShowButton = debounce(showButtonRaf, 120)

// Expose showButton for template events
function showButton() {
  debouncedShowButton()
}

function hideSoon() {
  if (hideTimeout) clearTimeout(hideTimeout)
  hideTimeout = setTimeout(() => {
    visible.value = false
  }, 2000)
}

function handleSystemThemeChange(e: MediaQueryListEvent) {
  if (!localStorage.getItem('astra-theme')) {
    isDark.value = e.matches
    setTheme(e.matches)
  }
}

onMounted(() => {
  const saved = localStorage.getItem('astra-theme')
  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true
    setTheme(true)
  } else {
    isDark.value = false
    setTheme(false)
  }

  // Use debounced handler for input events
  window.addEventListener('mousemove', debouncedShowButton, { passive: true })
  window.addEventListener('scroll', debouncedShowButton, { passive: true })
  showButtonRaf()
  hideSoon()

  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', handleSystemThemeChange)
})

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', debouncedShowButton)
  window.removeEventListener('scroll', debouncedShowButton)
  if (hideTimeout) clearTimeout(hideTimeout)
  if (mediaQuery) {
    mediaQuery.removeEventListener('change', handleSystemThemeChange)
  }
})

watch(isDark, (val) => {
  setTheme(val)
})
</script>