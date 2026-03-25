import { APIService } from '@/services/APIService'
import { taxonomyStore } from './Taxonomy.svelte'
import { debounce, type DebouncedFunction, validation } from '@/utils'
import type {
    SearchFilters,
    CardJob,
    JobGridProps,
    LoadMoreResponse,
    SearchResponse,
    TaxonomyTerm,
    SortOption,
} from '@/types'
import type { SearchContext, SearchTitle, DropdownOption, TaxonomyType } from '@/types'

export class SearchManager {
    // State
    public searchHistory = $state<string[]>([])
    public suggestions = $state<string[]>([])
    public showSuggestions = $state(false)
    public jobs = $state<CardJob[]>([])
    public context = $state<SearchContext>("latest") // default context at initial load for jobgrid
    public title = $state<SearchTitle>("Lowongan Terbaru") // default context at initial load for jobgrid
    public totalJobs = $state<JobGridProps["total"]>(0)
    public maxNumPages = $state<JobGridProps["maxNumPages"]>(1)
    public page = $state<number>(1)

    public loading = $state(false)
    public error = $state<string | null>(null)
    public suggestionsLoading = $state(false)
    public selectedSuggestionIndex = $state(-1)

    // Load more cache for CLS-free loading, avoid dynamically append list cards to DOM which triggered unfair CLS assessment
    public nextPageLoadMoreCache = $state<CardJob[] | null>(null)
    public isPrefetchingLoadMore = $state(false)

    public filters = $state<SearchFilters>({
        cari: null,
        'lokasi_pekerjaan': [],
        'gender': [],
        'pendidikan': [],
        sort: { value: 'desc', label: 'Terbaru' } as SortOption,
        context: this.context,
    })

    // Computed helpers
    public get hasFilters(): boolean {
        const f = this.filters
        return !!(
            (typeof f.cari === 'string' && f.cari.trim() !== '') ||
            (Array.isArray(f['lokasi_pekerjaan']) && f['lokasi_pekerjaan'].length > 0) ||
            (Array.isArray(f['gender']) && f['gender'].length > 0) ||
            (Array.isArray(f['pendidikan']) && f['pendidikan'].length > 0) ||
            f.sort?.value === 'asc' || f.sort?.value === 'desc'
        )
    }

    public get recentSearches(): string[] {
        return this.searchHistory.slice(0, 5)
    }

    public get hasSuggestions(): boolean {
        return this.suggestions.length > 0
    }

    public get hasMore(): boolean {
        return this.page < this.maxNumPages!
    }

    // Debounced suggestions
    private debouncedGetSuggestions: DebouncedFunction<(query: SearchFilters['cari']) => Promise<void>> = debounce(async (query: SearchFilters['cari']) => {
        const cleanQuery = validation.sanitizeString(String(query))
        if (validation.isValidQuery(cleanQuery)) {
            this.suggestionsLoading = true
            try {
                const data = await APIService.getAutoSuggestionsGraphQL(cleanQuery)
                this.suggestions = data || []
                this.showSuggestions = this.suggestions.length > 0
            } catch {
                this.suggestions = []
                this.showSuggestions = false
            } finally {
                this.suggestionsLoading = false
            }
        } else {
            this.suggestions = []
            this.showSuggestions = false
        }
    }, 500)

    // Actions
    public setFilters(newFilters: Partial<SearchFilters>): void {
        const sanitized: Partial<SearchFilters> = { ...newFilters }
        if (typeof newFilters.cari === 'string') sanitized.cari = validation.sanitizeString(newFilters.cari)

        this.filters.cari = typeof sanitized.cari === 'string' ? sanitized.cari : this.filters.cari
        this.filters['lokasi_pekerjaan'] = SearchUtils.sanitizeArr(newFilters['lokasi_pekerjaan']) ?? this.filters['lokasi_pekerjaan']
        this.filters['gender'] = SearchUtils.sanitizeArr(newFilters['gender']) ?? this.filters['gender']
        this.filters['pendidikan'] = SearchUtils.sanitizeArr(newFilters['pendidikan']) ?? this.filters['pendidikan']
        if (newFilters.sort && typeof newFilters.sort === 'object') {
            this.filters.sort = { value: newFilters.sort.value, label: newFilters.sort.label }
        }
        this.filters.context = newFilters.context ?? this.filters.context
    }

