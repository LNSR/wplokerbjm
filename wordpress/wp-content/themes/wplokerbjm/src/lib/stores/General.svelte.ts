import { createSubscriber, SvelteDate } from 'svelte/reactivity'
import { type JobSummary, type StatusPekerjaanNumber, type StatusPekerjaanString } from "@/types";
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

/**
 * General Job display UI orchestration store.
 */
class GeneralJobStore
{
    public svelteDate = new SvelteDate(); // non-reactive if used in non-tracking context
    /** IANA time zone used for formatting and comparisons (default: Makassar) */
    #timeZone: string = 'Asia/Makassar';
    #intervalId: ReturnType<typeof setInterval> | null = null;

    public getNowReactiveDate = $derived.by( () =>
    {
        this.#subscribeToTime();
        return this.svelteDate;
    } );

    /**
* Provides a better performance by sharing a single interval for all time-based updates.
* @see this.showTimeAgo()
* @see this.showDeadline()
*/
    #subscribeToTime = createSubscriber( ( update ) =>
    {
        if ( this.#intervalId ) return;
        this.#intervalId = setInterval( () =>
        {
            const now = Date.now();
            this.svelteDate.setTime( now );
            update();
        }, 60000 ); // Update every 1 minute

        // clean up the interval when the subscriber is destroyed to prevent memory leaks
        return () =>
        {
            if ( this.#intervalId )
            {
                clearInterval( this.#intervalId );
                this.#intervalId = null;
            }
        };
    } );

    private getYMDInTimeZone( date: Date, timeZone: string = this.#timeZone )
    {
        const fmt = new Intl.DateTimeFormat( 'en-CA', { timeZone, year: 'numeric', month: 'numeric', day: 'numeric' } );
        const parts = fmt.formatToParts( date ).reduce( ( acc: Record<string, string>, p ) => ( acc[ p.type ] = p.value, acc ), {} as Record<string, string> );
        return {
            year: Number( parts.year ),
            month: Number( parts.month ),
            day: Number( parts.day ),
        };
    }

    public showDeadline( deadline: string ): { text: string; status: 'upcoming' | 'soon' | 'last_day' | 'expired_yesterday' | 'expired' | 'today' | 'unknown' }
    {
        if ( !deadline )
        {
            return { text: '', status: 'unknown' }
        }

        const deadlineDateRaw = new SvelteDate( deadline );
        const nowDate = this.getNowReactiveDate;
        // compute Y/M/D in target time zone then compare UTC midnights to get whole-day difference
        const deadlineYMD = this.getYMDInTimeZone( deadlineDateRaw, this.#timeZone );
        const nowYMD = this.getYMDInTimeZone( nowDate, this.#timeZone );
        const msPerDay = 1000 * 60 * 60 * 24;
        const deadlineMidUTC = Date.UTC( deadlineYMD.year, deadlineYMD.month - 1, deadlineYMD.day );
        const nowMidUTC = Date.UTC( nowYMD.year, nowYMD.month - 1, nowYMD.day );
        const days_left = Math.floor( ( deadlineMidUTC - nowMidUTC ) / msPerDay );
        let text = ''
        let status: 'upcoming' | 'soon' | 'last_day' | 'expired_yesterday' | 'expired' | 'today' | 'unknown' = 'unknown'

        switch ( true )
        {
            case days_left > 1:
                text = `Sisa ${ days_left } hari`
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
    public showTimeAgo( postTime: string ): string
    {
        if ( !postTime ) return '';
        const postDate = new SvelteDate( postTime );
        if ( isNaN( postDate.getTime() ) ) return '';
        const nowDate = this.getNowReactiveDate;
        const seconds = Math.floor( ( nowDate.getTime() - postDate.getTime() ) / 1000 );
        return this._formatHelper.formatTimeAgo( seconds );
    }

    // Return a single status string. Previously returned {label,status} where both values were identical.
    public showStatusJob( status_pekerjaan: StatusPekerjaanNumber ): StatusPekerjaanString | ''
    {
        if ( typeof status_pekerjaan !== 'number' ) throw new Error( 'status_pekerjaan must be a number' );
        switch ( status_pekerjaan )
        {
            case 2:
                return 'Urgent'
            case 3:
                return 'Pinned'
            default:
                return ''
        }
    }

    public showSummaryJob( jobdata?: JobSummary | null ): SummaryRow[]
    {
        if ( typeof jobdata !== 'object' || jobdata === null ) throw new Error( 'jobdata must be a non-null object' );
        const rows: SummaryRow[] = []
        const data: JobSummary = ( jobdata ?? {} )

        const arrayOrString = ( value: unknown ): string =>
        {
            return typia.is<string>( value )
                ? value
                : typia.is<string[]>( value ) ? value.join( ', ' ) : '';
        }

        if ( data[ 'jenis_pekerjaan' ] )
        {
            rows.push( {
                icon: ClockSolid,
                label: 'Jenis Pekerjaan',
                value: arrayOrString( data[ 'jenis_pekerjaan' ] ),
            } )
        }
        if ( data[ 'pendidikan' ] )
        {
            rows.push( {
                icon: GraduationCapSolid,
                label: 'Pendidikan',
                value: arrayOrString( data[ 'pendidikan' ] ),
            } )
        }
        if ( data[ 'pengalaman' ] )
        {
            rows.push( {
                icon: BriefcaseSolid,
                label: 'Pengalaman',
                value: `Minimal ${ data[ 'pengalaman' ] } Tahun Pengalaman`,
            } )
        }
        if ( data[ 'gender' ] )
        {
            rows.push( {
                icon: VenusMarsSolid,
                label: 'Gender',
                value: arrayOrString( data[ 'gender' ] ),
            } )
        }
        const gaji_min = data[ 'gaji_minimal' ] ? Number( data[ 'gaji_minimal' ] ) : undefined
        const gaji_max = data[ 'gaji_maksimal' ] ? Number( data[ 'gaji_maksimal' ] ) : undefined
        const gaji_display = this._formatHelper.formatSalary( gaji_min, gaji_max )
        if ( gaji_display )
        {
            rows.push( {
                icon: MoneyBillWaveSolid,
                label: 'Gaji',
                value: gaji_display,
            } )
        }
        const umur_min = data[ 'umur_min' ] ? Number( data[ 'umur_min' ] ) : undefined
        const umur_max = data[ 'umur_max' ] ? Number( data[ 'umur_max' ] ) : undefined
        const umur_display = this._formatHelper.formatAge( umur_min, umur_max )
        if ( umur_display )
        {
            rows.push( {
                icon: CakeCandlesSolid,
                label: 'Usia',
                value: umur_display,
            } )
        }
        if ( data[ 'lokasi_pekerjaan' ] )
        {
            rows.push( {
                icon: MapMarkerAltSolid,
                label: 'Lokasi',
                value: arrayOrString( data[ 'lokasi_pekerjaan' ] ),
            } )
        }

        if ( data[ 'deadline' ] )
        {
            rows.push( {
                icon: CalendarSolid,
                label: 'Deadline',
                value: this._formatHelper.deadlineFormat( data[ 'deadline' ], this.#timeZone, this.getNowReactiveDate ),
            } )
        }

        return rows
    }

    private _formatHelper = {
        deadlineFormat: ( dateStr?: string | null, timeZone?: string, nowDate?: Date ): string =>
        {
            const indonesianMonths = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            if ( !dateStr ) return '';
            const date = new SvelteDate( dateStr );
            if ( isNaN( date.getTime() ) ) return dateStr;

            try
            {
                const dateYMD = this.getYMDInTimeZone( date, timeZone );
                const nowYMD = this.getYMDInTimeZone( nowDate ?? new SvelteDate(), timeZone );
                const msPerDay = 1000 * 60 * 60 * 24;
                const dateMidUTC = Date.UTC( dateYMD.year, dateYMD.month - 1, dateYMD.day );
                const nowMidUTC = Date.UTC( nowYMD.year, nowYMD.month - 1, nowYMD.day );
                const days_left = Math.floor( ( dateMidUTC - nowMidUTC ) / msPerDay );

                if ( days_left < 0 )
                {
                    return 'kadaluarsa';
                }

                return new Intl.DateTimeFormat( 'id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone } ).format( date );
            } catch
            {
                const day = date.getDate();
                const month = date.getMonth();
                const year = date.getFullYear();
                return `${ day } ${ indonesianMonths[ month ] } ${ year }`;
            }
        },

        formatAge: ( umur_min?: number, umur_max?: number ): string | null =>
        {
            const has_umur_min = !!umur_min;
            const has_umur_max = !!umur_max;

            if ( !has_umur_min && !has_umur_max ) return null;

            if ( has_umur_min && has_umur_max )
            {
                return `${ umur_min } - ${ umur_max } Tahun`;
            } else if ( has_umur_min )
            {
                return `Minimal ${ umur_min } Tahun`;
            } else
            {
                return `Maksimal ${ umur_max } Tahun`;
            }
        },

        formatTimeAgo: ( seconds: number ) =>
        {
            const relativeTimeFormatter = new Intl.RelativeTimeFormat( 'id', { numeric: 'always' } );
            const removeYangString = ( text: string ): string => text.replace( /\s+yang\s+/g, ' ' );

            if ( seconds < 60 ) return 'Baru saja diposting';

            const formatTime = ( divisor: number, unit: Intl.RelativeTimeFormatUnit ) =>
                removeYangString( relativeTimeFormatter.format( -Math.floor( seconds / divisor ), unit ) );

            if ( seconds < 3600 ) return formatTime( 60, 'minute' );
            if ( seconds < 86400 ) return formatTime( 3600, 'hour' );
            if ( seconds < 604800 ) return formatTime( 86400, 'day' );
            if ( seconds < 2592000 ) return formatTime( 604800, 'week' );
            if ( seconds < 31536000 ) return formatTime( 2592000, 'month' );
            return formatTime( 31536000, 'year' );
        },

        formatSalary: ( gaji_minimal?: number, gaji_maksimal?: number ): string | null =>
        {
            const has_gaji_min = !!gaji_minimal;
            const has_gaji_max = !!gaji_maksimal;

            if ( !has_gaji_min && !has_gaji_max ) return null;

            const formatIDR = ( value: number ): string =>
                new Intl.NumberFormat( 'id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 } ).format( value );

            const gaji_min = has_gaji_min ? formatIDR( gaji_minimal! ) : null;
            const gaji_max = has_gaji_max ? formatIDR( gaji_maksimal! ) : null;

            if ( has_gaji_min && has_gaji_max )
            {
                return `${ gaji_min } - ${ gaji_max }`;
            } else if ( has_gaji_min )
            {
                return `Sekitar ${ gaji_min }`;
            } else
            {
                return `Maksimal ${ gaji_max }`;
            }
        },
    };
}
export const generalJobStore = new GeneralJobStore();