import adapter from "@sveltejs/adapter-cloudflare";

/** @type {import('@sveltejs/kit').Config} */
const wranglerConfig = process.env.WRANGLER_ENV === "dev"
  ? "wrangler.dev.toml"
  : process.env.WRANGLER_ENV === "staging"
    ? "wrangler.staging.toml"
    : process.env.WRANGLER_ENV === "production" ? "wrangler.prod.toml" : "wrangler.dev.toml"; // default to dev if WRANGLER_ENV is not set or has an unexpected value

const config = {
  kit: {
    adapter: adapter(),
    alias: {
      "@components": "src/lib/components",
      "@css": "src/lib/assets/css",
      "@": "src",
      "@@": "/"
    }
  },
  compilerOptions: {
    runes: true,
    modernAst: true,
    hmr: true,
    discloseVersion: false,
    dev: true
  }
};

export default config;
