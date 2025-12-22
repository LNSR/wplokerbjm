import { APIService } from '@/services/APIService'
import type { HeadData } from '@/types'

/**
 * SEO Service to manage RankMath head data
 */
export class SEOService {


    static async fetchHeadData(path: string): Promise<HeadData | null> {
        // Prevent fetch if hostname is localhost or an IP address
        // RankMath API wont work in these hostnames
        const hostname = window.location.hostname;
        if (hostname === 'localhost' || hostname.match(/^\d+\.\d+\.\d+\.\d+$/)) {
            return null;
        }

        try {
            const fullUrl = `${window.location.origin}${path}`;
            const response = await APIService.getRankMathHead(fullUrl);
            const head = this.parseHeadHtml(response.head);
            this.updateHead(head);
            return head;
        } catch (error) {
            console.warn('Failed to fetch RankMath head data:', error);
            return null;
        }
    }

    /**
     * Parse RankMath HTML head string into structured data
     */
    private static parseHeadHtml(headHtml: string): HeadData {
        const headData: HeadData = {}

        // Create a temporary DOM element to parse the HTML
        const tempDiv = document.createElement('div')
        tempDiv.innerHTML = headHtml

        // Extract title
        const titleTag = tempDiv.querySelector('title')
        if (titleTag) {
            headData.title = titleTag.textContent || undefined
        }

        // Extract meta tags
        const metaTags = tempDiv.querySelectorAll('meta')
        metaTags.forEach(meta => {
            const name = meta.getAttribute('name') || meta.getAttribute('property')
            const content = meta.getAttribute('content')

            if (name && content) {
                switch (name) {
                    case 'description':
                        headData.description = content
                        break
                    case 'robots':
                        headData.robots = content
                        break
                    case 'author':
                        headData.author = content
                        break
                    case 'og:title':
                        headData.og_title = content
                        break
                    case 'og:description':
                        headData.og_description = content
                        break
                    case 'og:image':
                        headData.og_image = content
                        break
                    case 'og:locale':
                        headData.og_locale = content
                        break
                    case 'og:type':
                        headData.og_type = content
                        break
                    case 'og:url':
                        headData.og_url = content
                        break
                    case 'og:site_name':
                        headData.og_site_name = content
                        break
                    case 'article:publisher':
                        headData.article_publisher = content
                        break
                    case 'og:updated_time':
                        headData.og_updated_time = content
                        break
                    case 'og:video':
                        headData.og_video = content
                        break
                    case 'og:audio':
                        headData.og_audio = content
                        break
                    case 'og:determiner':
                        headData.og_determiner = content
                        break
                    case 'twitter:title':
                        headData.twitter_title = content
                        break
                    case 'twitter:description':
                        headData.twitter_description = content
                        break
                    case 'twitter:image':
                        headData.twitter_image = content
                        break
                    case 'twitter:card':
                        headData.twitter_card = content
                        break
                    case 'twitter:label1':
                        headData.twitter_label1 = content
                        break
                    case 'twitter:data1':
                        headData.twitter_data1 = content
                        break
                    case 'twitter:label2':
                        headData.twitter_label2 = content
                        break
                    case 'twitter:data2':
                        headData.twitter_data2 = content
                        break
                    case 'twitter:site':
                        headData.twitter_site = content
                        break
                    case 'twitter:creator':
                        headData.twitter_creator = content
                        break
                    case 'fb:app_id':
                        headData.fb_app_id = content
                        break
                    case 'article:author':
                        headData.article_author = content
                        break
                    case 'article:published_time':
                        headData.article_published_time = content
                        break
                    case 'article:modified_time':
                        headData.article_modified_time = content
                        break
                    case 'article:section':
                        headData.article_section = content
                        break
                    case 'article:tag':
                        headData.article_tag = content
                        break
                }
            }
        })

        // Extract canonical link
        const canonicalLink = tempDiv.querySelector('link[rel="canonical"]')
        if (canonicalLink) {
            headData.canonical = canonicalLink.getAttribute('href') || undefined
        }

        // Extract schema markup if present
        const schemaScripts = tempDiv.querySelectorAll('script[type="application/ld+json"]')
        if (schemaScripts.length > 0) {
            try {
                // For simplicity, take the first schema script
                const schemaText = schemaScripts[0].textContent
                if (schemaText) {
                    headData.schema = JSON.parse(schemaText)
                }
            } catch (e) {
                console.warn('Failed to parse schema markup:', e)
            }
        }

        return headData
    }

