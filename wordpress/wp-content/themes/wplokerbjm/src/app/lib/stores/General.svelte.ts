import { SvelteDate } from 'svelte/reactivity'
import { FormattingService } from '@/services/Formatting'
import { type JobSummary, type JobContactRow, type SocialMediaItem } from "@/types";
import type { Component } from 'svelte';
import {
  ClockSolid,
  GraduationCapSolid,
  BriefcaseSolid,
  VenusMarsSolid,
  MoneyBillWaveSolid,
  CakeCandlesSolid,
  MapPinSolid,
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
} from "svelte-awesome-icons";

export interface SummaryRow {
    icon: Component
    label: string
    value: string
}

export interface ContactRow {
    type: string
    icon: Component
    label: string
    value: string
    href: string
}
export class GeneralStore {
    static useDeadline(deadline: string | null | undefined): { text: string; style: string } {
        if (!deadline) {
            return { text: '', style: '' }
        }
        let normalized = deadline
        if (typeof normalized === 'string' && /^\d{2}-\d{2}-\d{4}$/.test(normalized)) {
            const [day, month, year] = normalized.split('-')
            normalized = `${year}-${month}-${day}`
        }
        const deadlineDateRaw = new SvelteDate(normalized)
        const nowRaw = new SvelteDate()
        const deadlineDate = new SvelteDate(
            deadlineDateRaw.getFullYear(),
            deadlineDateRaw.getMonth(),
            deadlineDateRaw.getDate()
        )
        const now = new SvelteDate(nowRaw.getFullYear(), nowRaw.getMonth(), nowRaw.getDate())
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

    static useStatusJob(status_pekerjaan: number): { label: string; color: string } {
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

    static useSummaryJob(jobdata: JobSummary | null | undefined): SummaryRow[] {
        const rows: SummaryRow[] = []
        const data: JobSummary = (jobdata ?? {}) as JobSummary

        if (data['jenis_pekerjaan_taxo']) {
            rows.push({
                icon: ClockSolid,
                label: 'Jenis Pekerjaan',
                value: Array.isArray(data['jenis_pekerjaan_taxo'])
                    ? data['jenis_pekerjaan_taxo'].join(', ')
                    : String(data['jenis_pekerjaan_taxo'] ?? ''),
            })
        }
        if (data['pendidikan_taxo']) {
            rows.push({
                icon: GraduationCapSolid,
                label: 'Pendidikan',
                value: Array.isArray(data['pendidikan_taxo'])
                    ? data['pendidikan_taxo'].join(', ')
                    : String(data['pendidikan_taxo'] ?? ''),
            })
        }
        if (data['pengalaman']) {
            rows.push({
                icon: BriefcaseSolid,
                label: 'Pengalaman',
                value: `Minimal ${data['pengalaman']} Tahun Pengalaman`,
            })
        }
        if (data['gender_taxo']) {
            rows.push({
                icon: VenusMarsSolid,
                label: 'Gender',
                value: Array.isArray(data['gender_taxo'])
                    ? data['gender_taxo'].join(', ')
                    : String(data['gender_taxo'] ?? ''),
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
        if (data['lokasi_taxo']) {
            rows.push({
                icon: MapPinSolid,
                label: 'Lokasi',
                value: Array.isArray(data['lokasi_taxo'])
                    ? data['lokasi_taxo'].join(', ')
                    : String(data['lokasi_taxo'] ?? ''),
            })
        }
        if (data['deadline']) {
            rows.push({
                icon: CalendarSolid,
                label: 'Deadline',
                value: data['deadline'],
            })
        }

        return rows
    }

    static useContactsJob(jobdata: JobContactRow): ContactRow[] {
        const contacts: ContactRow[] = [];

        (jobdata.email_kontak ?? []).forEach((email) => {
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

        (jobdata.nomor_kontak ?? []).forEach((phone) => {
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

        (jobdata.situs_kontak ?? []).forEach((site) => {
            if (site) {
                contacts.push({
                    type: 'website',
                    icon: GlobeSolid,
                    label: 'Website',
                    value: site.replace(/^https?:\/\//, ''),
                    href: site,
                })
            }
        });

        return contacts
    }

    static useTimeAgo(postTime?: string): () => string {
        function computeTimeText(pt?: string, nowMs?: number): string {
            if (!pt) return ''
            const postDate = new Date(pt)
            if (isNaN(postDate.getTime())) return ''
            const now = new Date(nowMs ?? Date.now())
            const diff = Math.floor((now.getTime() - postDate.getTime()) / 1000)

            if (diff < 60) return 'Baru saja diposting'
            if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
            if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
            if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`
            if (diff < 2592000) return `${Math.floor(diff / 604800)} minggu lalu`
            if (diff < 31536000) return `${Math.floor(diff / 2592000)} bulan lalu`
            return `${Math.floor(diff / 31536000)} tahun lalu`
        }

        // shared clock that ticks every second
        let _nowClock: SvelteDate | null = null
        if (typeof window !== 'undefined') {
            _nowClock = new SvelteDate()
            $effect(() => {
                const id = setInterval(() => _nowClock!.setTime(Date.now()), 1000)
                return () => clearInterval(id)
            })
        } else {
            _nowClock = new SvelteDate(0)
        }

        const time = $derived.by(() => {
            // read shared clock so derived recalculates every tick
            _nowClock
            const nowMs = _nowClock ? _nowClock.getTime() : Date.now()
            return computeTimeText(postTime, nowMs)
        })

        // Return a thunk so callers read the reactive value lazily. This
        // avoids the compiler warning about returning a local reactive
        // variable which would otherwise capture only its initial value.
        return () => time
    }

    static useSocialMedia(): { socialMediaItems: (data: Record<string, string | string[]>) => SocialMediaItem[] } {
    // Icons are Svelte components (migration complete).
    const platforms: Record<string, { icon: Component; base_url: string }> = {
            "X / Twitter": {
                icon: TwitterBrands,
                base_url: "https://twitter.com/",
            },
            Facebook: { icon: FacebookBrands, base_url: "https://facebook.com/" },
            Instagram: { icon: InstagramBrands, base_url: "https://instagram.com/" },
            LinkedIn: { icon: LinkedinBrands, base_url: "https://linkedin.com/in/" },
            Youtube: { icon: YoutubeBrands, base_url: "https://youtube.com/@" },
            Whatsapp: { icon: WhatsappBrands, base_url: "https://wa.me/" },
            Tiktok: { icon: TiktokBrands, base_url: "https://tiktok.com/@" },
            Threads: { icon: ThreadsBrands, base_url: "https://threads.net/@" },
            Telegram: { icon: TelegramBrands, base_url: "https://t.me/" },
        };
        function getLinkData(platform: string, username: string): SocialMediaItem | null {
            const config = platforms[platform];
            if (!config || !username) return null;
            if (platform === "Whatsapp")
                return getWhatsappLinkData(platform, config, username);
            if (platform === "LinkedIn")
                return getLinkedInLinkData(platform, config, username);
            return getDefaultLinkData(platform, config, username);
        }

        function getWhatsappLinkData(
            platform: string,
            config: { icon: Component; base_url: string },
            username: string
        ): SocialMediaItem {
            if (/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i.test(username)) {
                return { platform, icon: config.icon, url: username, username };
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
                return { platform, icon: config.icon, url: username, username };
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
                return { platform, icon: config.icon, url: username, username };
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
                return { platform, icon: config.icon, url: username, username };
            }
            const clean_username = username.replace(/^@/, "");
            const url = config.base_url + clean_username;
            return { platform, icon: config.icon, url, username };
        }

        function createSocialMediaItems(
            socialMediaData: Record<string, string | string[]>
        ): SocialMediaItem[] {
            const processedItems: SocialMediaItem[] = [];
            for (const platform in socialMediaData) {
                const usernames = Array.isArray(socialMediaData[platform])
                    ? socialMediaData[platform]
                    : [socialMediaData[platform]];
                for (const username of usernames) {
                    if (!platform || !username) continue;
                    const linkData = getLinkData(platform, username);
                    if (linkData) {
                        processedItems.push(linkData);
                    }
                }
            }
            return processedItems;
        }
        return { socialMediaItems: createSocialMediaItems };
     }
 }