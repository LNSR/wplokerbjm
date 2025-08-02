import type { SortOption, Job } from './index'

// Base filters for search operations
export interface SearchFilters {
  cari: string
  lokasi: string[]
  gender: string[]
  pendidikan: string[]
  sort: SortOption
}

// Context type for search operations
export type SearchContext = 'search' | 'archive' | 'latest'

// Base response structure for job operations
export interface BaseJobResponse {
  jobs: Job[]
  totalJobs: number
  maxNumPages: number
  context?: SearchContext
  filters?: SearchFilters
}

// Extended response for initial search operations
export interface SearchResponse extends BaseJobResponse {
  title?: string
  shouldScroll?: boolean
}

// Response for pagination operations (keeps it simple)
export interface LoadMoreResponse {
  jobs: Job[]
  pagination: {
    current: number
    max: number
  }
  context?: SearchContext
  filters?: Partial<SearchFilters>
}

// Simplified load more filters - flattened structure
export interface LoadMoreFilters extends Partial<SearchFilters> {
  page: number
  context?: SearchContext
}

export interface AutoSuggestResponse extends Array<string> {}


// Standardized taxonomy term interface
export interface TaxonomyTerm {
  slug: string
  name: string
  parent?: number
  children?: TaxonomyTerm[]
}

// API response for taxonomy terms
export interface TaxonomyTermsResponse {
  lokasiTerms: TaxonomyTerm[]
  genderTerms: TaxonomyTerm[]
  pendidikanTerms: TaxonomyTerm[]
}

export interface SummaryRow {
  icon: string
  label: string
  value: string
}

export interface ContactRow {
  type: string
  icon: string
  label: string
  value: string
  href: string
}

export interface SocialMediaItem {
  platform: string;
  username: string;
  icon: string;
  url: string;
}

export interface SingleOverlayResponse {
  title: string;
  namaPerusahaan: string;
  tentangPerusahaan: string;
  deskripsiPekerjaan: string;
  persyaratan: string;
  caraMelamar: string;
  benefit: string;
  social_media: SocialMediaItem[];
  ringkasanPekerjaan: SummaryRow[];
  contacts: ContactRow[];
  post_time: string;
}