import type { Job } from './job'
import type { SortOption } from './index'

export interface SearchFilters {
  cari: string
  lokasi: string[]
  gender: string[]
  pendidikan: string[]
  sort: SortOption
}

export interface LoadMoreFilters {
  page: number
  context?: 'search' | 'archive'
  searchFilters?: SearchFilters
}

export interface SearchResponse {
  jobs: Job[]
  totalJobs: number
  maxNumPages: number
  context?: string
  filters?: SearchFilters
  title?: string
  shouldScroll?: boolean
}

export interface LoadMoreResponse {
  jobs: Job[]
  totalJobs?: number
  maxNumPages?: number
  context?: string
  filters?: SearchFilters
}

export interface AutoSuggestResponse extends Array<string> {}

export interface ApiErrorResponse {
  message: string
  status: number
  code?: string
}

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