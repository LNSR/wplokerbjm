import { SvelteDate } from 'svelte/reactivity'
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
    public now = new SvelteDate();
    #refCount = 0;
    #intervalId: ReturnType<typeof setInterval> | null = null;

    private getNowDate(): Date
    {
        return new Date( this.now.getTime() );
    }

    public useDeadline( deadline: string ): { text: string; status: 'upcoming' | 'soon' | 'last_day' | 'expired_yesterday' | 'expired' | 'today' | 'unknown' }
    {
        if ( !deadline )
        {
            return { text: '', status: 'unknown' }
        }

        const deadlineDateRaw = new Date( deadline )
        const nowDate = this.getNowDate()
        const deadlineDate = new Date(
            deadlineDateRaw.getFullYear(),
            deadlineDateRaw.getMonth(),
            deadlineDateRaw.getDate()
        )
        const now = new Date( nowDate.getFullYear(), nowDate.getMonth(), nowDate.getDate() )
        const msPerDay = 1000 * 60 * 60 * 24
        const days_left = Math.floor( ( deadlineDate.getTime() - now.getTime() ) / msPerDay )
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
            case days_left === -1:
                text = 'Berakhir kemarin'
                status = 'expired_yesterday'
                break
            case days_left < -1:
                text = `Berakhir ${ Math.abs( days_left ) } hari lalu`
                status = 'expired'
                break
            default:
                text = 'Berakhir hari ini'
                status = 'today'
        }
        return { text, status }
    }

    public useTimeAgo( postTime: string ): string
    {
        if ( !postTime ) return ''

        const postDate = new Date( postTime )
        if ( isNaN( postDate.getTime() ) ) return ''
        const nowDate = this.getNowDate()
        const diff = Math.floor( ( nowDate.getTime() - postDate.getTime() ) / 1000 )

        if ( diff < 60 ) return 'Baru saja diposting'
        if ( diff < 3600 ) return `${ Math.floor( diff / 60 ) } menit lalu`
        if ( diff < 86400 ) return `${ Math.floor( diff / 3600 ) } jam lalu`
        if ( diff < 604800 ) return `${ Math.floor( diff / 86400 ) } hari lalu`
        if ( diff < 2592000 ) return `${ Math.floor( diff / 604800 ) } minggu lalu`
        if ( diff < 31536000 ) return `${ Math.floor( diff / 2592000 ) } bulan lalu`
        return `${ Math.floor( diff / 31536000 ) } tahun lalu`
    }

    public useStatusJob( status_pekerjaan: StatusPekerjaanNumber ): { label: string; status: StatusPekerjaanString | '' }
    {
        if ( typeof status_pekerjaan !== 'number' ) throw new Error( 'status_pekerjaan must be a number' );
        switch ( status_pekerjaan )
        {
            case 2:
                return {
                    label: 'Urgent',
                    status: 'Urgent',
                }
            case 3:
                return {
                    label: 'Pinned',
                    status: 'Pinned',
                }
            default:
                return {
                    label: '',
                    status: '',
                }
        }
    }

    public useSummaryJob( jobdata?: JobSummary | null ): SummaryRow[]
    {
        if ( typeof jobdata !== 'object' || jobdata === null ) throw new Error( 'jobdata must be a non-null object' );
        const rows: SummaryRow[] = []
        const data: JobSummary = ( jobdata ?? {} ) as JobSummary

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
                value: this._formatHelper.deadlineFormat( data[ 'deadline' ] ),
            } )
        }

        return rows
    }

    /**
 * Provides a shared reactive clock that updates every minute, allowing multiple components to synchronize time-based displays (e.g., "time ago", deadlines) without setting up individual intervals. Usage: Call `const stopClock = generalStore.useSharedClock();` in a component's effect, and call `stopClock()` in the cleanup function to avoid memory leaks.
 * @see this.useTimeAgo()
 * @see this.useDeadline()
 */
    public useSharedClock(): () => void
    {

        const startTimeEffect = (): void =>
        {
            this.#refCount += 1;

            if ( !this.#intervalId )
            {
                this.#intervalId = setInterval( () =>
                {
                    const now = Date.now();
                    this.now.setTime( now );
                }, 60000 ); // Update every minute
            }
        };

        const stopTimeEffect = (): void =>
        {
            this.#refCount = Math.max( this.#refCount - 1, 0 );
            if ( this.#refCount === 0 && this.#intervalId )
            {
                clearInterval( this.#intervalId );
                this.#intervalId = null;
            }
        };

        function timeEffect(): () => void
        {
            startTimeEffect();

            // Return a cleanup function to clear the interval when the component is destroyed
            return (): void =>
            {
                stopTimeEffect();
            };
        };

        return timeEffect;
    }

    /**
     * Helper inner class to format various job-related data fields (e.g., salary, age, deadlines, social media) into user-friendly strings. This class centralizes all formatting logic for the GeneralStore, ensuring consistency across different components that display job information. The methods in this class handle specific formatting rules, such as converting salary ranges into localized currency formats or formatting deadlines into readable date strings. By encapsulating this logic in a dedicated helper class, the GeneralStore can maintain a clear separation of concerns, with the main store methods focusing on data orchestration and the helper class handling presentation-specific formatting details.
     * @class FormatHelper
     * @internal This inner class is intended for internal use within the GeneralStore and is not exposed as part of the public API. It serves as a utility to support various formatting needs in the store, such as those required by the `useSummaryJob` method, which is the main interface for generating formatted job summary data for display in the UI.
     * ! Only use inner class if solely used on a single place and only for helping parent class
     * @see this.useSummaryJob()
     */
    private _formatHelper = new ( class FormatHelper
    {

        private readonly indonesianMonths = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        public deadlineFormat( dateStr?: string | null ): string
        {
            if ( !dateStr ) return '';
            const date = new Date( dateStr );
            if ( isNaN( date.getTime() ) ) return dateStr;
            const day = date.getDate();
            const month = date.getMonth();
            const year = date.getFullYear();
            return `${ day } ${ this.indonesianMonths[ month ] } ${ year }`;
        }

        public formatAge( umur_min?: number, umur_max?: number ): string | null
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
        }

        public formatSalary( gaji_minimal?: number, gaji_maksimal?: number ): string | null
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
        }
    } )();
}
export const generalJobStore = new GeneralJobStore();