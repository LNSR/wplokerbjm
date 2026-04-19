import { timeIntervalStore } from '$lib/stores/Time.svelte';
import { type DeadlineStatus, type JobSummary, type StatusPekerjaanNumber, type StatusPekerjaanString } from "@/types";
import type { Component } from 'svelte';
import
{
    ClockSolid,
    GraduationCapSolid,
    BriefcaseSolid,
    VenusMarsSolid,
    MoneyBillWaveSolid,
    CakeCandlesSolid,
    CalendarSolid,
    MapMarkerAltSolid,
} from "svelte-awesome-icons";
import typia from 'typia';

interface SummaryRow
{
    icon: Component
    label: string
    value: string
}

const nowDate = $derived(timeIntervalStore.getNowReactiveDate)

function showSummaryJob(jobdata?: JobSummary | null): SummaryRow[]
{
    if (!typia.is<JobSummary>(jobdata)) throw new Error('jobdata must be a non-null object');
    const rows: SummaryRow[] = []
    const data: JobSummary = (jobdata ?? {})

    const arrayOrString = (value: unknown): string =>
    {
        return typia.is<string>(value)
            ? value
            : typia.is<string[]>(value) ? value.join(', ') : '';
    }

    const gaji_min = data[ 'gaji_minimal' ] ? Number(data[ 'gaji_minimal' ]) : undefined
    const gaji_max = data[ 'gaji_maksimal' ] ? Number(data[ 'gaji_maksimal' ]) : undefined
    const gaji_display = FormatHelper.formatSalary(gaji_min, gaji_max)

    const umur_min = data[ 'umur_min' ] ? Number(data[ 'umur_min' ]) : undefined
    const umur_max = data[ 'umur_max' ] ? Number(data[ 'umur_max' ]) : undefined
    const umur_display = FormatHelper.formatAge(umur_min, umur_max)

    interface SummaryField extends SummaryRow
    {
        key: Exclude<keyof JobSummary, 'gaji_minimal' | 'gaji_maksimal' | 'umur_min' | 'umur_max'> | ('gaji' | 'umur');
    }

    const dataSummaryFields: SummaryField[] = [
        { key: 'jenis_pekerjaan', label: 'Jenis Pekerjaan', icon: ClockSolid, value: arrayOrString(data[ 'jenis_pekerjaan' ]) },
        { key: 'pendidikan', label: 'Pendidikan', icon: GraduationCapSolid, value: arrayOrString(data[ 'pendidikan' ]) },
        { key: 'pengalaman', label: 'Pengalaman', icon: BriefcaseSolid, value: data[ 'pengalaman' ] ? `Minimal ${data[ 'pengalaman' ]} Tahun Pengalaman` : '' },
        { key: 'gender', label: 'Gender', icon: VenusMarsSolid, value: arrayOrString(data[ 'gender' ]) },
        { key: 'gaji', label: 'Gaji', icon: MoneyBillWaveSolid, value: gaji_display ?? '' },
        { key: 'umur', label: 'Usia', icon: CakeCandlesSolid, value: umur_display ?? '' },
        { key: 'lokasi_pekerjaan', label: 'Lokasi', icon: MapMarkerAltSolid, value: arrayOrString(data[ 'lokasi_pekerjaan' ]) },
        { key: 'deadline', label: 'Deadline', icon: CalendarSolid, value: data[ 'deadline' ] ? FormatHelper.deadlineFormat(data[ 'deadline' ], undefined, nowDate) : '' },
    ]

    dataSummaryFields.forEach(field =>
    {
        if (field.value) rows.push({ icon: field.icon, label: field.label, value: field.value })
    })

    return rows
}
function showStatusJob(status_pekerjaan: StatusPekerjaanNumber): StatusPekerjaanString | ''
{
    if (typeof status_pekerjaan !== 'number') throw new Error('status_pekerjaan must be a number');
    switch (status_pekerjaan)
    {
        case 2:
            return 'Urgent'
        case 3:
            return 'Pinned'
        default:
            return ''
    }
}

function showDeadline(deadline: string): { text: string; status: DeadlineStatus }
{
    if (!deadline)
    {
        return { text: '', status: 'unknown' }
    }

    const deadlineDateRaw = new Date(deadline);
    // compute Y/M/D in target time zone then compare UTC midnights to get whole-day difference
    const deadlineYMD = FormatHelper.getYMDInTimeZone(deadlineDateRaw);
    const nowYMD = FormatHelper.getYMDInTimeZone(nowDate);
    const msPerDay = 1000 * 60 * 60 * 24;
    const deadlineMidUTC = Date.UTC(deadlineYMD.year, deadlineYMD.month - 1, deadlineYMD.day);
    const nowMidUTC = Date.UTC(nowYMD.year, nowYMD.month - 1, nowYMD.day);
    const days_left = Math.floor((deadlineMidUTC - nowMidUTC) / msPerDay);
    let text = ''
    let status: DeadlineStatus = 'unknown'

    switch (true)
    {
        case days_left > 1:
            text = `Sisa ${days_left} hari`
            status = 'upcoming'
            break
        case days_left === 1:
            text = 'Sisa 1 hari'
            status = 'soon'
            break
        case days_left === 0:
            text = 'Hari terakhir'
            status = 'last_day'
            break
        case days_left < 0:
            text = 'Kadaluarsa'
            status = 'expired'
            break
        default:
            text = 'Berakhir hari ini'
            status = 'today'
    }
    return { text, status }
}

