import fs from 'fs';

export function generateCaddyEarlyHints(options: { manifestPath: string; outputPath: string }) {
  return {
    name: 'generate-caddy-early-hints',
    writeBundle() {
      const manifestPath = options.manifestPath;
      if (!fs.existsSync(manifestPath)) return;
      const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));

      const isProd = process.env.WP_ENV === 'production';

      const routes = [
        { path: '/', key: 'src/app/routes/Homepage.svelte', name: 'homepage' },
        { path: '/pasang-iklan-loker', key: 'src/app/routes/PasangIklanLoker.svelte', name: 'pasang' },
        { path: '/lowongan/', key: 'src/app/routes/SingleLowongan.svelte', regex: true, name: 'single' }
      ];

      let entryKey: string | undefined;
      for (const [k, v] of Object.entries(manifest)) {
        if ((v as any).isEntry) {
          entryKey = k;
          break;
        }
      }

      let caddyConfig = '';

      for (const route of routes) {
        const matcherName = route.name;
        const itIsProd = "&& {env.WP_ENV} == 'production'";
        const matcher = route.regex
          ? `expression {http.request.uri.path}.matches("^${route.path}.*") ${itIsProd}`
          : `expression {http.request.uri.path} == "${route.path}" ${itIsProd}`;

        caddyConfig += `@${matcherName} ${matcher}\n`;

        let urls: string[] = [];
        const distUri = '/wp-content/themes/wplokerbjm/assets/dist/';

        if (entryKey) {
          urls.push(...generateCaddyEarlyHintsPlugin.getAllTransitiveAssets(manifest, entryKey).map((u: string) => distUri + u));
        }
        urls.push(...generateCaddyEarlyHintsPlugin.getAllTransitiveAssets(manifest, 'src/app.svelte').map((u: string) => distUri + u));
        if (manifest[route.key]) {
          urls.push(...generateCaddyEarlyHintsPlugin.getAllTransitiveAssets(manifest, route.key).map((u: string) => distUri + u));
        }
        urls = [...new Set(urls)];

        const linkParts: string[] = [];
        for (const url of urls) {
          const rel = url.endsWith('.js') ? 'modulepreload' : url.endsWith('.css') ? 'preload' : 'preload';
          const as = url.endsWith('.js') ? 'script' : url.endsWith('.css') ? 'style' : '';
          linkParts.push(`<${url}>; rel=${rel}; as=${as}; crossorigin`);
        }

        if (linkParts.length > 0) {
          if (isProd) {
            caddyConfig += `respond @${matcherName} 103 \n`;
            caddyConfig += `header @${matcherName} Link "${linkParts.join(', ')}"\n`;
          } else {
            caddyConfig += `header @${matcherName} Link "${linkParts.join(', ')}"\n`;
          }

          caddyConfig += `\n`;
        }
      }

      const outputPath = options.outputPath;
      fs.writeFileSync(outputPath, caddyConfig);
      console.log('Generated Caddy early hints config at', outputPath);
    }
  };
}

class generateCaddyEarlyHintsPlugin {
  static getAllTransitiveAssets(manifest: Record<string, any>, key: string, visited: string[] = []): string[] {
    if (visited.includes(key)) return [];
    visited.push(key);
    const assets: string[] = [];
    if (manifest[key]?.file) assets.push(manifest[key].file);
    if (manifest[key]?.css) assets.push(...manifest[key].css);
    if (manifest[key]?.imports) {
      for (const imp of manifest[key].imports) {
        assets.push(...generateCaddyEarlyHintsPlugin.getAllTransitiveAssets(manifest, imp, visited));
      }
    }
    return assets;
  }
}