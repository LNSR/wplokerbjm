import devtoolsJson from "vite-plugin-devtools-json";
import UnpluginTypia from '@typia/unplugin/vite'
import tailwindcss from "@tailwindcss/vite";
import { sveltekit } from "@sveltejs/kit/vite";
import type { ConfigEnv, UserConfig } from "vite";
import { defineConfig } from "vite";
import fs from "fs";
import { resolve } from "path";
import { analyzer } from "vite-bundle-analyzer";
import { partytownVite, copyLibFiles } from "@qwik.dev/partytown/utils";

export default defineConfig((configEnv: ConfigEnv): UserConfig => {
  const isDev = configEnv.mode === "development" || configEnv.mode === "preview";


  const resolveFolder: UserConfig["resolve"] = {
    alias: {
      "@components": "src/lib/components",
      "@css": "src/lib/assets/css",
      "@": "src",
      "@@": "/",
    },
  }
  
  const devServer: UserConfig["server"] = isDev
    ? {
      host: true,
      https:
        fs.existsSync("../../../../certs/localhost.key") && fs.existsSync("../../../../certs/localhost.crt")
          ? {
            key: fs.readFileSync("../../../../certs/localhost.key"),
            cert: fs.readFileSync("../../../../certs/localhost.crt"),
          }
          : undefined,
      hmr: {
        port: 50001,
        clientPort: 50001,
      }
    }
    : undefined;

  const plugins: UserConfig["plugins"] = [
    UnpluginTypia({
      cache: true,
      log: true,
    }),
    tailwindcss(),
    sveltekit(),
    devtoolsJson(),
    partytownVite({
      dest: resolve(__dirname, "public", "~partytown"),
    }),
      analyzer({
        fileName: "stats",
        openAnalyzer: false,
        analyzerMode: "static",
      }),
    {
      name: "copy-partytown-assets",
      async closeBundle() {
        try {
          const dest = resolve(__dirname, ".svelte-kit", "cloudflare", "~partytown");
          await copyLibFiles(dest);
        } catch (error) {
          console.warn("failed to copy Partytown assets to cloudflare dir", error);
        }
      },
    },
  ];

  return {
    resolve: resolveFolder,
    plugins,
    server: devServer,
  };
});
