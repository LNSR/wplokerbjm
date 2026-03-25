// Homepage job grid saved state types
import type { SearchContext, SearchFilters, CardJob, SearchTitle } from '@/types';
export interface SearchState {
  jobs?: CardJob[] | null
  context: SearchContext
  title: SearchTitle
  totalJobs: number
  maxNumPages: number
  page: number
  filters: SearchFilters
}

export interface CarouselState {
  slideIndex: number
  offset: number
}