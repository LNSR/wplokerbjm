import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'
import liveReload from 'vite-plugin-live-reload'
import Inspector from 'vite-plugin-vue-inspector'
import { compression, defineAlgorithm } from 'vite-plugin-compression2'
import { visualizer } from 'rollup-plugin-visualizer';
import { constants as zlibConstants } from 'zlib'
import fs from 'fs'
import path from 'path'

export default defineConfig(({ command }) => ({
  plugins: [
    vue(),
    tailwindcss(),
    liveReload([
      './**/*.php',
    ]),
    Inspector(),
    compression({
      algorithms: [
        defineAlgorithm('zstd', {
          params: {
            [zlibConstants.ZSTD_c_compressionLevel]: 19,
            [zlibConstants.ZSTD_c_nbWorkers]: 4,
            [zlibConstants.ZSTD_c_contentSizeFlag]: true,
            [zlibConstants.ZSTD_c_checksumFlag]: true,
          }
        }),
      ]
    }),
    visualizer({ open: true }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
      '@assets': resolve(__dirname, './assets'),
    }
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    cors: true,
    https: {
      key: fs.readFileSync(path.resolve(__dirname, '../../../localhost-key.pem')),
      cert: fs.readFileSync(path.resolve(__dirname, '../../../localhost.pem')),
    },
  },
  ...(command === 'build'
    ? { base: '/wp-content/themes/astra-child/assets/vue/dist/' }
    : {}),
  build: {
    outDir: './assets/vue/dist',
    emptyOutDir: true,
    sourcemap: false,
    manifest: true,
    rollupOptions: {
      input: {
        homepage: './src/homepage.ts',
        single: './src/single.ts',
      },
      output: {
        format: 'es',
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const assetName = assetInfo.names && assetInfo.names.length > 0 ? assetInfo.names[0] : '';
          if (assetName.endsWith('.css')) {
            return 'css/[name]-[hash][extname]'
          }
          if (
            assetName &&
            /\.(woff2?|ttf|otf|eot)$/.test(assetName)
          ) {
            return 'webfonts/[name]-[hash][extname]'
          }
          // images or other assets
          return 'assets/[name]-[hash][extname]'
        },
      },
    },
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
        passes: 10,
      },
      format: {
        comments: false,
      },
    },
  },
}))
