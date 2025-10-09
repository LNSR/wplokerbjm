/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
import { visualizer } from "rollup-plugin-visualizer";
import fs from "fs";
import path from "path";

export default defineConfig(({ command }) => ({
  plugins: [
    svelte({
      dynamicCompileOptions({ filename }) {
        if (filename.includes('node_modules')) {
          // Whitelist packages that ship Svelte 5 source and therefore
          // must be compiled with runes enabled.
          const runesWhitelist = [
            'svelte-awesome-icons',
          ];
          for (const pkg of runesWhitelist) {
            if (filename.includes(`node_modules/${pkg}`)) {
              return { runes: true };
            }
          }
          // Most node_modules packages are authored for legacy mode ---
          // compile those with runes disabled to avoid invalid-runes
          // syntax errors (export let, $$restProps, etc.).
          return { runes: false };
        }
      }
    }),
    tailwindcss(),
    liveReload(["./**/*.php"]),
    visualizer({ open: true }),
  ],
  resolve: {
    alias: {
      "@@": resolve(__dirname, "./"),
      "@": resolve(__dirname, "./src"),
      "$lib": resolve(__dirname, "./src/app/lib"),
      "@routes": resolve(__dirname, "./src/routes"),
      "@components": resolve(__dirname, "./src/app/components"),
      "@css": resolve(__dirname, "./src/assets/css"),
    },
  },
  server: (() => {
    const base = { host: "0.0.0.0", port: 5173, cors: true };
    if (command !== "serve") return base;
    try {
      const key = fs.readFileSync(
        path.resolve(__dirname, "../../../../localhost-key.pem")
      );
      const cert = fs.readFileSync(path.resolve(__dirname, "../../../../localhost.pem"));
      return { ...base, https: { key, cert } };
    } catch (err) {
      console.warn("Local HTTPS certs not available; running dev server without HTTPS:", String(err));
      return base;
    }
  })(),
  // Ensure svelte-awesome-icons is pre-bundled in dev and compiled for SSR
  // so the package's .svelte sources are handled consistently with the app.
  optimizeDeps: {
    include: ["svelte-awesome-icons"],
  },
  ssr: {
    noExternal: ["svelte-awesome-icons"],
  },
  ...(command === "build"
    ? { base: "/wp-content/themes/wplokerbjm/assets/dist/" }
    : {}),
  build: {
    outDir: "./assets/dist",
    emptyOutDir: true,
    sourcemap: false,
    manifest: true,
    target: "esnext",
    rolldownOptions: {
      input: {
        main: "src/main.ts",
      },
      output: {
        inlineDynamicImports: false,
        format: "es",
        entryFileNames: "js/[name]-[hash].js",
        chunkFileNames: "js/[name]-[hash].js",
        assetFileNames: (assetInfo): string => {
          const assetName =
            assetInfo.names && assetInfo.names.length > 0
              ? assetInfo.names[0]
              : "";
          if (assetName.endsWith(".css")) {
            return "css/[name]-[hash][extname]";
          }
          if (assetName && /\.(woff2?|ttf|otf|eot)$/.test(assetName)) {
            return "webfonts/[name]-[hash][extname]";
          }
          return "assets/[name]-[hash][extname]";
        },
      }
    },
  },
}));