    public resetFilters(): void {
        this.filters.cari = ''
        this.filters['lokasi_pekerjaan'] = []
        this.filters['gender'] = []
        this.filters['pendidikan'] = []
        this.filters.sort = { value: 'desc', label: 'Terbaru' }
        this.filters.context = "latest"
    }

    public addToHistory(query: string): void {
        if (query && !this.searchHistory.includes(query)) {
            this.searchHistory.unshift(query)
            if (this.searchHistory.length > 10) this.searchHistory = this.searchHistory.slice(0, 10)
        }
    }

    public clearHistory(): void {
        this.searchHistory = []
    }

    public getSuggestions(query: SearchFilters['cari']): void {
        this.debouncedGetSuggestions(query)
    }

    public selectSuggestion(suggestion: string): void {
        this.filters.cari = validation.sanitizeString(suggestion)
        this.showSuggestions = false
        this.suggestions = []
    }

    public hideSuggestions(): void {
        setTimeout(() => {
            this.showSuggestions = false
        }, 150)
    }

    public async searchJobs(): Promise<SearchResponse> {
        this.loading = true
        this.error = null
        try {
            const cleaned = SearchUtils.sanitizeFilters({ ...this.filters })
            const response = await APIService.searchJobsGraphQL(cleaned)
            this.jobs = [...(response.jobs || [])]
            this.context = (response.context) || "search"
            this.title = response.title || "Hasil Pencarian"
            this.totalJobs = response.total || 0
            this.maxNumPages = response.maxNumPages || 1
            this.page = 1
            if (cleaned.cari) this.addToHistory(String(cleaned.cari))
            return response
        } catch (err) {
            this.error = err instanceof Error ? err.message : 'Search failed'
            throw err
        } finally {
            this.loading = false
        }
    }

    public async loadMore(retries = 2): Promise<LoadMoreResponse> {
        if (this.loading || this.page >= this.maxNumPages!) {
            throw new Error('Cannot load more: already loading or no more pages')
        }

        this.loading = true
        this.error = null
        try {
            const paged = this.page + 1
            const context = this.context
            const filters = SearchUtils.sanitizeFilters({ ...this.filters })

            const loadMoreFilters = {
                paged,
                context,
                ...filters,
            }

            const response = await APIService.loadMoreJobsGraphQL(loadMoreFilters)

            if (Array.isArray(response.jobs) && response.jobs.length) {
                // Filter out jobs that already exist (by permalink) to prevent duplicates
                const newJobs = response.jobs.filter(newJob =>
                    !this.jobs.some(existingJob => existingJob.permalink === newJob.permalink)
                );
                this.jobs.push(...newJobs)
                this.page = paged
                this.maxNumPages = response.maxNumPages || this.maxNumPages
            } else {
                this.page = this.maxNumPages!
            }
            return response
        } catch (err) {
            console.error('SearchStore: Load more failed:', err);
            this.error = err instanceof Error ? err.message : 'Load more failed'

            // Retry logic
            if (retries > 0) {
                console.log(`Retrying loadMore, attempts left: ${retries}`)
                await new Promise(resolve => setTimeout(resolve, 1000))  // Simple delay
                return this.loadMore(retries - 1)
            }

            throw err
        } finally {
            this.loading = false
        }
    }

