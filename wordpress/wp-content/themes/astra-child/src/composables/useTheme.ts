import { onMounted, onUnmounted, watch, ref, type Ref } from 'vue'
import { debounce } from '@/utils/debounce'

let mediaQuery: MediaQueryList | null = null

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
    } catch {
      // ignore storage errors
    }
    if (!prefersReducedMotion()) {
      setTimeout(() => {
        document.documentElement.classList.remove('theme-switching')
      }, 30)
    }
    // update mobile browser chrome color to match the site theme
    updateMetaThemeColor(dark)
  })
}

function updateMetaThemeColor(dark: boolean): void {
  try {
    const root = document.documentElement
    const cs = getComputedStyle(root)
    // Try a project-level color var first; fall back to an Astra global color or defaults.
    let color = (cs.getPropertyValue('--theme-color') || cs.getPropertyValue('--ast-global-color-4') || '').trim()
    if (!color) color = dark ? '#0b1220' : '#ffffff'

    let meta = document.querySelector('meta[name="theme-color"]') as HTMLMetaElement | null
    if (!meta) {
      meta = document.createElement('meta')
      meta.name = 'theme-color'
      document.head.appendChild(meta)
    }
    meta.setAttribute('content', color)
  } catch {
    // ignore in restricted environments
  }
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
      if (mediaQuery && typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', handleSystemThemeChange as (e: MediaQueryListEvent) => void, { passive: true })
      }
    } catch {
      mediaQuery = null
    }

    watch(() => themeState.isDark.value, (v) => debouncedSetTheme(v))
  }

  function teardown(): void {
    if (!mediaQuery) return
    try {
      if (typeof mediaQuery.removeEventListener === 'function') {
        mediaQuery.removeEventListener('change', handleSystemThemeChange as (e: MediaQueryListEvent) => void)
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
