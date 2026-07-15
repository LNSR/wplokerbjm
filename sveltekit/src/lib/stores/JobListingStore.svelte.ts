import { APIServiceBrowser, APIServiceShared } from '@/services/graphql/APIService'
import type {
    SearchFilters,
    CardJob,
    JobGridProps,
    SearchResponse,
    SortOption,
    SearchContext,
    SearchTitle,
} from '@/types'
import { SearchUtils } from '@/utils/search';
import { routeStateStore } from '$lib/stores/Route.svelte';
/**
 * Handle state and actions related to job search and job grid display.
 * Both components need each other to function properly, so they are combined into a single store for better state management
 */
class JobListingStore
{
    // State
    private searchHistory = $state<string[]>([])
    public jobs = $state<CardJob[]>([])
    public context = $state<SearchContext>("latest") // default context at initial load for jobgrid
    public title = $state<SearchTitle>("Lowongan Terbaru") // default context at initial load for jobgrid
    public totalJobs = $state<JobGridProps[ "total" ]>(0)
    public maxNumPages = $state<JobGridProps[ "maxNumPages" ]>(1)
    public page = $state<number>(1)

    public loading = $state(false)
    public error = $state<string | null>(null)

    public filters = $state<SearchFilters>({
        cari: null,
        'lokasi_pekerjaan': [],
        'gender': [],
        'pendidikan': [],
        sort: { value: 'desc', label: 'Terbaru' } as SortOption,
        context: this.context,
    })

    // Computed helpers
    public get hasFilters(): boolean
    {
        const f = this.filters
        return !!(
            (typeof f.cari === 'string' && f.cari.trim() !== '') ||
            (Array.isArray(f[ 'lokasi_pekerjaan' ]) && f[ 'lokasi_pekerjaan' ].length > 0) ||
            (Array.isArray(f[ 'gender' ]) && f[ 'gender' ].length > 0) ||
            (Array.isArray(f[ 'pendidikan' ]) && f[ 'pendidikan' ].length > 0) ||
            f.sort?.value === 'asc' || f.sort?.value === 'desc'
        )
    }

    public get recentSearches(): string[]
    {
        return this.searchHistory.slice(0, 5)
    }

    public get hasMore(): boolean
    {
        return this.page < this.maxNumPages!
    }


    // Actions
    public setFilters(newFilters: Partial<SearchFilters>): void
    {
        const sanitized: Partial<SearchFilters> = { ...newFilters }
        if (typeof newFilters.cari === 'string') sanitized.cari = SearchUtils.sanitizeString(newFilters.cari)

        this.filters.cari = typeof sanitized.cari === 'string' ? sanitized.cari : this.filters.cari
        this.filters[ 'lokasi_pekerjaan' ] = SearchUtils.sanitizeArr(newFilters[ 'lokasi_pekerjaan' ]) ?? this.filters[ 'lokasi_pekerjaan' ]
        this.filters[ 'gender' ] = SearchUtils.sanitizeArr(newFilters[ 'gender' ]) ?? this.filters[ 'gender' ]
        this.filters[ 'pendidikan' ] = SearchUtils.sanitizeArr(newFilters[ 'pendidikan' ]) ?? this.filters[ 'pendidikan' ]
        if (newFilters.sort && typeof newFilters.sort === 'object')
        {
            this.filters.sort = { value: newFilters.sort.value, label: newFilters.sort.label }
        }
        this.filters.context = newFilters.context ?? this.filters.context
    }

    public resetFilters(): void
    {
        this.filters.cari = ''
        this.filters[ 'lokasi_pekerjaan' ] = []
        this.filters[ 'gender' ] = []
        this.filters[ 'pendidikan' ] = []
        this.filters.sort = { value: 'desc', label: 'Terbaru' }
        this.filters.context = "latest"
    }

    private addToHistory(query: string): void
    {
        if (query && !this.searchHistory.includes(query))
        {
            this.searchHistory.unshift(query)
            if (this.searchHistory.length > 10) this.searchHistory = this.searchHistory.slice(0, 10)
        }
    }

    /**
     * Execute a job search based on the current filters
     */
    public async searchJobs(): Promise<SearchResponse>
    {
        this.loading = true
        this.error = null
        routeStateStore.clearCardHeights("jobGrid");
        try
        {
            const cleaned = SearchUtils.sanitizeFilters({ ...this.filters })
            const response = await APIServiceBrowser.searchJobsGraphQL(cleaned)
            this.jobs = [ ...(response.jobs || []) ]
            this.context = "search"
            this.title = response.title || "Hasil Pencarian"
            this.totalJobs = response.total || 0
            this.maxNumPages = response.maxNumPages || 1
            this.page = 1
            if (cleaned.cari) this.addToHistory(String(cleaned.cari))
            return response
        } catch (err)
        {
            this.error = err instanceof Error ? err.message : 'Search failed'
            throw err
        } finally
        {
            this.loading = false
        }
    }

    /**
     * Depending on the current context, either re-run the search query or fetch the latest job grid data. Clears the job grid card heights cache to ensure proper virtualization after data refresh.
     */
    public async refreshJobGrid(): Promise<JobGridProps | SearchResponse>
    {
        let response: JobGridProps | SearchResponse
        routeStateStore.clearCardHeights("jobGrid");
        try
        {
            if (this.context === "search")
            {
                return response = await this.searchJobs()
            }

            const cleaned = SearchUtils.sanitizeFilters({ ...this.filters })
            response = await APIServiceShared.fetchJobGridGraphQL({
                paged: 1,
                context: this.context,
                title: this.title,
                total_jobs: 0,
                ...cleaned,
            })

            this.jobs = response.jobs || []
            this.maxNumPages = response.maxNumPages || 1
            this.context = "latest"
            this.title = response.title || "Lowongan Terbaru"
            this.totalJobs = response.total || 0
            if (response.filters)
            {
                this.setFilters(response.filters)
            }
            this.page = 1
            this.error = null
            return response
        } catch (err)
        {
            this.error = err instanceof Error ? err.message : "Failed to refresh job grid"
            throw err
        }
    }
}
export const jobListingStore = new JobListingStore();