    public async prefetchNextPage(): Promise<void> {
        if (this.isPrefetchingLoadMore || this.page >= this.maxNumPages! || this.nextPageLoadMoreCache) {
            return
        }

        this.isPrefetchingLoadMore = true
        this.error = null

        const paged = this.page + 1
        const context = this.context
        const filters = SearchUtils.sanitizeFilters({ ...this.filters })

        const loadMoreFilters = {
            paged,
            context,
            ...filters,
        }

        const TIMEOUT_MS = 6000

        try {
            let timedOut = false
            const apiPromise = APIService.loadMoreJobsGraphQL(loadMoreFilters)
            const timeoutPromise = new Promise(resolve => setTimeout(() => {
                timedOut = true
                resolve({ __timedOut: true })
            }, TIMEOUT_MS))

            const response: unknown = await Promise.race([apiPromise, timeoutPromise])

            if (timedOut) {
                console.warn('SearchStore: Prefetch timed out, performing manual fetch')
                try {
                    const manualResponse: LoadMoreResponse = await APIService.loadMoreJobsGraphQL(loadMoreFilters)
                    if (Array.isArray(manualResponse.jobs) && manualResponse.jobs.length) {
                        const newJobs = manualResponse.jobs.filter((newJob: any) =>
                            !this.jobs.some(existingJob => existingJob.permalink === newJob.permalink)
                        )
                        this.nextPageLoadMoreCache = newJobs
                        this.maxNumPages = manualResponse.maxNumPages || this.maxNumPages
                    } else {
                        this.page = this.maxNumPages!
                    }
                } catch (err) {
                    console.error('SearchStore: Manual prefetch fetch failed:', err)
                    this.error = err instanceof Error ? err.message : 'Prefetch manual fetch failed'
                }
            } else {
                if (response && Array.isArray((response as LoadMoreResponse).jobs) && (response as LoadMoreResponse).jobs.length) {
                    const newJobs = (response as LoadMoreResponse).jobs.filter((newJob: any) =>
                        !this.jobs.some(existingJob => existingJob.permalink === newJob.permalink)
                    )
                    this.nextPageLoadMoreCache = newJobs
                    this.maxNumPages = (response as LoadMoreResponse).maxNumPages || this.maxNumPages
                } else {
                    this.page = this.maxNumPages!
                }
            }
        } catch (err) {
            console.error('SearchStore: Prefetch failed:', err);
            this.error = err instanceof Error ? err.message : 'Prefetch failed'
        } finally {
            this.isPrefetchingLoadMore = false
        }
    }

    public appendCachedPage(): void {
        if (!this.nextPageLoadMoreCache) {
            return
        }

        this.jobs.push(...this.nextPageLoadMoreCache)
        this.page++
        this.nextPageLoadMoreCache = null
    }

    public get selectedFiltersWithNames() {
        // No empty-string sentinel anymore; ignore any blank values here
        const filters: {
            key: TaxonomyType
            label: string
            values: string[]
            names: string[]
        }[] = []

        if (this.filters['lokasi_pekerjaan'] && this.filters['lokasi_pekerjaan'].length) {
            const filtered = this.filters['lokasi_pekerjaan'].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: 'lokasi_pekerjaan',
                    label: 'Lokasi',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug('lokasi_pekerjaan', slug)),
                })
            }
        }

        if (this.filters['gender'] && this.filters['gender'].length) {
            const filtered = this.filters['gender'].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: 'gender',
                    label: 'Gender',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug('gender', slug)),
                })
            }
        }

        if (this.filters['pendidikan'] && this.filters['pendidikan'].length) {
            const filtered = this.filters['pendidikan'].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: 'pendidikan',
                    label: 'Pendidikan',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug('pendidikan', slug)),
                })
            }
        }

        return filters
    }
}

export class SearchUtils {
    static sanitizeArr(arrOrVal: unknown): string[] | undefined {
        if (Array.isArray(arrOrVal)) {
            // sanitize each item and remove any empty/blank results
            return arrOrVal
                .map((v) => {
                    if (typeof v === 'string') return validation.sanitizeString(v)
                    if (typeof v === 'number') return String(v)
                    return validation.sanitizeString(String(v ?? ''))
                })
                .filter((s) => typeof s === 'string' && String(s).trim() !== '') as string[]
        }
        if (typeof arrOrVal === 'string') {
            const s = validation.sanitizeString(arrOrVal)
            return s.trim() ? [s] : []
        }
        return undefined
    }

    static sanitizeFilters(f: SearchFilters): SearchFilters {
        return {
            ...f,
            cari: typeof f.cari === 'string' ? validation.sanitizeString(f.cari) : f.cari,
            ['lokasi_pekerjaan']: Array.isArray(f['lokasi_pekerjaan'])
                ? f['lokasi_pekerjaan']
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f['lokasi_pekerjaan'],
            ['gender']: Array.isArray(f['gender'])
                ? f['gender']
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f['gender'],
            ['pendidikan']: Array.isArray(f['pendidikan'])
                ? f['pendidikan']
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f['pendidikan'],
            sort: f.sort ? { value: f.sort.value, label: f.sort.label } : f.sort,
            context: f.context,
        }
    }

    static mapTerms(terms: TaxonomyTerm[], placeholder = 'Semua'): DropdownOption[] {
        return terms.map((t) => ({
            value: t.slug,
            label: t.name,
            children: t.children ? SearchUtils.mapTerms(t.children, placeholder) : undefined,
        }))
    }
}

export const searchStore = new SearchManager()
