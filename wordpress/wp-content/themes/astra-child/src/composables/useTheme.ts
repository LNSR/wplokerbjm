import { onMounted, onUnmounted, watch, ref, type Ref } from 'vue'
import { debounce } from '@/utils/debounce'

type MediaQueryListWithLegacy = MediaQueryList & {
  addListener?: (listener: (e: MediaQueryListEvent) => void) => void;
  removeListener?: (listener: (e: MediaQueryListEvent) => void) => void;
};

let mediaQuery: MediaQueryListWithLegacy | null = null

interface ThemeState {
  isDark: Ref<boolean>
  currentTheme: Ref<string>
}
const themeState: ThemeState = {
  isDark: ref<boolean>(false),
  currentTheme: ref<string>('')
}

function prefersReducedMotion(): boolean {
  try {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
  } catch {
    return false
  }
}

function setThemeDirect(dark: boolean): void {
  const newTheme = dark ? 'dark' : 'light'
  if (themeState.currentTheme.value === newTheme) return
  themeState.currentTheme.value = newTheme

  window.requestAnimationFrame(() => {
    if (!prefersReducedMotion()) {
      document.documentElement.classList.add('theme-switching')
    }
    document.documentElement.setAttribute('data-theme', newTheme)
    if (dark) {
      document.documentElement.classList.add('astra-dark-mode-enable')
    } else {
      document.documentElement.classList.remove('astra-dark-mode-enable')
    }
    try {
      localStorage.setItem('astra-theme', newTheme)
    } catch (err) {
  // ignore storage errors
  console.error('Error saving theme to localStorage:', err)
    }
    if (!prefersReducedMotion()) {
      setTimeout(() => {
        document.documentElement.classList.remove('theme-switching')
      }, 30)
    }
  })
}

const debouncedSetTheme = debounce(setThemeDirect, 10)

function handleSystemThemeChange(e: MediaQueryListEvent | MediaQueryList): void {
  let hasStored = false
  try {
    hasStored = !!localStorage.getItem('astra-theme')
  } catch {
    hasStored = false
  }
  if (!hasStored) {
    const matches = ('matches' in e) ? (e as MediaQueryListEvent).matches : (e as MediaQueryList).matches
    themeState.isDark.value = matches
    setThemeDirect(matches)
  }
}

export function useTheme(): {
  isDark: Ref<boolean>;
  setTheme: (dark: boolean) => void;
} {
  function init(): void {
    let saved = ''
    try {
      saved = localStorage.getItem('astra-theme') || ''
    } catch {
      saved = ''
    }

    const systemPrefersDark = ((): boolean => {
      try {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      } catch {
        return false
      }
    })()

    if (saved === 'dark' || (!saved && systemPrefersDark)) {
      themeState.isDark.value = true
      setThemeDirect(true)
    } else {
      themeState.isDark.value = false
      setThemeDirect(false)
    }

    try {
      mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
      if (mediaQuery) {
        if (typeof mediaQuery.addEventListener === 'function') {
          mediaQuery.addEventListener('change', handleSystemThemeChange as unknown as (e: MediaQueryListEvent) => void, { passive: true })
        } else if (typeof mediaQuery.addListener === 'function') {
          mediaQuery.addListener!(handleSystemThemeChange)
        }
      }
    } catch {
      mediaQuery = null
    }

    // Fix: watch the value of the ref, not the ref itself
    watch(() => themeState.isDark.value, (v) => debouncedSetTheme(v))
  }

  function teardown(): void {
    if (!mediaQuery) return
    try {
      if (typeof mediaQuery.removeEventListener === 'function') {
        mediaQuery.removeEventListener('change', handleSystemThemeChange as unknown as (e: MediaQueryListEvent) => void)
      } else if (typeof mediaQuery.removeListener === 'function') {
        mediaQuery.removeListener!(handleSystemThemeChange)
      }
    } catch {
      // ignore
    }
  }

  onMounted(() => init())
  onUnmounted(() => teardown())

  return {
    isDark: themeState.isDark,
    setTheme: (dark: boolean): void => {
      themeState.isDark.value = dark
      debouncedSetTheme(dark)
    }
  }
}
