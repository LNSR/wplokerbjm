<template>
  <div class="!backdrop-blur-lg rounded-full shadow-lg p-2">
    <label class="flex cursor-pointer gap-2 items-center">
      <span class="relative w-12 h-6 flex items-center">
        <span class="absolute inset-0 rounded-full bg-gray-200 dark:bg-slate-700 transition"></span>
        <span
          class="absolute top-0 left-0 w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow transition-transform"
          :style="{ transform: isDark ? 'translateX(100%)' : 'translateX(0)' }"
        ></span>
        <!-- Sun icon -->
        <svg
          class="absolute left-1 top-1 w-4 h-4 transition-all z-10"
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
        <!-- Moon icon -->
        <svg
          class="absolute right-1 top-1 w-4 h-4 transition-all z-10"
          :class="!isDark ? 'opacity-40 grayscale' : 'opacity-100'"
          style="color: var(--icon-color);"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
        >
          <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2" fill="none" stroke="currentColor">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
          </g>
        </svg>
        <input
          type="checkbox"
          value="dark"
          class="toggle theme-controller focus:ring-2 focus:ring-blue-400 absolute w-12 h-6 opacity-0 cursor-pointer"
          aria-label="Theme Switch"
          v-model="isDark"
        />
      </span>
    </label>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { debounce } from '@/utils/debounce'

const isDark = ref(false)
let mediaQuery: MediaQueryList
let currentTheme = ''

function setTheme(dark: boolean) {
  const newTheme = dark ? 'dark' : 'light'
  if (currentTheme === newTheme) return
  currentTheme = newTheme

  window.requestAnimationFrame(() => {
    document.documentElement.classList.add('theme-switching')
    document.documentElement.setAttribute('data-theme', newTheme)
    if (dark) {
      document.documentElement.classList.add('astra-dark-mode-enable')
    } else {
      document.documentElement.classList.remove('astra-dark-mode-enable')
    }
    try {
      localStorage.setItem('astra-theme', newTheme)
    } catch (e) {
      console.error('Error saving theme to localStorage:', e)
    }
    setTimeout(() => {
      document.documentElement.classList.remove('theme-switching')
    }, 30)
  })
}

function handleSystemThemeChange(e: MediaQueryListEvent) {
  if (!localStorage.getItem('astra-theme')) {
    isDark.value = e.matches
    setTheme(e.matches)
  }
}

onMounted(() => {
  let saved = ''
  try {
    saved = localStorage.getItem('astra-theme') || ''
  } catch (e) {
    console.error('Error reading localStorage:', e)
  }

  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true
    setTheme(true)
  } else {
    isDark.value = false
    setTheme(false)
  }

  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', handleSystemThemeChange, { passive: true })
})

const debouncedSetTheme = debounce(setTheme, 10)

watch(isDark, (val) => {
  debouncedSetTheme(val)
})
</script>