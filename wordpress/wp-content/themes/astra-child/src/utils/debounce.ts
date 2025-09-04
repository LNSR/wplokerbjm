import lodashDebounce from 'lodash-es/debounce'

/**
 * Custom debounce wrapper for lodash.debounce.
 *
 */
// eslint-disable-next-line
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
): (...args: Parameters<T>) => ReturnType<T> {
  return lodashDebounce(func, wait, options)
}