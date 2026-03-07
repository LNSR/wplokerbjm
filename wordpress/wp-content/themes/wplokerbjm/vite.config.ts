import devtoolsJson from "vite-plugin-devtools-json";
import tailwindcss from "@tailwindcss/vite";
import { sveltekit } from "@sveltejs/kit/vite";
import { defineConfig } from "vite";
import fs from "fs";
import { resolve } from "path";
import { analyzer, unstableRolldownAdapter } from "vite-bundle-analyzer";
import { partytownVite, copyLibFiles } from "@qwik.dev/partytown/utils";

export default defineConfig(({ mode }) => {
  const isDev = mode === "development";

  return {
    plugins: [
      tailwindcss(),
      sveltekit(),
      devtoolsJson(),
      partytownVite({
        dest: resolve(__dirname, "static", "~partytown"),
      }),
      unstableRolldownAdapter(
        analyzer({
          fileName: "stats",
          openAnalyzer: false,
          analyzerMode: "static",
        }),
      ),
      {
        name: 'copy-partytown-assets',
        async closeBundle() {
          try {
            const dest = resolve(__dirname, '.svelte-kit', 'cloudflare', '~partytown');
            await copyLibFiles(dest);
          } catch (e) {
            console.warn('failed to copy Partytown assets to cloudflare dir', e);
          }
        }
      }
    ],
    ...(isDev ? {
      server: (() => {
        const server: any = { host: true };
        const keyPath = "../../../../certs/localhost.key";
        const certPath = "../../../../certs/localhost.crt";
        if (fs.existsSync(keyPath) && fs.existsSync(certPath)) {
          server.https = {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
          };
        }
        return server;
      })()
    } : undefined),
  };
});
