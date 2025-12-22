/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
import { visualizer } from "rollup-plugin-visualizer";
import dotenv from "dotenv";
// import { compression, defineAlgorithm } from 'vite-plugin-compression2'
// import fs from "fs";

function loadEnvVariables() {
  // secondary .env from root project
  dotenv.config({ path: ("../../../.env") });
}

export default defineConfig(({ command }) => {
  loadEnvVariables();
  return {
    define: {
      // Only expose WP_ENV in development to avoid bundling it in production
      ...(command === 'serve' ? {
        'import.meta.env.WP_ENV': JSON.stringify(process.env.WP_ENV || 'production'),
      } : {}),
    },
    plugins: [
      svelte(),
      tailwindcss(),
      liveReload(["./vendor/composer/autoload_real.php"]),
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
    server: {
      host: "0.0.0.0",
      allowedHosts: true,
      port: 5173,
      cors: true,
      strictPort: false,
      hmr: { overlay: true, port: 5173, clientPort: 443 }
    },
    ...(command === "build"
      ? { base: "/wp-content/themes/wplokerbjm/assets/dist/" }
      : { base: "/__vite/" }),
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
  };
});