/**
  * 
  * @param postTime post_time received from API
  * @returns 
  */
function showTimeAgo(postTime: string): string
{
    if (!postTime) return '';
    const postDate = new Date(postTime);
    if (isNaN(postDate.getTime())) return '';
    const seconds = Math.floor((nowDate.getTime() - postDate.getTime()) / 1000);
    return FormatHelper.formatTimeAgo(seconds);
}

/**
 * helper class for formatting various job-related data like deadlines, ages, salaries, and time ago text.
 */
class FormatHelper
{
    static #timeZone = 'Asia/Makassar'

    public static getYMDInTimeZone(date: Date, timeZone: string = this.#timeZone)
    {
        const fmt = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: 'numeric', day: 'numeric' })
        const parts = fmt.formatToParts(date).reduce((acc: Record<string, string>, p) => (acc[ p.type ] = p.value, acc), {} as Record<string, string>)
        return {
            year: Number(parts.year),
            month: Number(parts.month),
            day: Number(parts.day),
        }
    }

    public static deadlineFormat(dateStr?: string | null, timeZone?: string, nowDate?: Date): string
    {
        const indonesianMonths = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ]

        if (!dateStr) return ''
        const date = new Date(dateStr)
        if (isNaN(date.getTime())) return dateStr

        try
        {
            const dateYMD = this.getYMDInTimeZone(date, timeZone)
            const nowYMD = this.getYMDInTimeZone(nowDate ?? new Date(), timeZone)
            const msPerDay = 1000 * 60 * 60 * 24
            const dateMidUTC = Date.UTC(dateYMD.year, dateYMD.month - 1, dateYMD.day)
            const nowMidUTC = Date.UTC(nowYMD.year, nowYMD.month - 1, nowYMD.day)
            const days_left = Math.floor((dateMidUTC - nowMidUTC) / msPerDay)

            if (days_left < 0)
            {
                return 'kadaluarsa'
            }

            return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone }).format(date)
        } catch
        {
            const day = date.getDate()
            const month = date.getMonth()
            const year = date.getFullYear()
            return `${day} ${indonesianMonths[ month ]} ${year}`
        }
    }

    public static formatAge(umur_min?: number, umur_max?: number): string | null
    {
        const has_umur_min = (umur_min ?? 0) > 0
        const has_umur_max = (umur_max ?? 0) > 0

        if (!has_umur_min && !has_umur_max) return null

        if (has_umur_min && has_umur_max)
            return `${umur_min} - ${umur_max} Tahun`
        if (has_umur_min)
            return `Minimal ${umur_min} Tahun`
        if (has_umur_max)
            return `Maksimal ${umur_max} Tahun`
        return null
    }

    public static formatTimeAgo(seconds: number): string
    {
        const relativeTimeFormatter = new Intl.RelativeTimeFormat('id', { numeric: 'always' })
        const removeYangString = (text: string): string => text.replace(/\s+yang\s+/g, ' ')

        if (seconds < 60) return 'Baru saja diposting'

        const formatTime = (divisor: number, unit: Intl.RelativeTimeFormatUnit) =>
            removeYangString(relativeTimeFormatter.format(-Math.floor(seconds / divisor), unit))

        if (seconds < 3600) return formatTime(60, 'minute')
        if (seconds < 86400) return formatTime(3600, 'hour')
        if (seconds < 604800) return formatTime(86400, 'day')
        if (seconds < 2592000) return formatTime(604800, 'week')
        if (seconds < 31536000) return formatTime(2592000, 'month')
        return formatTime(31536000, 'year')
    }

    public static formatSalary(gaji_minimal?: number, gaji_maksimal?: number): string | null
    {
        const has_gaji_min = !!gaji_minimal
        const has_gaji_max = !!gaji_maksimal

        if (!has_gaji_min && !has_gaji_max) return null

        const formatIDR = (value: number): string =>
            new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)

        const gaji_min = has_gaji_min ? formatIDR(gaji_minimal!) : null
        const gaji_max = has_gaji_max ? formatIDR(gaji_maksimal!) : null

        if (has_gaji_min && has_gaji_max)
        {
            return `${gaji_min} - ${gaji_max}`
        } else if (has_gaji_min)
        {
            return `Sekitar ${gaji_min}`
        } else
        {
            return `Maksimal ${gaji_max}`
        }
    }
}

export { showSummaryJob, showStatusJob, showDeadline, showTimeAgo }; 