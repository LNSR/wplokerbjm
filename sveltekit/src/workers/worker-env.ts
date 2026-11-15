/**
 * SvelteKit injects its dev runtime globals into the page, but not into
 * dedicated Web Workers. Prime them before any `$app/env` import runs.
 */
if (import.meta.env.DEV) {
  Object.assign(globalThis, {
    __SVELTEKIT_DEV__: true,
    __sveltekit_dev: {
      base: "",
      env: {},
      version: "",
    },
  });
}
