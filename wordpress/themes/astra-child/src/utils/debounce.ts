import lodashDebounce from 'lodash/debounce'

/**
 * Custom debounce wrapper for lodash.debounce.
 *
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
): (...args: Parameters<T>) => ReturnType<T> {
  return lodashDebounce(func, wait, options)
}