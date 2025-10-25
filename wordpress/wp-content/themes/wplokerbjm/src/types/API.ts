import type { SortOption, Job, JobSummary, JobContactRow } from '@/types';

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
  paged: number
  context?: SearchContext
}


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

// RankMath head data interface
export interface HeadData {
  title?: string;
  description?: string;
  canonical?: string;
  robots?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string;
  og_locale?: string;
  og_type?: string;
  og_url?: string;
  og_site_name?: string;
  article_publisher?: string;
  og_updated_time?: string;
  og_video?: string;
  og_audio?: string;
  og_determiner?: string;
  twitter_title?: string;
  twitter_description?: string;
  twitter_image?: string;
  twitter_card?: string;
  twitter_label1?: string;
  twitter_data1?: string;
  twitter_label2?: string;
  twitter_data2?: string;
  twitter_site?: string;
  twitter_creator?: string;
  fb_app_id?: string;
  article_author?: string;
  article_published_time?: string;
  article_modified_time?: string;
  article_section?: string;
  article_tag?: string;
  author?: string;
  schema?: Record<string, any>;
}