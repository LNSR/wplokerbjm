import type {
    SearchFilters,
    TaxonomyTerm,
    DropdownOption,
    TaxonomyType
} from '@/types'
/**
 * Utility class for search-related functions, such as sanitizing filters and mapping taxonomy terms to dropdown options.
 * This class provides static methods that can be used across the application wherever search functionality is needed,
 * ensuring consistent handling of search data and taxonomy terms. The methods in this class focus on data transformation
 * and sanitization, making it easier to work with search filters and taxonomy data.
 */
export class SearchUtils {
    static isValidQuery(query: string, minLength = 2): boolean {
        return typeof query === 'string' && query.trim().length >= minLength
    }

    static sanitizeString(str: string): string {
        return str.trim().replace(/[<>]/g, '')
    }

    static sanitizeArr(arrOrVal: unknown): string[] | undefined {
        if (Array.isArray(arrOrVal)) {
            // sanitize each item and remove any empty/blank results
            return arrOrVal
                .map((v) => {
                    if (typeof v === 'string') return SearchUtils.sanitizeString(v)
                    if (typeof v === 'number') return String(v)
                    return SearchUtils.sanitizeString(String(v ?? ''))
                })
                .filter((s) => typeof s === 'string' && String(s).trim() !== '') as string[]
        }
        if (typeof arrOrVal === 'string') {
            const s = SearchUtils.sanitizeString(arrOrVal)
            return s.trim() ? [s] : []
        }
        return undefined
    }

    static sanitizeFilters(f: SearchFilters): SearchFilters {
        return {
            ...f,
            cari: typeof f.cari === 'string' ? SearchUtils.sanitizeString(f.cari) : f.cari,
            ['lokasi_pekerjaan']: Array.isArray(f['lokasi_pekerjaan'])
                ? f['lokasi_pekerjaan']
                    .map((v) => (typeof v === 'string' ? SearchUtils.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f['lokasi_pekerjaan'],
            ['gender']: Array.isArray(f['gender'])
                ? f['gender']
                    .map((v) => (typeof v === 'string' ? SearchUtils.sanitizeString(v) : String(v)))
                    .filter((s) => String(s).trim() !== '')
                : f['gender'],
            ['pendidikan']: Array.isArray(f['pendidikan'])
                ? f['pendidikan']
                    .map((v) => (typeof v === 'string' ? SearchUtils.sanitizeString(v) : String(v)))
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

    static normalizeStringOrArray(input?: string | string[] | null): string[] {
        if (Array.isArray(input)) {
            return input
                .map((item) => String(item).trim())
                .filter((v) => v !== '');
        }
        if (typeof input === 'string') {
            const trimmed = input.trim();
            return trimmed ? [trimmed] : [];
        }
        return [];
    }

    static sanitizeTaxonomyValue(value: unknown): string[] {
        const arr = SearchUtils.normalizeStringOrArray(value as string | string[] | null)
        return arr.filter((v) => v !== '')
    }

    static getTaxonomyLabel(
        key: TaxonomyType,
        values: unknown,
        taxonomyStore: {getTermNameBySlug: (key: TaxonomyType, slug: string) => string},
        emptyLabel: string,
    ): string {
        const arr = SearchUtils.sanitizeTaxonomyValue(values)
        if (arr.length === 0) return emptyLabel
        if (arr.length === 1) return taxonomyStore.getTermNameBySlug(key, arr[0])
        return `${arr.length} filter dipilih`
    }
}
