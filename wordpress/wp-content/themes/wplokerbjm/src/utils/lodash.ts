import lodashDebounce from 'lodash-es/debounce'
import lodashThrottle from 'lodash-es/throttle'

/**
 * Custom debounce wrapper for lodash.debounce.
 *
 */
// eslint-disable-next-line
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
// eslint-disable-next-line @typescript-eslint/no-explicit-any
): any {
  return lodashDebounce(func, wait, options)
}

/**
 * Custom throttle wrapper for lodash.throttle.
 *
 */
// eslint-disable-next-line
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
// eslint-disable-next-line @typescript-eslint/no-explicit-any
): any {
  return lodashThrottle(func, wait, options)
}