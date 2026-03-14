import { SvelteDate, SvelteSet } from 'svelte/reactivity'
import { FormattingService } from '@/services/Formatting'
import { type JobSummary, type JobContactRow, type SocialMediaItem, type CustomFields, SocialMediaPlatform } from "@/types";
import type { Component } from 'svelte';
import {
    ClockSolid,
    GraduationCapSolid,
    BriefcaseSolid,
    VenusMarsSolid,
    MoneyBillWaveSolid,
    CakeCandlesSolid,
    CalendarSolid,
    EnvelopeSolid,
    PhoneSolid,
    GlobeSolid,
    InstagramBrands,
    WhatsappBrands,
    TiktokBrands,
    ThreadsBrands,
    FacebookBrands,
    TelegramBrands,
    LinkedinBrands,
    YoutubeBrands,
    TwitterBrands,
    MapMarkerAltSolid,
} from "svelte-awesome-icons";

interface SummaryRow {
    icon: Component
    label: string
    value: string
}

interface ContactRow {
    type: string
    icon: Component
    label: string
    value: string
    href: string
}
export class GeneralStore {

    public useDeadline(deadline: string | null | undefined, now?: SvelteDate): { text: string; style: string } {
        function computeDeadlineInfo(dl?: string | null, nowMs?: number): { text: string; style: string } {
            if (!dl) {
                return { text: '', style: '' }
            }
            const deadlineDateRaw = new Date(dl)
            const nowRaw = new Date(nowMs ?? Date.now())
            const deadlineDate = new Date(
                deadlineDateRaw.getFullYear(),
                deadlineDateRaw.getMonth(),
                deadlineDateRaw.getDate()
            )
            const now = new Date(nowRaw.getFullYear(), nowRaw.getMonth(), nowRaw.getDate())
            const msPerDay = 1000 * 60 * 60 * 24
            const days_left = Math.floor((deadlineDate.getTime() - now.getTime()) / msPerDay)
            let text = ''
            let style = ''
            if (days_left > 1) {
                text = `Sisa ${days_left} hari`
                style = 'bg-blue-600 text-white border border-blue-800'
            } else if (days_left === 1) {
                text = 'Sisa 1 hari'
                style = 'bg-yellow-400 text-black border border-yellow-600'
            } else if (days_left === 0) {
                text = 'Hari terakhir'
                style = 'bg-red-600 text-white border border-red-800'
            } else if (days_left === -1) {
                text = 'Berakhir kemarin'
                style = 'bg-gray-500 text-white border border-gray-700'
            } else if (days_left < -1) {
                text = `Berakhir ${Math.abs(days_left)} hari lalu`
                style = 'bg-gray-400 text-black border border-gray-700'
            } else {
                text = 'Berakhir hari ini'
                style = 'bg-red-600 text-white border border-red-800'
            }
            return { text, style }
        }

        const deadlineInfo = this.timeReactiveValues<{ text: string; style: string }>(
            (nowMs) => computeDeadlineInfo(deadline, nowMs),
            now,
        )

        return deadlineInfo()
    }

