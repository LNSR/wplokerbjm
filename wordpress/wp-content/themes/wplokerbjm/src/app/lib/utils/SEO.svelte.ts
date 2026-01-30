import { APIService } from '@/services/APIService'
import { routeStore } from '$lib/stores/Route.svelte'
import type { HeadData } from '@/types'
import { SvelteSet, SvelteMap, SvelteDate } from 'svelte/reactivity'

/**
 * SEO Service to manage RankMath head data.
 *
 * Responsibilities:
 * - Fetch RankMath head HTML via GraphQL and parse it to a structured HeadData.
 * - Update document <head> elements (title, meta tags, canonical link, Open Graph/Twitter tags).
 * - Manage insertion/removal of JSON-LD schema scripts (JobPosting / ItemList).
 * - Prefer explicit SSR-provided schemas marked with data attributes; otherwise fetch via API.
 *
 * Note: Methods operate on the global document and are intended to run in a browser context.
 */
export class SEOUtils {
    private headDataCache = $state(new SvelteMap<string, { data: HeadData; timestamp: number }>());
    private addedJobPostingIds = $state(new SvelteSet<number>());
    public initialSchemaSSR: boolean = true;
    private headAbortController: AbortController | null = null;
    private schemaAbortController: AbortController | null = null;

    /**
     * Fetch RankMath head HTML for the given path and immediately update the document <head>.
     *
     * - Uses a short in-memory cache (2 minutes) keyed by path.
     * - Aborts any in-flight head fetch when a new request is initiated.
     * - Parses the returned HTML and applies changes via {@link updateHead}.
     *
     * @param path - Relative path (e.g. '/some-page') to fetch head data for
     * @returns Parsed HeadData on success, or null if aborted/failed
     */
    async fetchHeadData(path: string): Promise<HeadData | null> {
        // Abort any previous head data fetch
        if (this.headAbortController) {
            this.headAbortController.abort();
        }
        this.headAbortController = new AbortController();

        if (this.initialSchemaSSR) {
            this.initialSchemaSSR = false;
        }

        // Check cache first
        const cached = this.headDataCache.get(path);
        if (cached && (SvelteDate.now() - cached.timestamp) < 1 * 60 * 2000) { // 2 minutes cache
            this.updateHead(cached.data);
            return cached.data;
        }

        try {
            const fullUrl = `${routeStore.currentUrl.origin}${path}`;
            const response = await APIService.getRankMathHeadGraphQL(fullUrl, this.headAbortController.signal);
            const head = this.parseHeadHtml(response);
            this.headDataCache.set(path, { data: head, timestamp: SvelteDate.now() });
            this.updateHead(head);
            return head;
        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                // Request was aborted, ignore
                return null;
            }
            console.warn('Failed to fetch RankMath head data:', error);
            return null;
        }
    }

    /**
     * Parse RankMath HTML head string into structured data.
     *
     * Extracts title, meta tags (by name/property), canonical link, and the first
     * application/ld+json script (parsed as JSON).
     *
     * @param headHtml - Raw head HTML returned by RankMath
     * @returns Structured HeadData object (may contain schema JSON if present)
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

    /**
     * Apply parsed head data to the current document.
     *
     * Updates document title, canonical link, meta tags (description, robots, OpenGraph, Twitter, etc.),
     * and inserts/removes JSON-LD scripts as appropriate.
     *
     * @param headData - Parsed head data returned by {@link parseHeadHtml}
     */
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

        if (headData.schema) {
            // Heuristic: treat schemas containing @graph (or arrays with multiple
            // types) as vendor/RankMath bundles. Mark and insert them as
            // 'rank-math' so we don't collide with our custom ItemList/JobPosting.
            const schema = headData.schema;
            const looksLikeGraph = Boolean(schema && (schema['@graph'] || (Array.isArray(schema) && schema.some((s: any) => s && s['@type']))));
            if (looksLikeGraph) {
                this.updateSchemaMarkup(headData.schema, 'rank-math');
            } else {
                this.updateSchemaMarkup(headData.schema);
            }
        } else {
            const existingSchemas = document.querySelectorAll('script[type="application/ld+json"]:not([data-ld-type="JobPosting"]):not([data-ld-id^="jobposting-"]):not([data-ld-type="ItemList"]):not(.rank-math-schema)');
            existingSchemas.forEach(script => script.remove());
        }
    }

    /**
     * Update or remove a meta tag in the document head.
     *
     * If `content` is falsy this removes the meta tag; otherwise it upserts the meta tag
     * with the given attribute name/value (e.g. `name="description"` or `property="og:title"`).
     *
     * @param attrName - Attribute to match (`name` or `property`)
     * @param attrValue - Attribute value to match (e.g. 'description', 'og:title')
     * @param content - Meta content value; if omitted the meta is removed
     */
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

    /**
     * Update or remove the canonical link tag in the document head.
     *
     * @param href - Canonical URL to set. If omitted, the canonical link is removed.
     */
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

    /**
     * Insert or remove JSON-LD schema scripts in the document head.
     *
     * - If `dataLdType` is provided, remove scripts previously inserted with the same data type
     *   (e.g. 'ItemList' or 'JobPosting') to avoid duplicates.
     * - Always removes any JobPosting scripts previously created by this utility.
     *
     * @param schema - Schema object to insert. If falsy, matching scripts are removed.
     * @param dataLdType - Optional marker type used to label inserted scripts (set as `data-ld-type`).
     */
    private updateSchemaMarkup(schema: any, dataLdType?: string) {
        // Only remove scripts that we previously inserted or explicitly match
        // the data attributes we're managing. Do NOT touch other vendor
        // scripts (e.g., RankMath) that do not carry our GraphQL data attributes.
        try {
            if (typeof document === 'undefined') return;
            if (dataLdType) {
                // Remove only scripts we marked with the same data-ld-type
                const toRemove = Array.from(document.querySelectorAll(`script[type="application/ld+json"][data-ld-type="${dataLdType}"]`));
                toRemove.forEach(s => s.remove());
            }
            // Also remove any jobposting scripts we created (by data-ld-id prefix)
            const jobPostingRem = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-id^="jobposting-"]'));
            jobPostingRem.forEach(s => s.remove());
        } catch (e) {
            console.warn('Failed to update schema markup cleanup', e);
        }

        // Add new schema script
        if (schema) {
            const script = document.createElement('script');
            script.type = 'application/ld+json';
            if (dataLdType) script.setAttribute('data-ld-type', dataLdType);
            script.textContent = JSON.stringify(schema);
            document.head.appendChild(script);
        }
    }

    /**
     * Add a JobPosting JSON-LD script for a single job id.
     *
     * Behavior:
     * - When called with an empty array, removes all previously inserted JobPosting scripts and clears internal tracking.
     * - Supports only single-ID insertion; call with one ID to add or refresh the JobPosting script for that job.
     * - Prefers using an SSR-provided script (if present and explicitly marked) to avoid an unnecessary network fetch.
     *
     * @param jobIds - Array containing exactly one job ID to insert, or an empty array to remove existing JobPosting scripts.
     */
    public addJobPostingJsonLd(jobIds: number[] = []): void {
        // If called with empty array, remove all existing JobPosting scripts
        if (!jobIds || jobIds.length === 0) {
            try {
                if (typeof document === 'undefined') return;

                const explicit = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="JobPosting"]')) as HTMLScriptElement[];
                explicit.forEach(s => {
                    const idAttr = s.getAttribute('data-ld-id');
                    if (idAttr && idAttr.startsWith('jobposting-')) {
                        const id = parseInt(idAttr.replace('jobposting-', ''), 10);
                        if (!isNaN(id)) {
                            this.addedJobPostingIds.delete(id);
                        }
                    }
                    s.remove();
                });

                // Ensure we don't think SSR is still present after explicit removal
                this.initialSchemaSSR = false;
            } catch (e) {
                console.warn('Failed to remove JobPosting JSON-LD', e);
            }
            return;
        }

        // Only support single job ID insertion for JobPosting
        if (jobIds.length !== 1) return;

        const jobId = jobIds[0];

        // Remove any existing JobPosting scripts (we will replace)
        try {
            const existingAll = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="JobPosting"]')) as HTMLScriptElement[];
            existingAll.forEach(s => s.remove());
            this.addedJobPostingIds.clear();
        } catch {
            // ignore
        }

        // Abort any previous schema fetch
        if (this.schemaAbortController) {
            this.schemaAbortController.abort();
        }
        this.schemaAbortController = new AbortController();

        const addSchema = async (): Promise<void> => {
            try {
                let schema: any = null;

                if (this.initialSchemaSSR) {
                    // Prefer SSR-provided script if present; avoid unnecessary fetch
                    const existingScript = document.querySelector(`script[data-ld-id="jobposting-${jobId}"]`) as HTMLScriptElement | null;
                    if (existingScript && existingScript.textContent) {
                        try {
                            const parsed = JSON.parse(existingScript.textContent);
                            // Only accept if the SSR script explicitly declares JobPosting type
                            const isJobPosting = parsed && (parsed['@type'] === 'JobPosting' || (Array.isArray(parsed) && parsed.some((p: any) => p && p['@type'] === 'JobPosting')));
                            if (isJobPosting) {
                                // If parsed contains an identifier or id, ensure it matches the requested jobId
                                let matchesId = false;
                                try {
                                    const identifierVal = parsed['identifier'] && (parsed['identifier']['value'] || parsed['identifier']);
                                    const candidateId = identifierVal || parsed['jobId'] || parsed['id'];
                                    if (candidateId && `${candidateId}` === `${jobId}`) matchesId = true;
                                } catch { }

                                // Accept SSR script only when it explicitly indicates JobPosting and matches id (or the script's data-ld-id matched the requested id)
                                if (matchesId || existingScript.getAttribute('data-ld-id') === `jobposting-${jobId}`) {
                                    schema = parsed;
                                } else {
                                    schema = null;
                                }
                            } else {
                                schema = null;
                            }
                        } catch {
                            schema = null;
                        }
                    }
                    // Mark SSR as consumed so subsequent navigations will fetch as needed
                    this.initialSchemaSSR = false;
                }

                if (!schema) {
                    const schemas = await APIService.fetchJobSchemasGraphQL([jobId], this.schemaAbortController?.signal, 'JobPosting');
                    schema = schemas?.[0];
                }

                if (!schema) return;

                const script = document.createElement('script');
                script.type = 'application/ld+json';
                script.setAttribute('data-ld-type', 'JobPosting');
                script.setAttribute('data-ld-id', `jobposting-${jobId}`);
                script.textContent = JSON.stringify(schema);

                document.head.appendChild(script);
                this.addedJobPostingIds.add(jobId);
            } catch (e) {
                if (e instanceof Error && e.name === 'AbortError') {
                    // Request was aborted, ignore
                    return;
                }
                console.warn('Failed to add JobPosting JSON-LD', e);
            }
        };

        void addSchema();
    }

    /**
     * Remove any JSON-LD schema scripts inserted by this utility and clear tracking.
     *
     * This is a blunt operation intended for teardown or navigation where all inserted
     * schemas should be removed from the document.
     */
    public RemoveAllSchemas(): void {
        try {
            if (typeof document === 'undefined') return;
            const existingSchemas = Array.from(document.querySelectorAll('script[type="application/ld+json"]')) as HTMLScriptElement[];
            existingSchemas.forEach(s => s.remove());
            try {
                // Clear addedJobPostingIds tracking
                this.addedJobPostingIds.clear();
            } catch {
                // ignore
            }
        } catch (e) {
            console.warn('Failed to remove JSON-LD schemas', e);
        }
    }

    /**
     * Add or refresh an ItemList JSON-LD script for a batch of job IDs.
     *
     * Behavior:
     * - If an explicit SSR-provided script marked with `data-ld-type="ItemList"` exists, it will be used and no fetch is made.
     * - Otherwise fetches schema data via {@link APIService.fetchJobSchemasGraphQL} and normalizes/insserts the ItemList script.
     * - Replaces any existing ItemList scripts rather than appending.
     *
     * @param jobIds - Array of job IDs to include in the ItemList
     */
    public addItemListSchema(jobIds: number[]): void {
        if (!jobIds || jobIds.length === 0) return;

        if (this.schemaAbortController) {
            this.schemaAbortController.abort();
        }
        this.schemaAbortController = new AbortController();

        const fetchAndInsert = async (): Promise<void> => {
            try {
                let schemas: any = null;
                let schema: any = null;
                let ssrProvided = false;

                if (this.initialSchemaSSR) {
                    const explicitItemList = document.querySelector('script[type="application/ld+json"][data-ld-type="ItemList"]') as HTMLScriptElement | null;
                    if (explicitItemList && explicitItemList.textContent) {
                        try {
                            const parsed = JSON.parse(explicitItemList.textContent);
                            if (parsed && parsed['@type'] === 'ItemList') {
                                schema = parsed;
                                ssrProvided = true;
                            }
                        } catch {
                            // ignore parse errors
                        }
                    }
                    // Mark SSR consumed so subsequent calls will fetch fresh data
                    this.initialSchemaSSR = false;
                }

                // If SSR provided a usable ItemList/JobPosting schema, skip fetching to avoid duplicate insertion.
                if (ssrProvided && schema) {
                    // Ensure we normalize by setting our data attribute (done above) and then return.
                    return;
                }

                // If SSR was a vendor bundle (RankMath), we set ssrProvided true and schema null — bail out to avoid colliding.
                if (ssrProvided && !schema) {
                    return;
                }

                // Fetch only when SSR did not provide schema or parsing failed
                if (!schema) {
                    schemas = await APIService.fetchJobSchemasGraphQL(jobIds, this.schemaAbortController?.signal, 'ItemList');
                }

                // Normalize response: API may return an array of JobPosting objects (old behavior) or a single ItemList.
                if (Array.isArray(schemas)) {
                    // If it's already an ItemList as first element, use it
                    if (schemas.length === 1 && schemas[0] && schemas[0]['@type'] === 'ItemList') {
                        schema = schemas[0];
                    } else if (schemas.length >= 1 && schemas.every((s: any) => s && s['@type'] === 'JobPosting')) {
                        // Server returned JobPosting items individually — convert to ItemList client-side
                        const elements = schemas.map((job: any, idx: number) => {
                            const item = { ...job };
                            if (item['@context']) delete item['@context'];
                            return { "@type": "ListItem", "position": idx + 1, "item": item };
                        });
                        schema = {
                            "@context": "https://schema.org",
                            "@type": "ItemList",
                            "itemListElement": elements,
                            "itemListOrder": "https://schema.org/ItemListOrderDescending",
                            "numberOfItems": elements.length,
                        };
                    } else if (schemas.length >= 1) {
                        schema = schemas[0];
                    }
                } else if (!schema) {
                    schema = schemas;
                }

                if (!schema) return;

                // Always replace the ItemList script with the fetched batch or SSR one
                this.updateSchemaMarkup(schema, 'ItemList');
            } catch (e) {
                if (e instanceof Error && e.name === 'AbortError') {
                    return;
                }
                console.warn('Failed to fetch/insert ItemList schema', e);
            }
        };

        void fetchAndInsert();
    }

    /**
     * Public convenience for adding schemas for a batch of job IDs.
     *
     * - Emits an ItemList only when the current route is the Homepage. Otherwise
     *   any existing ItemList scripts are removed (ItemList should only appear on Homepage).
     * - Delegates the actual fetch/insert work to {@link addItemListSchema}.
     *
     * @param jobIds - Array of job IDs to include in the ItemList
     */
    public addJobSchemas(jobIds: number[]): void {
        if (!jobIds || jobIds.length === 0) return;

        // Only emit ItemList schema when on the Homepage route. If not on
        // homepage, ensure any existing ItemList schemas are removed so they
        // are exclusive to the homepage.
        try {
            const comp = routeStore.getComponentNamePath(routeStore.currentUrl.pathname);
            if (comp !== 'Homepage') {
                if (typeof document !== 'undefined') {
                    const existingItemLists = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="ItemList"]')) as HTMLScriptElement[];
                    existingItemLists.forEach(s => s.remove());
                }
                return;
            }
        } catch {
            // If routeStore is not available for some reason, fail open and continue
            // to avoid breaking schema insertion. But prefer to not add ItemList.
            try {
                if (typeof document !== 'undefined') {
                    const existingItemLists = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="ItemList"]')) as HTMLScriptElement[];
                    existingItemLists.forEach(s => s.remove());
                }
            } catch {
                // ignore
            }
            return;
        }

        this.addItemListSchema(jobIds);
        return;
    }
}

export const utilsSEO = new SEOUtils();