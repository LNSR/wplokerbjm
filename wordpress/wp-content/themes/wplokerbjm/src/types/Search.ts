import type { SortOption, BaseJobSearch } from './Shared';
import type { WPTaxonomyTerm } from './wordpress/Wordpress';
import type { TaxonomyType } from './wordpress/MetaBox';

// Base optional job filters
type OptionalJobFilters = JobFilterTaxonomy & {
  cari?: string | null
  sort?: SortOption | null
  context?: SearchContext | null
}

export type JobFilterTaxonomy = Partial<Record<TaxonomyType, string[] | null | undefined>>;
export type TaxonomyGroup = Exclude<TaxonomyType, 'lokasi_pekerjaan'> | 'lokasi'; // Map internal WP 'lokasi_pekerjaan' to 'lokasi' for internal grouping

// Base filters for search operations
export interface SearchFilters extends OptionalJobFilters
{
  cari: string | null
  sort: SortOption | null
}

// Context type for search and loadMore operations
export type SearchContext = 'search' | 'latest'

export type SearchTitle = 'Lowongan Terbaru' | 'Hasil Pencarian'

// Response for pagination operations
export type LoadMoreResponse = BaseJobSearch

// Simplified load more filters - flattened structure
export interface LoadMoreFilters extends OptionalJobFilters
{
  paged: number
}

// Filters for fetching job grid data
export interface JobGridFilters extends OptionalJobFilters
{
  paged?: number
  title?: string
  total_jobs?: number
}

// Standardized taxonomy term interface
export interface TaxonomyTerm extends Pick<WPTaxonomyTerm, 'slug' | 'name' | 'parent'>
{
  children?: TaxonomyTerm[]
}

export type TaxonomyTermsResponse = {
  lokasiTerms: TaxonomyTerm[]
  genderTerms: TaxonomyTerm[]
  pendidikanTerms: TaxonomyTerm[]
}