import devtoolsJson from "vite-plugin-devtools-json";
import tailwindcss from "@tailwindcss/vite";
import { sveltekit } from "@sveltejs/kit/vite";
import type { ConfigEnv, ServerOptions, UserConfig } from "vite";
import { defineConfig } from "vite";
import fs from "fs";
import { resolve } from "path";
import { analyzer, unstableRolldownAdapter } from "vite-bundle-analyzer";
import { partytownVite, copyLibFiles } from "@qwik.dev/partytown/utils";

export default defineConfig((configEnv: ConfigEnv): UserConfig => {
  const isDev = configEnv.mode === "development" || configEnv.mode === "preview";

  const devServer: ServerOptions | undefined = isDev
    ? {
      host: true,
      https:
        fs.existsSync("../../../../certs/localhost.key") && fs.existsSync("../../../../certs/localhost.crt")
          ? {
            key: fs.readFileSync("../../../../certs/localhost.key"),
            cert: fs.readFileSync("../../../../certs/localhost.crt"),
          }
          : undefined,
    }
    : undefined;

  const plugins = [
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
    plugins,
    server: devServer,
  };
});