    static updateHead(headData: HeadData) {
        if (!headData) return;

        // Update title
        if (headData.title) {
            document.title = headData.title;
        }

        // Update meta description
        this.updateMetaTag('name', 'description', headData.description);

        // Update author meta
        this.updateMetaTag('name', 'author', headData.author);

        // Update canonical link
        this.updateCanonicalLink(headData.canonical);

        // Update robots meta
        this.updateMetaTag('name', 'robots', headData.robots);

        // Update Open Graph meta tags
        this.updateMetaTag('property', 'og:title', headData.og_title);
        this.updateMetaTag('property', 'og:description', headData.og_description);
        this.updateMetaTag('property', 'og:image', headData.og_image);
        this.updateMetaTag('property', 'og:locale', headData.og_locale);
        this.updateMetaTag('property', 'og:type', headData.og_type);
        this.updateMetaTag('property', 'og:url', headData.og_url);
        this.updateMetaTag('property', 'og:site_name', headData.og_site_name);
        this.updateMetaTag('property', 'article:publisher', headData.article_publisher);
        this.updateMetaTag('property', 'og:updated_time', headData.og_updated_time);
        this.updateMetaTag('property', 'og:video', headData.og_video);
        this.updateMetaTag('property', 'og:audio', headData.og_audio);
        this.updateMetaTag('property', 'og:determiner', headData.og_determiner);

        // Update Twitter meta tags
        this.updateMetaTag('name', 'twitter:title', headData.twitter_title);
        this.updateMetaTag('name', 'twitter:description', headData.twitter_description);
        this.updateMetaTag('name', 'twitter:image', headData.twitter_image);
        this.updateMetaTag('name', 'twitter:card', headData.twitter_card);
        this.updateMetaTag('name', 'twitter:label1', headData.twitter_label1);
        this.updateMetaTag('name', 'twitter:data1', headData.twitter_data1);
        this.updateMetaTag('name', 'twitter:label2', headData.twitter_label2);
        this.updateMetaTag('name', 'twitter:data2', headData.twitter_data2);
        this.updateMetaTag('name', 'twitter:site', headData.twitter_site);
        this.updateMetaTag('name', 'twitter:creator', headData.twitter_creator);

        // Update Facebook meta tags
        this.updateMetaTag('property', 'fb:app_id', headData.fb_app_id);

        // Update Article meta tags
        this.updateMetaTag('property', 'article:author', headData.article_author);
        this.updateMetaTag('property', 'article:published_time', headData.article_published_time);
        this.updateMetaTag('property', 'article:modified_time', headData.article_modified_time);
        this.updateMetaTag('property', 'article:section', headData.article_section);
        this.updateMetaTag('property', 'article:tag', headData.article_tag);

        // Update schema markup if present
        if (headData.schema) {
            this.updateSchemaMarkup(headData.schema);
        } else {
            const existingSchemas = document.querySelectorAll('script[type="application/ld+json"]:not([data-ld-type="JobPosting"]):not([data-ld-id^="jobposting-"])');
            existingSchemas.forEach(script => script.remove());
        }
    }

    private static updateMetaTag(attrName: string, attrValue: string, content?: string) {
        if (!content) {
            const existingMeta = document.querySelector(`meta[${attrName}="${attrValue}"]`);
            if (existingMeta) existingMeta.remove();
            return;
        }

        let meta = document.querySelector(`meta[${attrName}="${attrValue}"]`) as HTMLMetaElement;
        if (meta) {
            meta.content = content;
        } else {
            meta = document.createElement('meta');
            meta.setAttribute(attrName, attrValue);
            meta.content = content;
            document.head.appendChild(meta);
        }
    }

    private static updateCanonicalLink(href?: string) {
        if (!href) {
            const existingLink = document.querySelector('link[rel="canonical"]');
            if (existingLink) existingLink.remove();
            return;
        }

        let link = document.querySelector('link[rel="canonical"]') as HTMLLinkElement;
        if (link) {
            link.href = href;
        } else {
            link = document.createElement('link');
            link.rel = 'canonical';
            link.href = href;
            document.head.appendChild(link);
        }
    }

    private static updateSchemaMarkup(schema: any) {
        const existingSchemas = document.querySelectorAll('script[type="application/ld+json"]:not([data-ld-type="JobPosting"]):not([data-ld-id^="jobposting-"])');
        existingSchemas.forEach(script => script.remove());

        // Add new schema script
        if (schema) {
            const script = document.createElement('script');
            script.type = 'application/ld+json';
            script.textContent = JSON.stringify(schema);
            document.head.appendChild(script);
        }
    }
}