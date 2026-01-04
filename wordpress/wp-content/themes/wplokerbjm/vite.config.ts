/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
import { unstableRolldownAdapter } from 'vite-bundle-analyzer'
import { analyzer } from 'vite-bundle-analyzer'
import { partytownVite } from "@qwik.dev/partytown/utils";
// import { compression, defineAlgorithm } from 'vite-plugin-compression2'
// import zlib from 'zlib';

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
      // compression({
      //   deleteOriginalAssets: false,
      //   exclude: ["**/*.json", "**/*.map", "**/*.xml", "**/*.svg", "**/*.webmanifest", "**/*.txt", "**/*.woff2", "**/*.woff"],
      //   threshold: 0,
      //   skipIfLargerOrEqual: false,
      //   logLevel: 'info',
      //   algorithms: [defineAlgorithm('brotliCompress', {
      //     params: {
      //       [zlib.constants.BROTLI_PARAM_QUALITY]: 11
      //     }
      //   })]
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
          entryFileNames: "js/[name]-[hash:21].js",
          chunkFileNames: "js/[name]-[hash:21].js",
          assetFileNames: (assetInfo: any): string => {
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
        },
      },
    },
  };
});
