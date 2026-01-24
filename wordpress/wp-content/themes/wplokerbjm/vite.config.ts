/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
import { unstableRolldownAdapter } from 'vite-bundle-analyzer'
import { analyzer } from 'vite-bundle-analyzer'
import { partytownVite } from "@qwik.dev/partytown/utils";
import { generateCaddyEarlyHints } from "./vite-plugins/generate-caddy-early-hints";

export default defineConfig(({ command }) => {
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
      unstableRolldownAdapter(analyzer({ fileName: 'stats', openAnalyzer: false, analyzerMode: 'static' })),
      partytownVite({
        dest: resolve(__dirname, "assets", "dist", "~partytown")
      }),
      generateCaddyEarlyHints({
        manifestPath: resolve(__dirname, 'assets/dist/.vite/manifest.json'),
        outputPath: resolve(__dirname, '../../../../configs/caddy-early-hints.conf')
      })
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
          // codeSplitting: true,
          format: "esm",
          entryFileNames: "js/[name]-[hash:6].js",
          chunkFileNames: "js/[name]-[hash:6].js",
          assetFileNames: (assetInfo: any): string => {
            const assetName =
              assetInfo.names && assetInfo.names.length > 0
                ? assetInfo.names[0]
                : "";
            if (assetName.endsWith(".css")) {
              return "css/[name]-[hash:6][extname]";
            }
            if (assetName && /\.(woff2?|ttf|otf|eot)$/.test(assetName)) {
              return "webfonts/[name]-[hash:6][extname]";
            }
            return "assets/[name]-[hash:6][extname]";
          },
        },
      },
    },
  };
});
