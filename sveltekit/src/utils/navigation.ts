import { goto } from "$app/navigation";

type GotoOptions = NonNullable<Parameters<typeof goto>[1]>;

/**
 * Navigates with SvelteKit's `goto`, falling back to a full-page navigation
 * when the target does not resolve to an app route (Kit 3 rejects those).
 */
export function gotoWithFallback(to: string, options?: GotoOptions): Promise<void> {
  // The helper intentionally accepts dynamic/non-route targets and falls back to a full navigation.
  // eslint-disable-next-line svelte/no-navigation-without-resolve
  return goto(to, options).catch(() => {
    window.location.assign(to);
  });
}
