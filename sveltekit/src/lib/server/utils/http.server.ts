import type { JobDetailResponse } from "@/types";
import { cookieJwtName } from "$lib/server/constants/constants";

type LinkAs = HTMLLinkElement[ 'as' ] & ("audio" | "document" | "embed" | "fetch" | "font" | "image" | "object" | "script" | "style" | "track" | "video" | "worker");

/**
 * Extract image URLs from an HTML string using a simple regex fallback.
 */
function extractImagesFromHtml(html: string | null | undefined): string[]
{
    if (!html) return [];
    const srcs: string[] = [];
    const imgRe = /<img\s[^>]*?(?:src|data-src)=(\"|')(.*?)\1/gi;
    let match: RegExpExecArray | null;
    while ((match = imgRe.exec(html)))
    {
        if (match[ 2 ]) srcs.push(match[ 2 ]);
    }
    return srcs.filter(Boolean);
}

/**
 * Build a preload Link entry for early hints.
 */
export function buildPreloadLink(url: HTMLLinkElement[ 'href' ], as: LinkAs = "image", opts?: { nopush?: boolean; crossorigin?: boolean; media?: string }): string
{
    const parts = [ `<${url}>`, `rel=preload`, `as=${as}` ];
    if (opts?.nopush) parts.push("nopush");
    if (opts?.crossorigin) parts.push("crossorigin");
    if (opts?.media) parts.push(`media=${opts.media}`);
    return parts.join("; ");
}

/**
 * Collect image URLs from a JobDetailResponse and return a single Link header value
 * suitable for early hints (comma-separated preload entries).
 */
export function collectPreloadLinksForJob(job: JobDetailResponse | null | undefined): string | null
{
    if (!job) return null;

    const fields = [
        job.tentang_perusahaan,
        job.deskripsi_pekerjaan,
        job.persyaratan,
        job.cara_melamar,
        job.benefit,
    ];

    const urls = fields
        .filter((f): f is string => Boolean(f))
        .flatMap((f) => extractImagesFromHtml(f));


    const unique = Array.from(new Set(urls)).filter(Boolean);
    if (unique.length === 0) return null;

    const entries = unique.map((u) => buildPreloadLink(u, "image", { nopush: true }));
    return entries.join(", ");
}

export function isAuthenticated(cookies: string | null): boolean
{
    if (!cookies) return false;
    const wpAuthCookiePattern = /wordpress_logged_in|wordpress_sec|wordpress_\w+_?\d+/i;
    if (new RegExp(`${cookieJwtName}=([^;]+)`).test(cookies)) return true;
    return wpAuthCookiePattern.test(cookies);
}