import lodashDebounce from 'lodash-es/debounce'
import lodashThrottle from 'lodash-es/throttle'

/**
 * Debounced function type (matches lodash.debounce's returned function)
 * - call signature for scheduling
 * - `.flush()` to immediately invoke pending call
 * - `.cancel()` to cancel any pending invocation
 */
export type DebouncedFunction = {
  (...args: any[]): void;
  flush: () => void;
  cancel: () => void;
};

/**
 * Custom debounce wrapper for lodash.debounce.
 *
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
): any {
  return lodashDebounce(func, wait, options)
}

/**
 * Custom throttle wrapper for lodash.throttle.
 *
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: { leading?: boolean; trailing?: boolean }
): any {
  return lodashThrottle(func, wait, options)
}