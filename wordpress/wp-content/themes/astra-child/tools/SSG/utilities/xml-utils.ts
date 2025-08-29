import { XMLParser } from 'fast-xml-parser';
import https from 'https';
import http from 'http';

export interface SitemapUrl {
  loc: string;
  lastmod?: string;
  changefreq?: string;
  priority?: string;
}

export class XmlFetcher {
  static async fetch(url: string): Promise<string> {
    return new Promise((resolve, reject) => {
      const protocol = url.startsWith('https://') ? https : http;

      const request = protocol.get(url, (res) => {
        // Check for successful response
        if (res.statusCode && res.statusCode >= 200 && res.statusCode < 300) {
          let data = '';

          // Set encoding to utf8
          res.setEncoding('utf8');

          res.on('data', (chunk) => {
            data += chunk;
          });

          res.on('end', () => {
            console.log(`Fetched ${data.length} characters from ${url}`);
            resolve(data);
          });
        } else {
          reject(new Error(`HTTP ${res.statusCode}: ${res.statusMessage}`));
        }
      });

      request.on('error', (err) => {
        reject(err);
      });

      // Set timeout
      request.setTimeout(30000, () => {
        request.destroy();
        reject(new Error('Request timeout'));
      });
    });
  }
}

export class SitemapParser {
  private parser: XMLParser;

  constructor() {
    this.parser = new XMLParser({
      ignoreAttributes: false,
      attributeNamePrefix: '@_'
    });
  }

  parseSitemap(xmlContent: string): SitemapUrl[] {
    const parsed = this.parser.parse(xmlContent);
    return this.extractUrlsFromParsedXml(parsed);
  }

  private extractUrlsFromParsedXml(parsed: any): SitemapUrl[] {
    const urls: SitemapUrl[] = [];

    // Check if this is a sitemap index
    if (parsed.sitemapindex) {
      const sitemaps = Array.isArray(parsed.sitemapindex.sitemap)
        ? parsed.sitemapindex.sitemap
        : [parsed.sitemapindex.sitemap];

      console.log(`Found ${sitemaps.length} sitemap references in index`);

      // For sitemap index, we return empty array here and handle recursion in parseSitemapUrl
      return urls;
    }

    // This is a regular sitemap with urlset
    if (parsed.urlset && parsed.urlset.url) {
      const urlEntries = Array.isArray(parsed.urlset.url)
        ? parsed.urlset.url
        : [parsed.urlset.url];

      console.log(`Found ${urlEntries.length} URLs in sitemap`);

      for (const urlEntry of urlEntries) {
        if (urlEntry.loc) {
          urls.push({
            loc: urlEntry.loc,
            lastmod: urlEntry.lastmod,
            changefreq: urlEntry.changefreq,
            priority: urlEntry.priority
          });
        }
      }
    }

    return urls;
  }

  async parseSitemapUrl(sitemapUrl: string): Promise<SitemapUrl[]> {
    try {
      if (sitemapUrl.startsWith('http')) {
        console.log(`Fetching remote sitemap: ${sitemapUrl}`);
        const xmlContent = await XmlFetcher.fetch(sitemapUrl);

        console.log(`Parsing XML content (first 200 chars): ${xmlContent.substring(0, 200)}`);

        const parsed = this.parser.parse(xmlContent);
        const urls: SitemapUrl[] = [];

        // Check if this is a sitemap index
        if (parsed.sitemapindex) {
          const sitemaps = Array.isArray(parsed.sitemapindex.sitemap)
            ? parsed.sitemapindex.sitemap
            : [parsed.sitemapindex.sitemap];

          console.log(`Found ${sitemaps.length} sitemap references in index`);

          for (const sitemap of sitemaps) {
            if (sitemap.loc) {
              console.log(`Found sitemap reference: ${sitemap.loc}`);
              // Recursively parse the referenced sitemap
              const subUrls = await this.parseSitemapUrl(sitemap.loc);
              urls.push(...subUrls);
            }
          }
        } else if (parsed.urlset && parsed.urlset.url) {
          // This is a regular sitemap with urlset
          const urlEntries = Array.isArray(parsed.urlset.url)
            ? parsed.urlset.url
            : [parsed.urlset.url];

          console.log(`Found ${urlEntries.length} URLs in sitemap`);

          for (const urlEntry of urlEntries) {
            if (urlEntry.loc) {
              urls.push({
                loc: urlEntry.loc,
                lastmod: urlEntry.lastmod,
                changefreq: urlEntry.changefreq,
                priority: urlEntry.priority
              });
            }
          }
        }

        console.log(`Returning ${urls.length} URLs from ${sitemapUrl}`);
        return urls;
      } else {
        // Local file - this would need to be implemented if needed
        throw new Error('Local file parsing not implemented in SitemapParser');
      }
    } catch (error) {
      console.error(`Error parsing sitemap URL ${sitemapUrl}:`, error);
      return [];
    }
  }
}

// Convenience functions
export async function parseSitemapFromUrl(url: string): Promise<SitemapUrl[]> {
  const parser = new SitemapParser();
  return parser.parseSitemapUrl(url);
}

export function parseSitemapFromXml(xmlContent: string): SitemapUrl[] {
  const parser = new SitemapParser();
  return parser.parseSitemap(xmlContent);
}