    public useTimeAgo(postTime?: string, now?: SvelteDate): string {
        function computeTimeText(pt?: string, nowMs?: number): string {
            if (!pt) return ''
            const postDate = new Date(pt)
            if (isNaN(postDate.getTime())) return ''
            const nowDate = new Date(nowMs ?? Date.now())
            const diff = Math.floor((nowDate.getTime() - postDate.getTime()) / 1000)

            if (diff < 60) return 'Baru saja diposting'
            if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
            if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
            if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`
            if (diff < 2592000) return `${Math.floor(diff / 604800)} minggu lalu`
            if (diff < 31536000) return `${Math.floor(diff / 2592000)} bulan lalu`
            return `${Math.floor(diff / 31536000)} tahun lalu`
        }

        const time = this.timeReactiveValues<string>(
            (nowMs) => computeTimeText(postTime, nowMs),
            now,
        )

        return time()
    }

    private timeReactiveValues<T>(compute: (nowMs: number) => T, now?: SvelteDate,): () => T {
        const value = $derived.by(() => {
            // read now so derived recalculates every tick
            now
            const nowMs = now ? now.getTime() : Date.now()
            return compute(nowMs)
        })

        // Return a thunk so callers read the reactive value lazily. This
        // avoids the compiler warning about returning a local reactive
        // variable which would otherwise capture only its initial value.
        return () => value
    }

    public useStatusJob(status_pekerjaan: number): { label: string; color: string } {
        switch (status_pekerjaan) {
            case 2:
                return {
                    label: 'Urgent',
                    color: 'bg-red-600 text-white border border-red-700 shadow-sm text-xs',
                }
            case 3:
                return {
                    label: 'Pinned',
                    color: 'bg-yellow-400 text-black border border-yellow-600 shadow-sm text-xs',
                }
            default:
                return {
                    label: '',
                    color: '',
                }
        }
    }

    public useSummaryJob(jobdata: JobSummary | null | undefined): SummaryRow[] {
        const rows: SummaryRow[] = []
        const data: JobSummary = (jobdata ?? {}) as JobSummary

        /**
         * Format deadline date to Indonesian format
         */
        function deadlineFormat(dateStr: string): string {
            if (!dateStr) return ''
            const date = new Date(dateStr)
            if (isNaN(date.getTime())) return dateStr
            const day = date.getDate()
            const month = date.getMonth()
            const year = date.getFullYear()
            const indonesianMonths = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ]
            return `${day} ${indonesianMonths[month]} ${year}`
        }


        if (data['jenis_pekerjaan']) {
            rows.push({
                icon: ClockSolid,
                label: 'Jenis Pekerjaan',
                value: Array.isArray(data['jenis_pekerjaan'])
                    ? data['jenis_pekerjaan'].join(', ')
                    : String(data['jenis_pekerjaan'] ?? ''),
            })
        }
        if (data['pendidikan']) {
            rows.push({
                icon: GraduationCapSolid,
                label: 'Pendidikan',
                value: Array.isArray(data['pendidikan'])
                    ? data['pendidikan'].join(', ')
                    : String(data['pendidikan'] ?? ''),
            })
        }
        if (data['pengalaman']) {
            rows.push({
                icon: BriefcaseSolid,
                label: 'Pengalaman',
                value: `Minimal ${data['pengalaman']} Tahun Pengalaman`,
            })
        }
        if (data['gender']) {
            rows.push({
                icon: VenusMarsSolid,
                label: 'Gender',
                value: Array.isArray(data['gender'])
                    ? data['gender'].join(', ')
                    : String(data['gender'] ?? ''),
            })
        }
        const gaji_min = data['gaji_minimal'] ? Number(data['gaji_minimal']) : undefined
        const gaji_max = data['gaji_maksimal'] ? Number(data['gaji_maksimal']) : undefined
        const gaji_display = FormattingService.formatSalary(gaji_min, gaji_max)
        if (gaji_display) {
            rows.push({
                icon: MoneyBillWaveSolid,
                label: 'Gaji',
                value: gaji_display,
            })
        }
        const umur_min = data['umur_min'] ? Number(data['umur_min']) : undefined
        const umur_max = data['umur_max'] ? Number(data['umur_max']) : undefined
        const umur_display = FormattingService.formatAge(umur_min, umur_max)
        if (umur_display) {
            rows.push({
                icon: CakeCandlesSolid,
                label: 'Usia',
                value: umur_display,
            })
        }
        if (data['lokasi_pekerjaan']) {
            rows.push({
                icon: MapMarkerAltSolid,
                label: 'Lokasi',
                value: Array.isArray(data['lokasi_pekerjaan'])
                    ? data['lokasi_pekerjaan'].join(', ')
                    : String(data['lokasi_pekerjaan'] ?? ''),
            })
        }

        if (data['deadline']) {
            rows.push({
                icon: CalendarSolid,
                label: 'Deadline',
                value: deadlineFormat(data['deadline']),
            })
        }

        return rows
    }

    public useContactsJob(jobdata: JobContactRow | undefined): ContactRow[] {
        if (!jobdata) return [];
        const contacts: ContactRow[] = [];

        // email_kontak is now a comma-separated string
        const emails = jobdata.email_kontak ? jobdata.email_kontak.split(',').map((e: string) => e.trim()) : [];
        emails.forEach((email: string) => {
            if (email) {
                contacts.push({
                    type: 'email',
                    icon: EnvelopeSolid,
                    label: 'Email',
                    value: email,
                    href: `mailto:${email}`,
                })
            }
        });

        // nomor_kontak is now a comma-separated string
        const phones = jobdata.nomor_kontak ? jobdata.nomor_kontak.split(',').map((p: string) => p.trim()) : [];
        phones.forEach((phone: string) => {
            if (phone) {
                contacts.push({
                    type: 'phone',
                    icon: PhoneSolid,
                    label: 'Telepon',
                    value: phone,
                    href: `tel:${phone}`,
                })
            }
        });

        // situs_kontak is now a comma-separated string
        const sites = jobdata.situs_kontak ? jobdata.situs_kontak.split(',').map((s: string) => s.trim()) : [];
        sites.forEach((site: string) => {
            if (site) {
                const href = site.replace(/^http:\/\//i, 'https://');
                contacts.push({
                    type: 'website',
                    icon: GlobeSolid,
                    label: 'Website',
                    value: site.replace(/^https?:\/\//i, ''),
                    href,
                })
            }
        });

        return contacts
    }

    public useSocialMedia(): { socialMediaItems: (data: CustomFields['social_media']) => SocialMediaItem[] } {
        const platforms: Record<SocialMediaPlatform, { icon: Component; base_url: string }> = {
            [SocialMediaPlatform["X / Twitter"]]: {
                icon: TwitterBrands,
                base_url: "https://twitter.com/",
            },
            [SocialMediaPlatform.Facebook]: { icon: FacebookBrands, base_url: "https://facebook.com/" },
            [SocialMediaPlatform.Instagram]: { icon: InstagramBrands, base_url: "https://instagram.com/" },
            [SocialMediaPlatform.LinkedIn]: { icon: LinkedinBrands, base_url: "https://linkedin.com/in/" },
            [SocialMediaPlatform.Youtube]: { icon: YoutubeBrands, base_url: "https://youtube.com/@" },
            [SocialMediaPlatform.WhatsApp]: { icon: WhatsappBrands, base_url: "https://wa.me/" },
            [SocialMediaPlatform.TikTok]: { icon: TiktokBrands, base_url: "https://tiktok.com/@" },
            [SocialMediaPlatform.Threads]: { icon: ThreadsBrands, base_url: "https://threads.net/@" },
            [SocialMediaPlatform.Telegram]: { icon: TelegramBrands, base_url: "https://t.me/" },
        };
        function getLinkData(platform: string, username: string): SocialMediaItem | null {
            const config = platforms[platform as SocialMediaPlatform];
            if (!config || !username) return null;
            if (platform === SocialMediaPlatform.WhatsApp)
                return getWhatsappLinkData(platform, config, username);
            if (platform === SocialMediaPlatform.LinkedIn)
                return getLinkedInLinkData(platform, config, username);
            return getDefaultLinkData(platform, config, username);
        }

        function getWhatsappLinkData(
            platform: string,
            config: { icon: Component; base_url: string },
            username: string
        ): SocialMediaItem {
            if (/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i.test(username)) {
                const normalized = username.replace(/^http:\/\//i, 'https://');
                return { platform, icon: config.icon, url: normalized, username };
            }
            const waMeMatch = /^(?:https?:\/\/)?wa\.me\/(\d+)$/i.exec(username);
            if (waMeMatch) {
                const number = waMeMatch[1];
                return {
                    platform,
                    icon: config.icon,
                    url: `https://wa.me/${number}`,
                    username: `+${number}`,
                };
            }
            if (/^https?:\/\/((api|web)\.whatsapp\.com)/.test(username)) {
                const normalized = username.replace(/^http:\/\//i, 'https://');
                return { platform, icon: config.icon, url: normalized, username };
            }
            const clean_number = username.replace(/[^0-9]/g, "");
            return {
                platform,
                icon: config.icon,
                url: config.base_url + clean_number,
                username,
            };
        }

