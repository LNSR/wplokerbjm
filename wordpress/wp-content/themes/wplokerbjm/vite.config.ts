/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
import { visualizer } from "rollup-plugin-visualizer";
// import { compression, defineAlgorithm } from 'vite-plugin-compression2'
import fs from "fs";
import path from "path";

export default defineConfig(({ command }) => ({
  plugins: [
    svelte(),
    tailwindcss(),
    liveReload(["./**/*.php"]),
    visualizer({ open: true }),
    // compression({
    //   deleteOriginalAssets: true,
    //   exclude: ["**/*.json"],
    //   threshold: 0,
    //   skipIfLargerOrEqual: false,
    //   logLevel: 'info',
    //   algorithms: [defineAlgorithm('zstd', {
    //     params: {
    //       [require('zlib').constants.ZSTD_c_compressionLevel]: 22,
    //       [require('zlib').constants.ZSTD_c_checksumFlag]: 1,
    //       [require('zlib').constants.ZSTD_c_strategy]: require('zlib').constants.ZSTD_btultra2,
    //       [require('zlib').constants.ZSTD_c_windowLog]: 27,
    //     },
    //   })],
    // })
  ],
  resolve: {
    alias: {
      "@@": resolve(__dirname, "./"),
      "@": resolve(__dirname, "./src"),
      "$lib": resolve(__dirname, "./src/app/lib"),
      "@routes": resolve(__dirname, "./src/app/routes"),
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
        exports: "auto",
        hashCharacters: "base64",
        minify: true,
        inlineDynamicImports: false,
        format: "esm",
        entryFileNames: "js/[name]-[hash:32].js",
        chunkFileNames: "js/[name]-[hash:32].js",
        assetFileNames: (assetInfo): string => {
          const assetName =
            assetInfo.names && assetInfo.names.length > 0
              ? assetInfo.names[0]
              : "";
          if (assetName.endsWith(".css")) {
            return "css/[name]-[hash:32][extname]";
          }
          if (assetName && /\.(woff2?|ttf|otf|eot)$/.test(assetName)) {
            return "webfonts/[name]-[hash:32][extname]";
          }
          return "assets/[name]-[hash:32][extname]";
        },
      }
    },
  },
}));
