import lodashDebounce from 'lodash-es/debounce'
import lodashThrottle from 'lodash-es/throttle'
import type { DebouncedFunc, DebounceSettings, ThrottleSettings } from 'lodash'

/**
 * Debounced function type (matches lodash.debounce/throttle returned function)
 */
export type DebouncedFunction<T extends (...args: any[]) => any> = DebouncedFunc<T>

/**
 * Throttled function type (matches lodash.throttle returned function)
 */
export type ThrottledFunction<T extends (...args: any[]) => any> = DebouncedFunc<T>

/**
 * Custom debounce wrapper for lodash.debounce.
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: DebounceSettings
): DebouncedFunction<T> {
  return lodashDebounce(func, wait, options)
}

/**
 * Custom throttle wrapper for lodash.throttle.
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  wait = 300,
  options?: ThrottleSettings
): ThrottledFunction<T> {
  return lodashThrottle(func, wait, options)
}