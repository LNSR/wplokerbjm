import type { SortOption, CardJob, JobSummary, JobContactRow, MetaBox, WPBasePost, WPTaxonomyTerm } from '@/types';

// Base filters for search operations
export interface SearchFilters extends Pick<MetaBox, 'lokasi-pekerjaan' | 'gender' | 'pendidikan'> {
  cari: string
  'lokasi-pekerjaan'?: string[]
  gender?: string[]
  pendidikan?: string[]
  sort: SortOption
}

// Context type for search and loadMore operations
export enum SearchContext {
  Search = 'search',
  Latest = 'latest'
}

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
  title?: SearchTitle
  shouldScroll?: boolean
}

export enum SearchTitle {
  Latest = 'Lowongan Terbaru',
  Search = 'Hasil Pencarian'
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

export interface SingleOverlayResponse extends Pick<WPBasePost, 'id' | 'title' | 'post_time'>, Pick<MetaBox, 'nama_perusahaan' | 'tentang_perusahaan' | 'deskripsi_pekerjaan' | 'persyaratan' | 'cara_melamar' | 'benefit' | 'social_media'> {
  duplicateNonce?: string; // Nonce for plugin 'Duplicate post as draft'
  ringkasanPekerjaan: JobSummary;
  contacts?: JobContactRow;
  post_time: string;
}

// RankMath head data interface
export interface HeadData {
  title?: string;
  description?: string;
  canonical?: string;
  robots?: string;
  keywords?: string;
  author?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string;
  og_image_secure_url?: string;
  og_image_width?: string;
  og_image_height?: string;
  og_image_alt?: string;
  og_image_type?: string;
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
  // Twitter App Card fields
  twitter_app_name_iphone?: string;
  twitter_app_id_iphone?: string;
  twitter_app_url_iphone?: string;
  twitter_app_name_ipad?: string;
  twitter_app_id_ipad?: string;
  twitter_app_url_ipad?: string;
  twitter_app_name_googleplay?: string;
  twitter_app_id_googleplay?: string;
  twitter_app_url_googleplay?: string;
  twitter_app_description?: string;
  twitter_app_country?: string;
  // Twitter Player Card fields
  twitter_player?: string;
  twitter_player_width?: string;
  twitter_player_height?: string;
  twitter_player_stream?: string;
  twitter_player_stream_content_type?: string;
  fb_app_id?: string;
  fb_admins?: string;
  article_author?: string;
  article_published_time?: string;
  article_modified_time?: string;
  article_section?: string;
  article_tag?: string;
  // Webmaster verification tags
  google_verify?: string;
  bing_verify?: string;
  baidu_verify?: string;
  yandex_verify?: string;
  pinterest_verify?: string;
  norton_verify?: string;
  schema?: Record<string, any>;
}