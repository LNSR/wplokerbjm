import type { SortOption, Job, JobSummary, JobContactRow, CardJob } from '@/types';

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

// API response metadata from headers
export interface ApiMeta {
  total?: number
  totalPages?: number
  links?: Record<string, string>
}

// Generic API response wrapper
export interface ApiResponse<T> {
  data: T
  meta: ApiMeta
}

// * Base response for SearchResponse and LoadMoreResponse
export interface BaseJobResponse {
  jobs: Job[]
  context?: SearchContext
  filters?: SearchFilters
  meta?: ApiMeta
}

// Extended response for initial search operations
export interface SearchResponse extends BaseJobResponse {
  title?: string
  shouldScroll?: boolean
}

// Response for pagination operations
export interface LoadMoreResponse extends BaseJobResponse { }

// Simplified load more filters - flattened structure
export interface LoadMoreFilters extends Partial<SearchFilters> {
  page: number
  context?: SearchContext
}

export interface AutoSuggestResponse extends Array<string> { }

export type BookmarkedJobsResponse = CardJob[];

export enum TaxonomyType {
  lokasi = 'lokasi',
  gender = 'gender',
  pendidikan = 'pendidikan'
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

export interface SingleOverlayResponse {
  id?: number;
  duplicateNonce?: string; // Nonce for plugin 'Duplicate post as draft'
  title: string;
  namaPerusahaan: string;
  tentangPerusahaan: string;
  ringkasanPekerjaan: JobSummary;
  deskripsiPekerjaan: string;
  persyaratan: string;
  caraMelamar: string;
  benefit: string;
  contacts: JobContactRow;
  social_media: Record<string, string | string[]>;
  post_time: string;
}