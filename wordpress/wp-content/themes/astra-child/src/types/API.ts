import type { SortOption, Job } from '@/types';

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

export interface JobSummary {
  jenis_pekerjaan_taxo?: string | string[] | null;
  pendidikan_taxo?: string | string[] | null;
  pengalaman?: string | number | null;
  gender_taxo?: string | string[] | null;
  gaji_minimal?: string | number | null;
  gaji_maksimal?: string | number | null;
  umur_min?: string | number | null;
  umur_max?: string | number | null;
  lokasi_taxo?: string | string[] | null;
  deadline?: string | null;
}

// Shape returned by RESTData::getCardData()
export interface CardJob {
  slug?: string;
  title: string;
  nama_perusahaan?: string;
  ringkasanPekerjaan?: JobSummary | null;
  deadline?: string | null;
  statusjob?: number | string | null;
  permalink?: string;
  post_time?: string;
}

export type JobContactRow = {
  email_kontak?: string[];
  nomor_kontak?: string[];
  situs_kontak?: string[];
};

export interface SingleOverlayResponse {
  id?: number;
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