        function getLinkedInLinkData(
            platform: string,
            config: { icon: Component; base_url: string },
            username: string
        ): SocialMediaItem {
            if (/^https?:\/\//i.test(username)) {
                const normalized = username.replace(/^http:\/\//i, 'https://');
                return { platform, icon: config.icon, url: normalized, username };
            }
            const clean_username = username.replace(/^@/, "");
            const companyMatch = /^company[:/](.+)$/i.exec(clean_username);
            let url;
            if (companyMatch) {
                url = `https://linkedin.com/company/${companyMatch[1]}`;
            } else {
                url = config.base_url + clean_username;
            }
            return { platform, icon: config.icon, url, username };
        }

        function getDefaultLinkData(
            platform: string,
            config: { icon: Component; base_url: string },
            username: string
        ): SocialMediaItem {
            if (/^https?:\/\//i.test(username)) {
                const normalized = username.replace(/^http:\/\//i, 'https://');
                return { platform, icon: config.icon, url: normalized, username };
            }
            const clean_username = username.replace(/^@/, "");
            const url = config.base_url + clean_username;
            return { platform, icon: config.icon, url, username };
        }
        function createSocialMediaItems(
            socialMediaData: CustomFields['social_media']
        ): SocialMediaItem[] {
            const processedItems: SocialMediaItem[] = [];
            const seen = new SvelteSet<string>();
            if (!socialMediaData) return processedItems;

            const items = socialMediaData.split(';').map((s: string) => s.trim()).filter(Boolean);

            for (const item of items) {
                const idx = item.indexOf(':');
                if (idx === -1) continue;
                const platform = item.slice(0, idx).trim();
                const usernames = item.slice(idx + 1).trim();
                if (!platform || !usernames) continue;
                const usernameList = usernames.split(',').map((u: string) => u.trim()).filter(u => u);
                for (const username of usernameList) {
                    const linkData = getLinkData(platform, username);
                    if (linkData) {
                        const key = linkData.platform + linkData.username;
                        if (!seen.has(key)) {
                            seen.add(key);
                            processedItems.push(linkData);
                        }
                    }
                }
            }
            return processedItems;
        }
        return { socialMediaItems: createSocialMediaItems };
    }
}

export const generalStore = new GeneralStore();