import devtoolsJson from "vite-plugin-devtools-json";
import UnpluginTypia from '@typia/unplugin/vite'
import tailwindcss from "@tailwindcss/vite";
import { sveltekit } from "@sveltejs/kit/vite";
import { type ConfigEnv, type UserConfig, type Plugin, type LibraryFormats, type ResolvedConfig } from "vite";
import { defineConfig, build as viteBuild } from "vite";
import fs from "fs";
import { resolve } from "path";
import { analyzer } from "vite-bundle-analyzer";
import { partytownVite, copyLibFiles } from "@qwik.dev/partytown/utils";

const cloudflareSecurityHeaders = /*txt*/`
# === START CUSTOM SECURITY HEADERS ===

/~partytown/*
  Service-Worker-Allowed: /
  Cross-Origin-Opener-Policy: same-origin
  Cross-Origin-Embedder-Policy: credentialless

/_app/immutable/workers/*
  Service-Worker-Allowed: /
  Cross-Origin-Opener-Policy: same-origin
  Cross-Origin-Embedder-Policy: credentialless
# === END CUSTOM SECURITY HEADERS ===
`;

function writeCloudflareSecurityHeaders(): void
{
  const headersFile = resolve(__dirname, ".svelte-kit", "cloudflare", "_headers");
  const existing = fs.existsSync(headersFile) ? fs.readFileSync(headersFile, "utf8") : "";

  if (existing.includes("START CUSTOM SECURITY HEADERS")) return;

  fs.writeFileSync(headersFile, `${existing.trimEnd()}\n${cloudflareSecurityHeaders}`);
}

function transformInlinedScript(format: LibraryFormats = "iife"): Plugin
{
  let root: ResolvedConfig[ 'root' ] = process.cwd();
  let mode: ResolvedConfig[ 'mode' ] = "production";
  let command: ResolvedConfig[ 'command' ] = "build";
  let resolveConfig: UserConfig[ "resolve" ];

  return {
    name: "transform-inlined-script",
    enforce: "pre",

    configResolved(config)
    {
      root = config.root;
      mode = config.mode;
      command = config.command;
      resolveConfig = config.resolve;
    },

    async load(id)
    {
      if (!id.endsWith("?inline-script")) return null;

      const entry = id.slice(0, -"?inline-script".length);

      this.addWatchFile(entry);

      const result = await viteBuild({
        root,
        mode,
        configFile: false,
        publicDir: false,
        logLevel: "warn", // Toned down to avoid flooding the console on every edit
        envPrefix: [ "VITE_", "PUBLIC_" ],

        resolve: {
          alias: resolveConfig?.alias,
          conditions: resolveConfig?.conditions,
          extensions: resolveConfig?.extensions,
          mainFields: resolveConfig?.mainFields,
        },

        build: {
          write: false,
          emptyOutDir: false,
          target: "esnext",
          sourcemap: false,
          minify: "oxc",

          lib: {
            entry,
            formats: [ format ],
            name: "__inline_script__",
            fileName: "inline-script",
          },

          rolldownOptions: {
            output: {
              exports: "none",
            },
          },
        },
      });

      // Vite's build returns an array or a single output object depending on configuration
      const buildOutput = Array.isArray(result) ? result[ 0 ] : result;
      const output = buildOutput && 'output' in buildOutput ? buildOutput.output : null;

      if (!output) return null;

      const chunk = output.find(
        (item): item is Extract<typeof item, { type: "chunk" }> =>
          item.type === "chunk",
      );

      // Extract dependencies found during the sub-build and register them to the main watcher
      if (chunk && chunk.modules)
      {
        Object.keys(chunk.modules).forEach((modulePath) =>
        {
          if (!modulePath.includes('\0') && fs.existsSync(modulePath))
          {
            this.addWatchFile(resolve(modulePath));
          }
        });
      }

      return {
        code: `export default ${JSON.stringify(chunk?.code || "")};`,
        map: null,
      };
    },
  };
}
export default defineConfig((configEnv: ConfigEnv): UserConfig =>
{
  const isDev = configEnv.mode === "development" || configEnv.mode === "preview";

  const devServer: UserConfig[ "server" ] = isDev
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
      },
      headers: {
        'Cross-Origin-Opener-Policy': 'same-origin',
        'Cross-Origin-Embedder-Policy': 'credentialless',
      },
    }
    : undefined;

  const plugins: UserConfig[ "plugins" ] = [
    tailwindcss({
      optimize: {
        minify: true,
      },
    }),
    transformInlinedScript(),
    sveltekit(),
    UnpluginTypia({
      cache: true,
      log: true,
    }),
    devtoolsJson(),
    partytownVite({
      dest: resolve(__dirname, "public", "~partytown"),
    }),
    analyzer({
      enabled: false,
      fileName: "stats",
      openAnalyzer: false,
      analyzerMode: "static",
    }),
    {
      name: "copy-partytown-assets",
      async closeBundle()
      {
        const dest = resolve(__dirname, ".svelte-kit", "cloudflare", "~partytown");
        try
        {
          await copyLibFiles(dest);
        } catch (error)
        {
          console.warn("failed to copy Partytown assets to cloudflare dir", error);
        }

        try
        {
          writeCloudflareSecurityHeaders();
        } catch (error)
        {
          console.warn("failed to write Cloudflare security headers", error);
        }
      },
    },
  ];

  const build: UserConfig[ "build" ] = {
    minify: "oxc",
    modulePreload: {
      polyfill: false,
    },
    target: "esnext",
    sourcemap: false,
    rolldownOptions: {
      output: {
        codeSplitting: true,
      },
    },
  };

  const worker: UserConfig[ 'worker' ] = {
    format: "es",
    plugins: () => [
      transformInlinedScript(),
      sveltekit(),
      UnpluginTypia({
        cache: true,
        log: true,
      }),
    ],
    rolldownOptions: {
      output: {
        codeSplitting: true,
      },
    },
  };

  return {
    plugins,
    server: devServer,
    build,
    worker,
    envPrefix: [ "VITE_", "PUBLIC_" ],
  };
});
