import adapter from "@sveltejs/adapter-cloudflare";

/** @type {import('@sveltejs/kit').Config} */
const config = {
  kit: {
    version: {
      name: Date.now().toString() 
    },
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
