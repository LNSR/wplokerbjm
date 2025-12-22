import type { SortOption, CardJob, JobSummary, JobContactRow, MetaBox, WPBasePost, WPTaxonomyTerm } from '@/types';

// Base filters for search operations
export interface SearchFilters extends Pick<MetaBox, 'lokasi-pekerjaan' | 'gender' | 'pendidikan'> {
  cari: string
  'lokasi-pekerjaan': string[]
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
export interface BaseJobSearchResponse {
  jobs: CardJob[]
  context?: SearchContext
  filters?: SearchFilters
  meta?: ApiMeta
}

// Extended response for initial search operations
export interface SearchResponse extends BaseJobSearchResponse {
  title?: string
  shouldScroll?: boolean
}

// Response for pagination operations
export interface LoadMoreResponse extends BaseJobSearchResponse { }

// Simplified load more filters - flattened structure
export interface LoadMoreFilters extends Partial<SearchFilters> {
  paged: number
  context?: SearchContext
}

// Standardized taxonomy term interface
export interface TaxonomyTerm extends Pick<WPTaxonomyTerm, 'slug' | 'name' | 'parent'> {
  children?: TaxonomyTerm[]
}

export interface SingleOverlayResponse extends WPBasePost, Pick<MetaBox, 'nama_perusahaan' | 'tentang_perusahaan' | 'deskripsi_pekerjaan' | 'persyaratan' | 'cara_melamar' | 'benefit' | 'social_media'> {
  duplicateNonce?: string; // Nonce for plugin 'Duplicate post as draft'
  ringkasanPekerjaan: JobSummary;
  contacts: JobContactRow;
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