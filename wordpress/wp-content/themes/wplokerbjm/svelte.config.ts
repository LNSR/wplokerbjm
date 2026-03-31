import adapter from "@sveltejs/adapter-cloudflare";
import { vitePreprocess } from "@sveltejs/vite-plugin-svelte";
import type { Config } from "@sveltejs/kit";

const config: Config = {
  // preprocess: vitePreprocess({ script: true }),
  kit: {
    version: {
      name: Date.now().toString(),
    },
    prerender: {
      origin: "https://lokerbanjarmasin.my.id",
      concurrency: 4,
    },
    adapter: adapter(),
    alias: {
      "@components": "src/lib/components",
      "@css": "src/lib/assets/css",
      "@": "src",
      "@@": "/",
    },
  },
  compilerOptions: {
    runes: true,
    modernAst: true,
    hmr: true,
    discloseVersion: false,
    dev: true,
  },
};

export default config;
