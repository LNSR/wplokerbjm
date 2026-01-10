import { APIService } from '@/services/APIService'
import { routeStore } from '$lib/stores/Route.svelte'
import type { HeadData } from '@/types'
import { SvelteSet, SvelteMap, SvelteDate } from 'svelte/reactivity'

/**
 * SEO Service to manage RankMath head data
 */
export class SEOUtils {
    private headDataCache = $state(new SvelteMap<string, { data: HeadData; timestamp: number }>());
    private addedJobPostingIds = $state(new SvelteSet<number>());
    public initialSchemaSSR: boolean = true;
    private pendingJobIds = $state(new SvelteSet<number>());
    private processTimeout: number | null = null;

    async fetchHeadData(path: string): Promise<HeadData | null> {
        // Check cache first
        const cached = this.headDataCache.get(path);
        if (cached && (SvelteDate.now() - cached.timestamp) < 1 * 60 * 2000) { // 2 minutes cache
            this.updateHead(cached.data);
            return cached.data;
        }

        try {
            const fullUrl = `${routeStore.currentUrl.origin}${path}`;
            const response = await APIService.getRankMathHead(fullUrl);
            const head = this.parseHeadHtml(response.head);
            this.headDataCache.set(path, { data: head, timestamp: SvelteDate.now() });
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
    private parseHeadHtml(headHtml: string): HeadData {
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
                    case 'keywords':
                        headData.keywords = content
                        break
                    case 'author':
                        headData.author = content
                        break
                    // Webmaster verification tags
                    case 'google-site-verification':
                        headData.google_verify = content
                        break
                    case 'msvalidate.01':
                        headData.bing_verify = content
                        break
                    case 'baidu-site-verification':
                        headData.baidu_verify = content
                        break
                    case 'yandex-verification':
                        headData.yandex_verify = content
                        break
                    case 'p:domain_verify':
                        headData.pinterest_verify = content
                        break
                    case 'norton-safeweb-site-verification':
                        headData.norton_verify = content
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
                    case 'og:image:secure_url':
                        headData.og_image_secure_url = content
                        break
                    case 'og:image:width':
                        headData.og_image_width = content
                        break
                    case 'og:image:height':
                        headData.og_image_height = content
                        break
                    case 'og:image:alt':
                        headData.og_image_alt = content
                        break
                    case 'og:image:type':
                        headData.og_image_type = content
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
                    // Twitter App Card fields
                    case 'twitter:app:name:iphone':
                        headData.twitter_app_name_iphone = content
                        break
                    case 'twitter:app:id:iphone':
                        headData.twitter_app_id_iphone = content
                        break
                    case 'twitter:app:url:iphone':
                        headData.twitter_app_url_iphone = content
                        break
                    case 'twitter:app:name:ipad':
                        headData.twitter_app_name_ipad = content
                        break
                    case 'twitter:app:id:ipad':
                        headData.twitter_app_id_ipad = content
                        break
                    case 'twitter:app:url:ipad':
                        headData.twitter_app_url_ipad = content
                        break
                    case 'twitter:app:name:googleplay':
                        headData.twitter_app_name_googleplay = content
                        break
                    case 'twitter:app:id:googleplay':
                        headData.twitter_app_id_googleplay = content
                        break
                    case 'twitter:app:url:googleplay':
                        headData.twitter_app_url_googleplay = content
                        break
                    case 'twitter:app:description':
                        headData.twitter_app_description = content
                        break
                    case 'twitter:app:country':
                        headData.twitter_app_country = content
                        break
                    // Twitter Player Card fields
                    case 'twitter:player':
                        headData.twitter_player = content
                        break
                    case 'twitter:player:width':
                        headData.twitter_player_width = content
                        break
                    case 'twitter:player:height':
                        headData.twitter_player_height = content
                        break
                    case 'twitter:player:stream':
                        headData.twitter_player_stream = content
                        break
                    case 'twitter:player:stream:content_type':
                        headData.twitter_player_stream_content_type = content
                        break
                    case 'fb:app_id':
                        headData.fb_app_id = content
                        break
                    case 'fb:admins':
                        headData.fb_admins = content
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

    private updateHead(headData: HeadData) {
        if (!headData) return;

        // Update title
        if (headData.title) {
            document.title = headData.title;
        }

        // Update meta description
        this.updateMetaTag('name', 'description', headData.description);

        // Update keywords meta
        this.updateMetaTag('name', 'keywords', headData.keywords);

        // Update author meta
        this.updateMetaTag('name', 'author', headData.author);

        // Update webmaster verification tags
        this.updateMetaTag('name', 'google-site-verification', headData.google_verify);
        this.updateMetaTag('name', 'msvalidate.01', headData.bing_verify);
        this.updateMetaTag('name', 'baidu-site-verification', headData.baidu_verify);
        this.updateMetaTag('name', 'yandex-verification', headData.yandex_verify);
        this.updateMetaTag('name', 'p:domain_verify', headData.pinterest_verify);
        this.updateMetaTag('name', 'norton-safeweb-site-verification', headData.norton_verify);

        // Update canonical link
        this.updateCanonicalLink(headData.canonical);

        // Update robots meta
        this.updateMetaTag('name', 'robots', headData.robots);

        // Update Open Graph meta tags
        this.updateMetaTag('property', 'og:title', headData.og_title);
        this.updateMetaTag('property', 'og:description', headData.og_description);
        this.updateMetaTag('property', 'og:image', headData.og_image);
        this.updateMetaTag('property', 'og:image:secure_url', headData.og_image_secure_url);
        this.updateMetaTag('property', 'og:image:width', headData.og_image_width);
        this.updateMetaTag('property', 'og:image:height', headData.og_image_height);
        this.updateMetaTag('property', 'og:image:alt', headData.og_image_alt);
        this.updateMetaTag('property', 'og:image:type', headData.og_image_type);
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
        // Twitter App Card fields
        this.updateMetaTag('name', 'twitter:app:name:iphone', headData.twitter_app_name_iphone);
        this.updateMetaTag('name', 'twitter:app:id:iphone', headData.twitter_app_id_iphone);
        this.updateMetaTag('name', 'twitter:app:url:iphone', headData.twitter_app_url_iphone);
        this.updateMetaTag('name', 'twitter:app:name:ipad', headData.twitter_app_name_ipad);
        this.updateMetaTag('name', 'twitter:app:id:ipad', headData.twitter_app_id_ipad);
        this.updateMetaTag('name', 'twitter:app:url:ipad', headData.twitter_app_url_ipad);
        this.updateMetaTag('name', 'twitter:app:name:googleplay', headData.twitter_app_name_googleplay);
        this.updateMetaTag('name', 'twitter:app:id:googleplay', headData.twitter_app_id_googleplay);
        this.updateMetaTag('name', 'twitter:app:url:googleplay', headData.twitter_app_url_googleplay);
        this.updateMetaTag('name', 'twitter:app:description', headData.twitter_app_description);
        this.updateMetaTag('name', 'twitter:app:country', headData.twitter_app_country);
        // Twitter Player Card fields
        this.updateMetaTag('name', 'twitter:player', headData.twitter_player);
        this.updateMetaTag('name', 'twitter:player:width', headData.twitter_player_width);
        this.updateMetaTag('name', 'twitter:player:height', headData.twitter_player_height);
        this.updateMetaTag('name', 'twitter:player:stream', headData.twitter_player_stream);
        this.updateMetaTag('name', 'twitter:player:stream:content_type', headData.twitter_player_stream_content_type);

        // Update Facebook meta tags
        this.updateMetaTag('property', 'fb:app_id', headData.fb_app_id);
        this.updateMetaTag('property', 'fb:admins', headData.fb_admins);

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

    private updateMetaTag(attrName: string, attrValue: string, content?: string) {
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

    private updateCanonicalLink(href?: string) {
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

    private updateSchemaMarkup(schema: any) {
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

    /**
     * Custom madeup schema for JobPosting JSON-LD
     * Add JobPosting JSON-LD script tags without duplicating
     */
    public addJobPostingJsonLd(jobIds: number[]): void {
        jobIds.forEach(id => this.pendingJobIds.add(id));

        if (this.processTimeout) clearTimeout(this.processTimeout);

        this.processTimeout = setTimeout(async () => {
            const ids = [...this.pendingJobIds];
            this.pendingJobIds.clear();
            await processJobSchemas(ids);
        }, 1000);

        const processJobSchemas = async (jobIds: number[]): Promise<void> => {
            if (jobIds.length === 0) return;

            // Filter out jobIds that already have JobPosting scripts to avoid fetching duplicates
            jobIds = jobIds.filter(id => !this.addedJobPostingIds.has(id));

            // Limit to maximum 27 JobPosting scripts on DOM
            const maxAllowed = 27;
            const remainingSlots = maxAllowed - this.addedJobPostingIds.size;
            jobIds = jobIds.slice(0, remainingSlots);

            if (jobIds.length === 0) return;

            let schemas: Record<string, any>[] = [];

            try {
                if (!this.initialSchemaSSR) {
                    schemas = await APIService.fetchJobSchemas(jobIds);
                }

                if (schemas.length === 0) return;

                // Create script elements, but only if not already present
                schemas.forEach((schema, index) => {
                    const postId = jobIds[index];
                    if (!postId || !schema) return;

                    // Check if script already exists (additional safeguard)
                    const existingScript = document.querySelector(`script[data-ld-id="jobposting-${postId}"]`);
                    if (existingScript) return;

                    const script = document.createElement('script');
                    script.type = 'application/ld+json';
                    script.setAttribute('data-ld-type', 'JobPosting');
                    script.setAttribute('data-ld-id', `jobposting-${postId}`);
                    script.textContent = JSON.stringify(schema);

                    document.head.appendChild(script);
                    this.addedJobPostingIds.add(postId);
                });
            } catch (e) {
                console.warn('Failed to add JobPosting JSON-LD', e);
            }
        };
    }

    /**
     * Remove custom JobPosting JSON-LD.
     */
    public removeJobPostingJsonLd(): number {
        try {
            let removed = 0;

            if (typeof document === 'undefined') return removed;

            const explicit = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="JobPosting"]'));
            explicit.forEach(s => {
                const idAttr = s.getAttribute('data-ld-id');
                if (idAttr && idAttr.startsWith('jobposting-')) {
                    const id = parseInt(idAttr.replace('jobposting-', ''), 10);
                    if (!isNaN(id)) {
                        this.addedJobPostingIds.delete(id);
                    }
                }
                s.remove();
                removed++;
            });

            this.initialSchemaSSR = false;

            return removed;
        } catch (e) {
            console.warn(`Failed to remove JobPosting JSON-LD`, e);
            return 0;
        }
    }

    /**
     * Clear pending job schema requests.
     */
    public clearPendingJobSchemas(): void {
        if (this.processTimeout) {
            clearTimeout(this.processTimeout);
            this.processTimeout = null;
        }
        this.pendingJobIds.clear();
    }
}

export const utilsSEO = new SEOUtils();