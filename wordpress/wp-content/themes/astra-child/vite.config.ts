/** @type {import('vite').UserConfigExport} */
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import { resolve } from "path";
import liveReload from "vite-plugin-live-reload";
// import { compression, defineAlgorithm } from "vite-plugin-compression2";
import { visualizer } from "rollup-plugin-visualizer";
// import { constants as zlibConstants } from "zlib";
import fs from "fs";
import path from "path";

export default defineConfig(({ command }) => ({
  plugins: [
    vue(),
    tailwindcss(),
    liveReload(["./**/*.php"]),
    // compression({
    //   algorithms: [
    //     defineAlgorithm("zstd", {
    //       params: {
    //         [zlibConstants.ZSTD_c_compressionLevel]: 19,
    //         [zlibConstants.ZSTD_c_nbWorkers]: 4,
    //         [zlibConstants.ZSTD_c_contentSizeFlag]: true,
    //         [zlibConstants.ZSTD_c_checksumFlag]: true,
    //       },
    //     }),
    //   ],
    // }),
    visualizer({ open: true }),
  ],
  resolve: {
    alias: {
      "@": resolve(__dirname, "./src"),
      "@assets": resolve(__dirname, "./assets"),
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
    ? { base: "/wp-content/themes/astra-child/assets/dist/" }
    : {}),
  build: {
    outDir: "./assets/dist",
    emptyOutDir: true,
    sourcemap: false,
    manifest: true,
    target: "esnext",
    rollupOptions: {
      input: {
        main: "@/main.ts",
      },
      output: {
        inlineDynamicImports: false,
        format: "es",
        entryFileNames: "js/[name]-[hash].js",
        chunkFileNames: "js/[name]-[hash].js",
        assetFileNames: (assetInfo) => {
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
        // ! Must split inversify.config.ts because MOUNT runtime in production causing timing issues
        manualChunks(id) {
          if (id.includes("inversify.config.ts")) {
            return "vendor";
          }
        }
      }
    },
    minify: "terser",
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
        passes: 3,
        ecma: 2020,
        pure_funcs: [
          "console.info",
          "console.debug",
          "console.warn",
          "console.error",
          "console.log",
          "console.table",
          "console.group",
          "console.groupEnd",
          "console.time",
          "console.timeEnd"
        ],
        module: true,
        toplevel: true,
        unsafe: true,
        unsafe_arrows: true,
        unsafe_methods: true,
        unsafe_proto: true,
        unsafe_regexp: true,
        unsafe_undefined: true,
      },
      format: {
        comments: false,
        shebang: false,
      },
      mangle: {
        toplevel: true,
      },
      keep_classnames: false, // Set to true if you need class names for DI
      keep_fnames: false, // Set to true if you need function names for debugging
    }
  }
}));
