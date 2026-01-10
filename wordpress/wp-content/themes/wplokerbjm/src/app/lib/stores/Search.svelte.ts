import { APIService } from '@/services/APIService'
import { taxonomyStore } from './Taxonomy.svelte'
import { debounce, validation } from '@/utils'
import type {
    SearchFilters,
    LoadMoreFilters,
    CardJob,
    LoadMoreResponse,
    SearchResponse,
    TaxonomyTerm,
    SortOption,
} from '@/types'
import { SearchContext, SearchTitle } from '@/types'
import { TaxonomyType } from '@/types'
import type { DropdownOption } from '@/types'

export class SearchManager {
    // State
    public searchHistory = $state<string[]>([])
    public suggestions = $state<string[]>([])
    public showSuggestions = $state(false)
    public jobs = $state<CardJob[]>([])
    public context = $state<SearchContext>(SearchContext.Latest) // default context at initial load for jobgrid
    public title = $state<SearchTitle>(SearchTitle.Latest) // default context at initial load for jobgrid
    public totalJobs = $state(0)
    public maxNumPages = $state(1)
    public page = $state(1)

    public loading = $state(false)
    public error = $state<string | null>(null)
    public suggestionsLoading = $state(false)
    public selectedSuggestionIndex = $state(-1)

    public filters = $state<SearchFilters>({
        cari: '',
        [TaxonomyType.lokasi]: [],
        [TaxonomyType.gender]: [],
        [TaxonomyType.pendidikan]: [],
        sort: { value: 'desc', label: 'Terbaru' } as SortOption,
        context: this.context,
    })

    // Computed helpers
    public get hasFilters(): boolean {
        const f = this.filters
        return !!(
            (typeof f.cari === 'string' && f.cari.trim() !== '') ||
            (Array.isArray(f[TaxonomyType.lokasi]) && f[TaxonomyType.lokasi].length > 0) ||
            (Array.isArray(f[TaxonomyType.gender]) && f[TaxonomyType.gender].length > 0) ||
            (Array.isArray(f[TaxonomyType.pendidikan]) && f[TaxonomyType.pendidikan].length > 0) ||
            f.sort.value === 'asc' || f.sort.value === 'desc'
        )
    }

    public get recentSearches(): string[] {
        return this.searchHistory.slice(0, 5)
    }

    public get hasSuggestions(): boolean {
        return this.suggestions.length > 0
    }

    public get hasMore(): boolean {
        return this.page < this.maxNumPages
    }

    // Debounced suggestions
    private debouncedGetSuggestions = debounce(async (query: string) => {
        const cleanQuery = validation.sanitizeString(query)
        if (validation.isValidQuery(cleanQuery)) {
            this.suggestionsLoading = true
            try {
                const data = await APIService.getAutoSuggestions(cleanQuery)
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
        this.filters[TaxonomyType.lokasi] = SearchUtils.sanitizeArr(newFilters[TaxonomyType.lokasi]) ?? this.filters[TaxonomyType.lokasi]
        this.filters[TaxonomyType.gender] = SearchUtils.sanitizeArr(newFilters[TaxonomyType.gender]) ?? this.filters[TaxonomyType.gender]
        this.filters[TaxonomyType.pendidikan] = SearchUtils.sanitizeArr(newFilters[TaxonomyType.pendidikan]) ?? this.filters[TaxonomyType.pendidikan]
        this.filters.sort = typeof newFilters.sort === 'object' && newFilters.sort !== null ? (newFilters.sort as SortOption) : this.filters.sort
        this.filters.context = newFilters.context ?? this.filters.context
    }

    public resetFilters(): void {
        this.filters.cari = ''
        this.filters[TaxonomyType.lokasi] = []
        this.filters[TaxonomyType.gender] = []
        this.filters[TaxonomyType.pendidikan] = []
        this.filters.sort = { value: 'desc', label: 'Terbaru' }
        this.filters.context = SearchContext.Latest
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

    public getSuggestions(query: string): void {
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
            const response = await APIService.searchJobs(cleaned)
            this.jobs = [...(response.jobs || [])]
            this.context = (response.context as SearchContext) || SearchContext.Search
            this.title = response.title || SearchTitle.Search
            this.totalJobs = response.meta?.total || 0
            this.maxNumPages = response.meta?.totalPages || 1
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
        if (this.loading || this.page >= this.maxNumPages) {
            throw new Error('Cannot load more: already loading or no more pages')
        }

        this.loading = true
        this.error = null
        try {
            const loadMoreFilters: LoadMoreFilters = {
                paged: this.page + 1,
                context: this.context,
                ...SearchUtils.sanitizeFilters({ ...this.filters }),
            }

            const response = await APIService.loadMoreJobs(loadMoreFilters)

            if (Array.isArray(response.jobs) && response.jobs.length) {
                // Filter out jobs that already exist (by permalink) to prevent duplicates
                const newJobs = response.jobs.filter(newJob =>
                    !this.jobs.some(existingJob => existingJob.permalink === newJob.permalink)
                );
                this.jobs.push(...newJobs)
                this.page = loadMoreFilters.paged
                this.maxNumPages = response.meta?.totalPages || this.maxNumPages
            } else {
                this.page = this.maxNumPages
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

    public get selectedFiltersWithNames() {
        // No empty-string sentinel anymore; ignore any blank values here
        const filters: {
            key: TaxonomyType
            label: string
            values: string[]
            names: string[]
        }[] = []

        if (this.filters[TaxonomyType.lokasi] && this.filters[TaxonomyType.lokasi].length) {
            const filtered = this.filters[TaxonomyType.lokasi].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: TaxonomyType.lokasi,
                    label: 'Lokasi',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug(TaxonomyType.lokasi, slug)),
                })
            }
        }

        if (this.filters[TaxonomyType.gender] && this.filters[TaxonomyType.gender].length) {
            const filtered = this.filters[TaxonomyType.gender].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: TaxonomyType.gender,
                    label: 'Gender',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug(TaxonomyType.gender, slug)),
                })
            }
        }

        if (this.filters[TaxonomyType.pendidikan] && this.filters[TaxonomyType.pendidikan].length) {
            const filtered = this.filters[TaxonomyType.pendidikan].filter((slug) => typeof slug === 'string' && String(slug).trim() !== '')
            if (filtered.length) {
                filters.push({
                    key: TaxonomyType.pendidikan,
                    label: 'Pendidikan',
                    values: filtered,
                    names: filtered.map((slug) => taxonomyStore.getTermNameBySlug(TaxonomyType.pendidikan, slug)),
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
            [TaxonomyType.lokasi]: Array.isArray(f[TaxonomyType.lokasi])
                ? f[TaxonomyType.lokasi]
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f[TaxonomyType.lokasi],
            [TaxonomyType.gender]: Array.isArray(f[TaxonomyType.gender])
                ? f[TaxonomyType.gender]
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f[TaxonomyType.gender],
            [TaxonomyType.pendidikan]: Array.isArray(f[TaxonomyType.pendidikan])
                ? f[TaxonomyType.pendidikan]
                    .map((v) => (typeof v === 'string' ? validation.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f[TaxonomyType.pendidikan],
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
